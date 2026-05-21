/*
 * ESP32 CAM Monitoring Tanaman Melon
 * 
 * Hardware: ESP32 CAM AI Thinker
 * 
 * Fitur:
 *   - MJPEG Live Stream (port 80)
 *   - Foto terjadwal (12:00, 16:00, 20:00)
 *   - Watermark timestamp di foto
 *   - Upload base64 ke Firebase RTDB
 * 
 * Board: AI Thinker ESP32-CAM
 * Partition: Huge APP (3MB No OTA/1MB SPIFFS)
 */

#include "esp_camera.h"
#include <WiFi.h>
#include "esp_http_server.h"
#include <WiFiClientSecure.h>
#include <time.h>
#include "soc/soc.h"
#include "soc/rtc_cntl_reg.h"

// ===================== KONFIGURASI =====================
const char* ssid     = "NAMA_WIFI";
const char* password = "PASSWORD_WIFI";

const char* FIREBASE_HOST = "PROJECT_ID-default-rtdb.firebaseio.com";
const char* FIREBASE_AUTH = "FIREBASE_API_KEY";

const int JADWAL_JAM[] = {12, 16, 20};
const int TOTAL_JADWAL = 3;

// ===================== STATIC IP =====================
// Sesuaikan dengan jaringan WiFi kamu
IPAddress local_IP(192, 168, 1, 100);
IPAddress gateway(192, 168, 1, 1);
IPAddress subnet(255, 255, 255, 0);
IPAddress dns(8, 8, 8, 8);

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

httpd_handle_t stream_httpd = NULL;
volatile bool isUploading   = false;
bool sudahFotoHariIni[3]    = {false, false, false};
int hariTerakhir             = -1;

// ===================== FONT BITMAP 5x7 =====================
static const uint8_t font5x7[][5] = {
  {0x00,0x00,0x00,0x00,0x00},{0x00,0x00,0x5F,0x00,0x00},{0x00,0x07,0x00,0x07,0x00},
  {0x14,0x7F,0x14,0x7F,0x14},{0x24,0x2A,0x7F,0x2A,0x12},{0x23,0x13,0x08,0x64,0x62},
  {0x36,0x49,0x55,0x22,0x50},{0x00,0x05,0x03,0x00,0x00},{0x00,0x1C,0x22,0x41,0x00},
  {0x00,0x41,0x22,0x1C,0x00},{0x14,0x08,0x3E,0x08,0x14},{0x08,0x08,0x3E,0x08,0x08},
  {0x00,0x50,0x30,0x00,0x00},{0x08,0x08,0x08,0x08,0x08},{0x00,0x60,0x60,0x00,0x00},
  {0x20,0x10,0x08,0x04,0x02},{0x3E,0x51,0x49,0x45,0x3E},{0x00,0x42,0x7F,0x40,0x00},
  {0x42,0x61,0x51,0x49,0x46},{0x21,0x41,0x45,0x4B,0x31},{0x18,0x14,0x12,0x7F,0x10},
  {0x27,0x45,0x45,0x45,0x39},{0x3C,0x4A,0x49,0x49,0x30},{0x01,0x71,0x09,0x05,0x03},
  {0x36,0x49,0x49,0x49,0x36},{0x06,0x49,0x49,0x29,0x1E},{0x00,0x36,0x36,0x00,0x00},
  {0x00,0x56,0x36,0x00,0x00},{0x08,0x14,0x22,0x41,0x00},{0x14,0x14,0x14,0x14,0x14},
  {0x00,0x41,0x22,0x14,0x08},{0x02,0x01,0x51,0x09,0x06},{0x32,0x49,0x79,0x41,0x3E},
  {0x7E,0x11,0x11,0x11,0x7E},{0x7F,0x49,0x49,0x49,0x36},{0x3E,0x41,0x41,0x41,0x22},
  {0x7F,0x41,0x41,0x22,0x1C},{0x7F,0x49,0x49,0x49,0x41},{0x7F,0x09,0x09,0x09,0x01},
  {0x3E,0x41,0x49,0x49,0x7A},{0x7F,0x08,0x08,0x08,0x7F},{0x00,0x41,0x7F,0x41,0x00},
  {0x20,0x40,0x41,0x3F,0x01},{0x7F,0x08,0x14,0x22,0x41},{0x7F,0x40,0x40,0x40,0x40},
  {0x7F,0x02,0x0C,0x02,0x7F},{0x7F,0x04,0x08,0x10,0x7F},{0x3E,0x41,0x41,0x41,0x3E},
  {0x7F,0x09,0x09,0x09,0x06},{0x3E,0x41,0x51,0x21,0x5E},{0x7F,0x09,0x19,0x29,0x46},
  {0x46,0x49,0x49,0x49,0x31},{0x01,0x01,0x7F,0x01,0x01},{0x3F,0x40,0x40,0x40,0x3F},
  {0x1F,0x20,0x40,0x20,0x1F},{0x3F,0x40,0x38,0x40,0x3F},{0x63,0x14,0x08,0x14,0x63},
  {0x07,0x08,0x70,0x08,0x07},{0x61,0x51,0x49,0x45,0x43}
};

// ===================== DRAW TEXT =====================
void drawChar(uint8_t* buf, int bw, int bh, int x, int y, char c, uint16_t color) {
  if (!buf) return;
  if (c < 32 || c > 90) c = ' ';
  int idx = c - 32;
  for (int col = 0; col < 5; col++) {
    uint8_t bits = font5x7[idx][col];
    for (int row = 0; row < 7; row++) {
      if (bits & (1 << row)) {
        int px = x + col, py = y + row;
        if (px >= 0 && px < bw && py >= 0 && py < bh) {
          int offset = (py * bw + px) * 2;
          buf[offset]     = (color >> 8) & 0xFF;
          buf[offset + 1] = color & 0xFF;
        }
      }
    }
  }
}

void drawString(uint8_t* buf, int bw, int bh, int x, int y, const char* str, uint16_t color) {
  if (!buf || !str) return;
  while (*str) {
    char c = *str++;
    if (c >= 'a' && c <= 'z') c -= 32;
    drawChar(buf, bw, bh, x, y, c, color);
    x += 6;
  }
}

// ===================== FOTO DENGAN WATERMARK =====================
camera_fb_t* ambilFotoDenganWatermark(const char* waktuStr) {
  sensor_t* s = esp_camera_sensor_get();
  if (!s) return NULL;
  s->set_pixformat(s, PIXFORMAT_RGB565);
  delay(150);
  camera_fb_t* fb = esp_camera_fb_get();
  if (!fb || !fb->buf) {
    Serial.println("Gagal RGB565");
    s->set_pixformat(s, PIXFORMAT_JPEG);
    return NULL;
  }
  int w = fb->width, h = fb->height;
  drawString(fb->buf, w, h, 3, h - 11, waktuStr, 0x0000);
  drawString(fb->buf, w, h, 2, h - 12, waktuStr, 0xFFFF);

  uint8_t* jpg_buf = NULL;
  size_t jpg_len = 0;
  bool ok = frame2jpg(fb, 15, &jpg_buf, &jpg_len);
  esp_camera_fb_return(fb);
  s->set_pixformat(s, PIXFORMAT_JPEG);
  delay(150);
  if (!ok || !jpg_buf) { if(jpg_buf) free(jpg_buf); return NULL; }

  static camera_fb_t fake_fb;
  fake_fb.buf = jpg_buf; fake_fb.len = jpg_len;
  fake_fb.width = w; fake_fb.height = h;
  fake_fb.format = PIXFORMAT_JPEG;
  return &fake_fb;
}

// ===================== STREAM HANDLER =====================
static esp_err_t stream_handler(httpd_req_t* req) {
  camera_fb_t* fb = NULL;
  esp_err_t res = ESP_OK;
  char part_buf[64];
  httpd_resp_set_hdr(req, "Access-Control-Allow-Origin", "*");
  res = httpd_resp_set_type(req, STREAM_CONTENT_TYPE);
  if (res != ESP_OK) return res;
  while (true) {
    while (isUploading) delay(100);
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
    if (res != ESP_OK) break;
  }
  return res;
}

static esp_err_t options_handler(httpd_req_t* req) {
  httpd_resp_set_hdr(req, "Access-Control-Allow-Origin", "*");
  httpd_resp_set_hdr(req, "Access-Control-Allow-Methods", "GET, OPTIONS");
  httpd_resp_set_status(req, "204 No Content");
  httpd_resp_send(req, NULL, 0);
  return ESP_OK;
}

void startCameraServer() {
  httpd_config_t config = HTTPD_DEFAULT_CONFIG();
  config.server_port = 80;
  config.max_open_sockets = 3;
  httpd_uri_t stream_uri  = { .uri="/", .method=HTTP_GET,     .handler=stream_handler,  .user_ctx=NULL };
  httpd_uri_t options_uri = { .uri="/", .method=HTTP_OPTIONS, .handler=options_handler, .user_ctx=NULL };
  if (httpd_start(&stream_httpd, &config) == ESP_OK) {
    httpd_register_uri_handler(stream_httpd, &stream_uri);
    httpd_register_uri_handler(stream_httpd, &options_uri);
    Serial.println("Stream aktif di port 80");
  }
}

// ===================== NTP & WAKTU =====================
void syncNTP() {
  configTime(7 * 3600, 0, "pool.ntp.org", "time.nist.gov");
  Serial.print("Sync NTP");
  struct tm ti;
  unsigned long s = millis();
  while (true) {
    if (millis() - s > 20000) { Serial.println(" timeout"); return; }
    if (getLocalTime(&ti) && ti.tm_year > 120) break;
    delay(500); Serial.print(".");
  }
  char buf[25];
  strftime(buf, sizeof(buf), "%Y-%m-%d %H:%M:%S", &ti);
  Serial.println(" OK: " + String(buf));
}

String getTimeString() {
  struct tm ti;
  if (!getLocalTime(&ti) || ti.tm_year < 120) return "n/a";
  char buf[25];
  strftime(buf, sizeof(buf), "%Y-%m-%d %H:%M:%S", &ti);
  return String(buf);
}

unsigned long getUnixTimestamp() {
  struct tm ti;
  if (!getLocalTime(&ti) || ti.tm_year < 120) return 0;
  return mktime(&ti);
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
      b4[0]=(b3[0]&0xfc)>>2; b4[1]=((b3[0]&0x03)<<4)+((b3[1]&0xf0)>>4);
      b4[2]=((b3[1]&0x0f)<<2)+((b3[2]&0xc0)>>6); b4[3]=b3[2]&0x3f;
      for (i=0;i<4;i++) result += b64chars[b4[i]];
      i = 0;
    }
  }
  if (i) {
    for (int j=i;j<3;j++) b3[j]=0;
    b4[0]=(b3[0]&0xfc)>>2; b4[1]=((b3[0]&0x03)<<4)+((b3[1]&0xf0)>>4);
    b4[2]=((b3[1]&0x0f)<<2)+((b3[2]&0xc0)>>6);
    for (int j=0;j<i+1;j++) result += b64chars[b4[j]];
    while (i++<3) result += '=';
  }
  return result;
}

// ===================== UPLOAD KE FIREBASE =====================
void uploadToFirebase() {
  struct tm ti;
  if (!getLocalTime(&ti) || ti.tm_year < 120) {
    syncNTP();
    if (!getLocalTime(&ti) || ti.tm_year < 120) return;
  }

  Serial.println("\nAmbil foto...");
  isUploading = true;
  camera_fb_t* warmup = esp_camera_fb_get();
  if (warmup) esp_camera_fb_return(warmup);
  delay(200);

  String waktu = getTimeString();
  char waktuChar[25];
  waktu.toCharArray(waktuChar, 25);
  camera_fb_t* fb = ambilFotoDenganWatermark(waktuChar);
  if (!fb || !fb->buf) { isUploading = false; return; }
  if (fb->len < 500) { free(fb->buf); isUploading = false; return; }

  Serial.printf("Foto: %d bytes\n", fb->len);
  String b64 = base64Encode(fb->buf, fb->len);
  free(fb->buf);
  isUploading = false;

  unsigned long ts = getUnixTimestamp() * 1000UL;
  String body = "{\"image\":\"" + b64 + "\",\"timestamp\":" + String(ts) + ",\"waktu\":\"" + waktu + "\"}";

  WiFiClientSecure client;
  client.setInsecure();
  client.setTimeout(15);
  if (!client.connect(FIREBASE_HOST, 443)) { Serial.println("Gagal konek Firebase"); return; }

  String path = "/foto.json?auth=" + String(FIREBASE_AUTH);
  client.print("POST "); client.print(path); client.println(" HTTP/1.1");
  client.print("Host: "); client.println(FIREBASE_HOST);
  client.println("Content-Type: application/json");
  client.print("Content-Length: "); client.println(body.length());
  client.println("Connection: close");
  client.println();

  int sent = 0;
  while (sent < (int)body.length()) {
    int end = min(sent + 512, (int)body.length());
    client.print(body.substring(sent, end));
    sent = end;
  }

  unsigned long timeout = millis();
  while (client.available() == 0) {
    if (millis() - timeout > 15000) { client.stop(); return; }
    delay(10);
  }
  Serial.println("Upload: " + client.readStringUntil('\n'));
  client.stop();
}

// ===================== CEK JADWAL =====================
void cekJadwalFoto() {
  struct tm ti;
  if (!getLocalTime(&ti) || ti.tm_year < 120) return;
  int jam = ti.tm_hour;
  int hari = ti.tm_yday;
  if (hari != hariTerakhir) {
    for (int i = 0; i < TOTAL_JADWAL; i++) sudahFotoHariIni[i] = false;
    hariTerakhir = hari;
  }
  for (int i = 0; i < TOTAL_JADWAL; i++) {
    if (jam == JADWAL_JAM[i] && !sudahFotoHariIni[i]) {
      Serial.printf("Jadwal %02d:00\n", JADWAL_JAM[i]);
      uploadToFirebase();
      sudahFotoHariIni[i] = true;
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
  else Serial.println("PSRAM TIDAK AKTIF");

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  if (!WiFi.config(local_IP, gateway, subnet, dns))
    Serial.println("Static IP gagal");

  WiFi.begin(ssid, password);
  Serial.print("WiFi");
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
  config.xclk_freq_hz  = 20000000;
  config.pixel_format  = PIXFORMAT_JPEG;
  config.frame_size    = FRAMESIZE_QVGA;
  config.jpeg_quality  = 10;
  config.fb_count      = 3;
  config.fb_location   = CAMERA_FB_IN_PSRAM;
  config.grab_mode     = CAMERA_GRAB_LATEST;

  if (esp_camera_init(&config) != ESP_OK) {
    Serial.println("Kamera gagal!");
    delay(3000); ESP.restart();
  }
  Serial.println("Kamera OK");

  syncNTP();
  startCameraServer();
  Serial.println("Stream: http://" + WiFi.localIP().toString());
}

// ===================== LOOP =====================
void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    WiFi.reconnect();
    unsigned long rs = millis();
    while (WiFi.status() != WL_CONNECTED) {
      if (millis() - rs > 15000) ESP.restart();
      delay(500);
    }
    syncNTP();
  }

  static unsigned long lastCek = 0;
  if (millis() - lastCek >= 60000) {
    lastCek = millis();
    cekJadwalFoto();
  }
  delay(1000);
}
