#include <WiFi.h>
#include <Firebase_ESP_Client.h>
#include <addons/TokenHelper.h>
#include <addons/RTDBHelper.h>
#include <DHT.h>
#include <time.h>

// ── KONFIGURASI WiFi ──
#define WIFI_SSID     "NAMA_WIFI"
#define WIFI_PASSWORD "PASSWORD_WIFI"

// ── KONFIGURASI FIREBASE ──
#define FIREBASE_HOST "PROJECT_ID-default-rtdb.firebaseio.com"
#define FIREBASE_AUTH "FIREBASE_API_KEY"

// ── PIN PERANGKAT ──
#define DHT_PIN   4
#define DHT_TYPE  DHT22
#define SOIL_PIN  32
#define RELAY_PIN 26

// ── KALIBRASI SOIL MOISTURE ──
#define SOIL_DRY 4095
#define SOIL_WET 0

// ── INTERVAL ──
#define INTERVAL_SENSOR  3000      // Update latest setiap 3 detik
#define INTERVAL_HISTORY 300000    // Simpan riwayat setiap 5 menit

// ══════════════════════════════════════════════════════
// KALIBRASI DHT22
// Rumus: OFFSET = Nilai Referensi (HTC-1) - Nilai DHT22
// Suhu  : 27.0 - 28.6 = -1.6
// Hum   : 84.0 - 93.5 = -9.5
// Jika hasil masih belum akurat, sesuaikan nilai ini
// ══════════════════════════════════════════════════════
#define OFFSET_SUHU       -1.6
#define OFFSET_KELEMBAPAN -9.5

DHT dht(DHT_PIN, DHT_TYPE);
FirebaseData fbdo;
FirebaseData fbdoStream;
FirebaseAuth auth;
FirebaseConfig config;

bool pompaNyala     = false;
int  manualOverride = -1; // -1: Auto | 0: OFF paksa | 1: ON paksa

unsigned long lastSensor  = 0;
unsigned long lastHistory = 0;

// ── Stream Callback ──
void streamCallback(FirebaseStream data) {
  if (data.dataType() == "int") {
    manualOverride = data.intData();
    Serial.printf("Kontrol Dashboard: %s\n",
      manualOverride == 1 ? "ON MANUAL" :
      manualOverride == 0 ? "OFF MANUAL" : "MODE OTOMATIS");
  }
}

void streamTimeoutCallback(bool timeout) {
  if (timeout) Serial.println("Stream timeout, retrying...");
}

// ── Fungsi Timestamp ──
String getTimestamp() {
  struct tm timeinfo;
  if (!getLocalTime(&timeinfo)) return "n/a";
  char buf[25];
  strftime(buf, sizeof(buf), "%Y-%m-%d %H:%M:%S", &timeinfo);
  return String(buf);
}

// ======================================================
// SETUP
// ======================================================
void setup() {
  Serial.begin(115200);
  dht.begin();
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, HIGH); // Relay OFF saat start (Active Low)

  // Koneksi WiFi
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Connecting WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500); Serial.print(".");
  }
  Serial.println("\nTerhubung ke WiFi: " + WiFi.localIP().toString());

  // Konfigurasi Firebase
  config.database_url               = FIREBASE_HOST;
  config.signer.tokens.legacy_token = FIREBASE_AUTH;
  Firebase.begin(&config, &auth);
  Firebase.reconnectWiFi(true);

  // Stream relay (kontrol manual dari web)
  if (!Firebase.RTDB.beginStream(&fbdoStream, "/relay")) {
    Serial.println("Stream error: " + fbdoStream.errorReason());
  }
  Firebase.RTDB.setStreamCallback(&fbdoStream, streamCallback, streamTimeoutCallback);

  // Sync NTP WIB (UTC+7)
  configTime(7 * 3600, 0, "pool.ntp.org", "time.nist.gov", "id.pool.ntp.org");
  Serial.print("Sinkronisasi NTP");
  struct tm timeinfo;
  int retry = 0;
  while (!getLocalTime(&timeinfo) && retry < 20) {
    delay(500); Serial.print("."); retry++;
  }
  if (getLocalTime(&timeinfo)) {
    Serial.println("\nNTP OK: " + getTimestamp());
  } else {
    Serial.println("\nNTP GAGAL - waktu akan tampil n/a");
  }

  // Tampilkan info kalibrasi
  Serial.println("==============================");
  Serial.printf("Kalibrasi Suhu       : %+.1f C\n", OFFSET_SUHU);
  Serial.printf("Kalibrasi Kelembapan : %+.1f %%\n", OFFSET_KELEMBAPAN);
  Serial.println("==============================");
}

// ======================================================
// LOOP UTAMA
// ======================================================
void loop() {
  unsigned long now = millis();

  if (now - lastSensor >= INTERVAL_SENSOR) {
    lastSensor = now;

    // 1. Baca Sensor RAW
    float tRaw  = dht.readTemperature();
    float hRaw  = dht.readHumidity();
    int soilRaw = analogRead(SOIL_PIN);
    int soil    = constrain(map(soilRaw, SOIL_DRY, SOIL_WET, 0, 100), 0, 100);

    if (isnan(tRaw) || isnan(hRaw)) {
      Serial.println("DHT22 tidak terbaca!"); return;
    }

    // 2. Terapkan Kalibrasi
    float t = constrain(tRaw + OFFSET_SUHU,       -40.0, 80.0);
    float h = constrain(hRaw + OFFSET_KELEMBAPAN,   0.0, 100.0);

    // 3. Logika Pompa
    if (manualOverride == 1) {
      pompaNyala = true;   // Paksa ON dari web
    }
    else if (manualOverride == 0) {
      pompaNyala = false;  // Paksa OFF dari web
    }
    else {
      // MODE OTOMATIS
      // Nyalakan pompa jika soil < 40% (KERING)
      // Matikan pompa jika soil >= 60% (BASAH)
      // Antara 40-59% -> pertahankan status pompa sebelumnya
      if (soil < 40)       pompaNyala = true;
      else if (soil >= 60) pompaNyala = false;
    }

    // 4. Eksekusi Relay
    digitalWrite(RELAY_PIN, pompaNyala ? LOW : HIGH);

    // 5. Timestamp
    String ts = getTimestamp();

    // 6. Serial Monitor - tampilkan RAW dan hasil kalibrasi
    Serial.printf("RAW  -> T:%.1fC | H:%.1f%%\n", tRaw, hRaw);
    Serial.printf("KALI -> T:%.1fC | H:%.1f%% | Soil:%d%% | Pompa:%s | Mode:%s | %s\n",
      t, h, soil,
      pompaNyala ? "ON" : "OFF",
      manualOverride == -1 ? "AUTO" : "MANUAL",
      ts.c_str());

    // 7. Kirim ke Firebase (nilai sudah terkalibrasi)
    if (Firebase.ready()) {

      // ── LATEST: update setiap 3 detik ──
      FirebaseJson jsonLatest;
      jsonLatest.set("suhu",       t);    // terkalibrasi
      jsonLatest.set("kelembapan", h);    // terkalibrasi
      jsonLatest.set("soil",       soil);
      jsonLatest.set("pompa",      pompaNyala ? "ON" : "OFF");
      jsonLatest.set("updated_at", ts);
      Firebase.RTDB.setJSON(&fbdo, "/sensor/latest", &jsonLatest);

      // ── HISTORY: simpan setiap 5 menit ──
      if (now - lastHistory >= INTERVAL_HISTORY) {
        lastHistory = now;

        FirebaseJson jsonHistory;
        jsonHistory.set("suhu",       t);    // terkalibrasi
        jsonHistory.set("kelembapan", h);    // terkalibrasi
        jsonHistory.set("soil",       soil);
        jsonHistory.set("pompa",      pompaNyala ? "ON" : "OFF");
        jsonHistory.set("updated_at", ts);
        jsonHistory.set("created_at", ts);

        Firebase.RTDB.pushJSON(&fbdo, "/sensor/history", &jsonHistory);
        Serial.println("Riwayat tersimpan! Waktu: " + ts);
      }
    }
  }
}
