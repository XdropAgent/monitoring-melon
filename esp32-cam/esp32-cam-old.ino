/**
 * ESP32-CAM Melon Monitor — OLD VERSION (Local HTTP Server)
 * 
 * Fitur:
 *   - MJPEG Live Stream (port 80, single device)
 *   - Capture endpoint (port 81)
 *   - Auto capture terjadwal (12:00, 16:00, 20:00)
 *   - Upload base64 ke Firebase RTDB
 *   - Auto-retry on failure
 * 
 * Hardware: ESP32-CAM AI Thinker
 * Partition: Huge APP (3MB No OTA/1MB SPIFFS)
 * 
 * NOTE: Kode lama, stream cuma support 1 device.
 *       Kode baru pake WebSocket ke VPS proxy (multi-device).
 */

#include "esp_camera.h"
#include <WiFi.h>
#include "esp_http_server.h"
#include <WiFiClientSecure.h>
#include <time.h>
#include "soc/soc.h"
#include "soc/rtc_cntl_reg.h"

// ===================== KONFIGURASI =====================
const char* ssid     = "Nauval";
const char* password = "pasuruan";

const char* FIREBASE_HOST = "monitoring-tanaman-d2cd2-default-rtdb.firebaseio.com";
const char* FIREBASE_AUTH = "AIzaSyCmIIEjajnv7m95T4gMoUUMCwFcf2qwClw";

const int JADWAL_JAM[]   = {12, 16, 20};
const int JADWAL_MENIT[] = {0, 0, 0};
const int TOTAL_JADWAL   = 3;

// ===================== PIN KAMERA (AI Thinker) =====================
#define PWDN_GPIO_NUM     32
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM      0
#define SIOD_GPIO_NUM     26
#define SIOC_GPIO_NUM     27
#define Y9_GPIO_NUM       35
#define Y8_GPIO_NUM       34
#define Y7_GPIO_NUM       39
#define Y6_GPIO_NUM       36
#define Y5_GPIO_NUM       21
#define Y4_GPIO_NUM       19
#define Y3_GPIO_NUM       18
#define Y2_GPIO_NUM        5
#define VSYNC_GPIO_NUM    25
#define HREF_GPIO_NUM     23
#define PCLK_GPIO_NUM     22

// ===================== STREAM =====================
#define PART_BOUNDARY "123456789000000000000987654321"
static const char* STREAM_CONTENT_TYPE = "multipart/x-mixed-replace;boundary=" PART_BOUNDARY;
static const char* STREAM_BOUNDARY     = "\r\n--" PART_BOUNDARY "\r\n";
static const char* STREAM_PART         = "Content-Type: image/jpeg\r\nContent-Length: %u\r\n\r\n";

httpd_handle_t stream_httpd  = NULL;
httpd_handle_t capture_httpd = NULL;

volatile bool isUploading    = false;
bool sudahFotoHariIni[3]     = {false, false, false};
int  hariTerakhir            = -1;

// ===================== STREAM HANDLER =====================
static esp_err_t stream_handler(httpd_req_t* req) {
  camera_fb_t* fb = NULL;
  esp_err_t    res = ESP_OK;
  char         part_buf[64];

  httpd_resp_set_hdr(req, "Access-Control-Allow-Origin", "*");
  res = httpd_resp_set_type(req, STREAM_CONTENT_TYPE);
  if (res != ESP_OK) return res;

  while (true) {
    int waitCount = 0;
    while (isUploading) {
      delay(100);
      waitCount++;
      if (waitCount > 100) break;
    }

    fb = esp_camera_fb_get();
    if (!fb) { res = ESP_FAIL; break; }

    res = httpd_resp_send_chunk(req, STREAM_BOUNDARY, strlen(STREAM_BOUNDARY));
    if (res == ESP_OK) {
      size_t hlen = snprintf(part_buf, 64, STREAM_PART, fb->len);
      res = httpd_resp_send_chunk(req, part_buf, hlen);
    }
    if (res == ESP_OK)
      res = httpd_resp_send_chunk(req, (const char*)fb->buf, fb->len);

    esp_camera_fb_return(fb);
    delay(80);
    if (res != ESP_OK) break;
  }
  return res;
}

// ===================== CAPTURE HANDLER =====================
static esp_err_t capture_handler(httpd_req_t* req) {
  camera_fb_t* fb = esp_camera_fb_get();
  if (fb) esp_camera_fb_return(fb);
  delay(100);

  fb = esp_camera_fb_get();
  if (!fb) {
    httpd_resp_send_500(req);
    return ESP_FAIL;
  }

  httpd_resp_set_hdr(req, "Access-Control-Allow-Origin", "*");
  httpd_resp_set_hdr(req, "Cache-Control", "no-cache, no-store, must-revalidate");
  httpd_resp_set_type(req, "image/jpeg");
  httpd_resp_set_hdr(req, "Content-Disposition", "inline; filename=capture.jpg");

  esp_err_t res = httpd_resp_send(req, (const char*)fb->buf, fb->len);
  esp_camera_fb_return(fb);
  Serial.printf("[/capture] JPEG dikirim: %d bytes\n", fb->len);
  return res;
}

static esp_err_t options_handler(httpd_req_t* req) {
  httpd_resp_set_hdr(req, "Access-Control-Allow-Origin", "*");
  httpd_resp_set_hdr(req, "Access-Control-Allow-Methods", "GET, OPTIONS");
  httpd_resp_set_status(req, "204 No Content");
  httpd_resp_send(req, NULL, 0);
  return ESP_OK;
}

// ===================== START SERVER =====================
void startCameraServer() {
  httpd_config_t cfg1 = HTTPD_DEFAULT_CONFIG();
  cfg1.server_port    = 80;
  cfg1.max_open_sockets = 3;
  httpd_uri_t stream_uri  = { .uri="/",  .method=HTTP_GET,     .handler=stream_handler,  .user_ctx=NULL };
  httpd_uri_t options_uri = { .uri="/",  .method=HTTP_OPTIONS, .handler=options_handler, .user_ctx=NULL };

  if (httpd_start(&stream_httpd, &cfg1) == ESP_OK) {
    httpd_register_uri_handler(stream_httpd, &stream_uri);
    httpd_register_uri_handler(stream_httpd, &options_uri);
    Serial.println("Stream server aktif → port 80");
  }

  httpd_config_t cfg2 = HTTPD_DEFAULT_CONFIG();
  cfg2.server_port    = 81;
  cfg2.max_open_sockets = 2;
  cfg2.ctrl_port      = 32769;

  httpd_uri_t capture_uri  = { .uri="/capture", .method=HTTP_GET,     .handler=capture_handler, .user_ctx=NULL };
  httpd_uri_t options_uri2 = { .uri="/capture", .method=HTTP_OPTIONS, .handler=options_handler, .user_ctx=NULL };

  if (httpd_start(&capture_httpd, &cfg2) == ESP_OK) {
    httpd_register_uri_handler(capture_httpd, &capture_uri);
    httpd_register_uri_handler(capture_httpd, &options_uri2);
    Serial.println("Capture server aktif → port 81 (/capture)");
  }
}

// ===================== NTP & WAKTU =====================
void syncNTP() {
  configTime(0, 0, "pool.ntp.org");
  delay(100);
  configTime(0, 0, "pool.ntp.org", "time.nist.gov", "time.google.com");
  setenv("TZ", "WIB-7", 1);
  tzset();

  Serial.print("Sync NTP");
  struct tm ti;
  unsigned long s = millis();
  while (true) {
    if (millis() - s > 30000) { Serial.println(" timeout"); return; }
    if (getLocalTime(&ti) && ti.tm_year >= 124) break;
    delay(500); Serial.print(".");
  }
  char buf[25];
  strftime(buf, sizeof(buf), "%Y-%m-%d %H:%M:%S", &ti);
  Serial.println(" OK: " + String(buf));
  Serial.printf("  tm_hour=%d, tm_min=%d (harus WIB)\n", ti.tm_hour, ti.tm_min);
}

String getTimeString() {
  struct tm ti;
  if (!getLocalTime(&ti) || ti.tm_year < 124) return "n/a";
  char buf[25];
  strftime(buf, sizeof(buf), "%Y-%m-%d %H:%M:%S", &ti);
  return String(buf);
}

unsigned long getUnixTimestamp() {
  time_t now;
  time(&now);
  return (unsigned long)now;
}

// ===================== BASE64 =====================
static const char b64chars[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

String base64Encode(uint8_t* data, size_t len) {
  String result = "";
  result.reserve((len / 3 + 1) * 4 + 4);
  int i = 0;
  uint8_t b3[3], b4[4];
  while (len--) {
    b3[i++] = *data++;
    if (i == 3) {
      b4[0] = (b3[0] & 0xfc) >> 2;
      b4[1] = ((b3[0] & 0x03) << 4) + ((b3[1] & 0xf0) >> 4);
      b4[2] = ((b3[1] & 0x0f) << 2) + ((b3[2] & 0xc0) >> 6);
      b4[3] = b3[2] & 0x3f;
      for (i = 0; i < 4; i++) result += b64chars[b4[i]];
      i = 0;
    }
  }
  if (i) {
    for (int j = i; j < 3; j++) b3[j] = 0;
    b4[0] = (b3[0] & 0xfc) >> 2;
    b4[1] = ((b3[0] & 0x03) << 4) + ((b3[1] & 0xf0) >> 4);
    b4[2] = ((b3[1] & 0x0f) << 2) + ((b3[2] & 0xc0) >> 6);
    for (int j = 0; j < i + 1; j++) result += b64chars[b4[j]];
    while (i++ < 3) result += '=';
  }
  return result;
}

// ===================== UPLOAD KE FIREBASE =====================
bool uploadToFirebase() {
  struct tm ti;

  if (!getLocalTime(&ti) || ti.tm_year < 124) {
    Serial.println("NTP belum valid, sync ulang...");
    syncNTP();
    delay(2000);
    if (!getLocalTime(&ti) || ti.tm_year < 124) {
      Serial.println("NTP gagal (tahun tidak valid), skip upload");
      return false;
    }
  }

  char debugBuf[30];
  strftime(debugBuf, sizeof(debugBuf), "%Y-%m-%d %H:%M:%S", &ti);
  Serial.printf("Waktu saat upload: %s WIB\n", debugBuf);

  Serial.println("\n=== Ambil foto untuk Firebase ===");
  isUploading = true;

  for (int i = 0; i < 3; i++) {
    camera_fb_t* w = esp_camera_fb_get();
    if (w) esp_camera_fb_return(w);
    delay(150);
  }

  camera_fb_t* fb = esp_camera_fb_get();
  if (!fb || !fb->buf || fb->len < 500) {
    Serial.printf("Foto gagal! fb=%p len=%d\n", fb, fb ? fb->len : 0);
    if (fb) esp_camera_fb_return(fb);
    isUploading = false;
    return false;
  }

  Serial.printf("Foto OK: %d bytes (%dx%d)\n", fb->len, fb->width, fb->height);

  String b64 = base64Encode(fb->buf, fb->len);
  esp_camera_fb_return(fb);
  fb = NULL;

  Serial.printf("Base64: %d chars\n", b64.length());

  String waktu = getTimeString();
  unsigned long ts = getUnixTimestamp();

  Serial.printf("Waktu string : %s\n", waktu.c_str());
  Serial.printf("Unix ts (UTC): %lu\n", ts);

  String path = "/foto.json?auth=" + String(FIREBASE_AUTH);

  String bodyPrefix = "{\"image\":\"";
  String bodySuffix = "\",\"timestamp\":" + String((unsigned long long)ts * 1000ULL) +
                      ",\"waktu\":\"" + waktu + "\"}";
  int bodyLen = bodyPrefix.length() + b64.length() + bodySuffix.length();

  String header =
    "POST " + path + " HTTP/1.1\r\n"
    "Host: " + String(FIREBASE_HOST) + "\r\n"
    "Content-Type: application/json\r\n"
    "Content-Length: " + String(bodyLen) + "\r\n"
    "Connection: close\r\n\r\n";

  Serial.printf("Upload: total body %d bytes\n", bodyLen);

  for (int attempt = 1; attempt <= 3; attempt++) {
    Serial.printf("Attempt %d/3...\n", attempt);

    WiFiClientSecure client;
    client.setInsecure();
    client.setTimeout(20);

    if (!client.connect(FIREBASE_HOST, 443)) {
      Serial.println("Gagal konek Firebase");
      delay(2000);
      continue;
    }

    client.print(header);
    client.print(bodyPrefix);

    int sent = 0;
    const int CHUNK = 512;
    while (sent < (int)b64.length()) {
      int end = min(sent + CHUNK, (int)b64.length());
      client.print(b64.substring(sent, end));
      sent = end;
    }
    client.print(bodySuffix);

    bool timedOut = false;
    unsigned long t0 = millis();
    while (client.available() == 0) {
      if (millis() - t0 > 20000) {
        Serial.println("Response timeout");
        client.stop();
        timedOut = true;
        break;
      }
      delay(10);
    }
    if (timedOut) { delay(2000); continue; }

    String response = client.readStringUntil('\n');
    client.stop();
    Serial.println("Response: " + response);

    if (response.indexOf("200") >= 0 || response.indexOf("201") >= 0) {
      Serial.println("✅ Upload OK! Waktu: " + waktu);
      isUploading = false;
      b64 = "";
      return true;
    }

    Serial.println("Upload gagal: " + response);
    delay(2000);
  }

  Serial.println("❌ Upload GAGAL setelah 3x percobaan");
  isUploading = false;
  b64 = "";
  return false;
}

// ===================== CEK JADWAL =====================
void cekJadwalFoto() {
  struct tm ti;
  if (!getLocalTime(&ti) || ti.tm_year < 124) return;

  int jam   = ti.tm_hour;
  int menit = ti.tm_min;
  int hari  = ti.tm_yday;

  if (hari != hariTerakhir) {
    for (int i = 0; i < TOTAL_JADWAL; i++) sudahFotoHariIni[i] = false;
    hariTerakhir = hari;
  }

  for (int i = 0; i < TOTAL_JADWAL; i++) {
    if (jam == JADWAL_JAM[i] && menit == JADWAL_MENIT[i] && !sudahFotoHariIni[i]) {
      Serial.printf("\n>>> Jadwal %02d:%02d — ambil foto!\n", JADWAL_JAM[i], JADWAL_MENIT[i]);
      bool ok = uploadToFirebase();
      if (ok) {
        sudahFotoHariIni[i] = true;
        Serial.printf("✅ Foto %02d:%02d tersimpan!\n", JADWAL_JAM[i], JADWAL_MENIT[i]);
      } else {
        Serial.printf("❌ Foto %02d:%02d GAGAL — retry menit depan\n", JADWAL_JAM[i], JADWAL_MENIT[i]);
      }
      break;
    }
  }
}

// ===================== SETUP =====================
void setup() {
  WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0);
  Serial.begin(115200);
  delay(1000);
  Serial.println("\nESP32-CAM Booting...");

  if (psramInit()) Serial.println("PSRAM OK");
  else Serial.println("⚠️ PSRAM TIDAK AKTIF");

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.setTxPower(WIFI_POWER_19_5dBm);
  WiFi.begin(ssid, password);

  Serial.print("WiFi connecting");
  unsigned long ws = millis();
  while (WiFi.status() != WL_CONNECTED) {
    if (millis() - ws > 20000) { WiFi.begin(ssid, password); ws = millis(); }
    delay(500); Serial.print(".");
  }
  Serial.println("\nIP: " + WiFi.localIP().toString());

  pinMode(PWDN_GPIO_NUM, OUTPUT);
  digitalWrite(PWDN_GPIO_NUM, LOW);
  delay(100);

  camera_config_t config;
  config.ledc_channel  = LEDC_CHANNEL_0;
  config.ledc_timer    = LEDC_TIMER_0;
  config.pin_d0        = Y2_GPIO_NUM;
  config.pin_d1        = Y3_GPIO_NUM;
  config.pin_d2        = Y4_GPIO_NUM;
  config.pin_d3        = Y5_GPIO_NUM;
  config.pin_d4        = Y6_GPIO_NUM;
  config.pin_d5        = Y7_GPIO_NUM;
  config.pin_d6        = Y8_GPIO_NUM;
  config.pin_d7        = Y9_GPIO_NUM;
  config.pin_xclk      = XCLK_GPIO_NUM;
  config.pin_pclk      = PCLK_GPIO_NUM;
  config.pin_vsync     = VSYNC_GPIO_NUM;
  config.pin_href      = HREF_GPIO_NUM;
  config.pin_sccb_sda  = SIOD_GPIO_NUM;
  config.pin_sccb_scl  = SIOC_GPIO_NUM;
  config.pin_pwdn      = PWDN_GPIO_NUM;
  config.pin_reset     = RESET_GPIO_NUM;
  config.xclk_freq_hz  = 10000000;
  config.pixel_format  = PIXFORMAT_JPEG;
  config.frame_size    = FRAMESIZE_QVGA;
  config.jpeg_quality  = 12;
  config.fb_count      = 2;
  config.fb_location   = CAMERA_FB_IN_PSRAM;
  config.grab_mode     = CAMERA_GRAB_LATEST;

  if (esp_camera_init(&config) != ESP_OK) {
    Serial.println("❌ Kamera gagal! Restart...");
    delay(3000);
    ESP.restart();
  }
  Serial.println("✅ Kamera OK");

  sensor_t* s = esp_camera_sensor_get();
  if (s) {
    s->set_vflip(s, 1);
    s->set_hmirror(s, 1);
    s->set_brightness(s, 0);
    s->set_contrast(s, 0);
    s->set_saturation(s, 0);
    s->set_whitebal(s, 1);
    s->set_awb_gain(s, 1);
    s->set_exposure_ctrl(s, 1);
    s->set_aec2(s, 1);
  }

  syncNTP();
  startCameraServer();

  Serial.println("\n===== SIAP =====");
  Serial.println("Stream  : http://" + WiFi.localIP().toString() + "/");
  Serial.println("Capture : http://" + WiFi.localIP().toString() + ":81/capture");
  Serial.print("Jadwal foto: ");
  for (int i = 0; i < TOTAL_JADWAL; i++)
    Serial.printf("%02d:%02d ", JADWAL_JAM[i], JADWAL_MENIT[i]);
  Serial.println();
}

// ===================== LOOP =====================
void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi terputus, reconnect...");
    WiFi.reconnect();
    unsigned long rs = millis();
    while (WiFi.status() != WL_CONNECTED) {
      if (millis() - rs > 15000) {
        Serial.println("WiFi gagal, restart...");
        ESP.restart();
      }
      delay(500);
    }
    Serial.println("WiFi OK: " + WiFi.localIP().toString());
    syncNTP();
  }

  static unsigned long lastCek = 0;
  if (millis() - lastCek >= 10000) {
    lastCek = millis();
    cekJadwalFoto();
  }

  delay(1000);
}
