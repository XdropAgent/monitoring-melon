/*
 * ESP32 Sensor Monitoring Tanaman Melon
 * 
 * Hardware:
 *   - DHT22 (GPIO 4) — Suhu & Kelembapan
 *   - Soil Moisture (GPIO 32) — Kelembapan Tanah
 *   - Relay (GPIO 26) — Pompa Air
 *   - Button (GPIO 0) — Reset WiFi
 * 
 * Library:
 *   - Firebase ESP Client (Mobizt)
 *   - DHT sensor library (Adafruit)
 *   - Adafruit Unified Sensor
 */

#include <WiFi.h>
#include <Firebase_ESP_Client.h>
#include <DHT.h>
#include <addons/TokenHelper.h>
#include <addons/RTDBHelper.h>

// ===================== KONFIGURASI =====================
#define WIFI_SSID     "NAMA_WIFI"
#define WIFI_PASSWORD "PASSWORD_WIFI"

#define API_KEY       "FIREBASE_API_KEY"
#define DATABASE_URL  "https://PROJECT_ID-default-rtdb.firebaseio.com"

// ===================== PIN =====================
#define DHT_PIN       4
#define DHT_TYPE      DHT22
#define SOIL_PIN      32
#define RELAY_PIN     26
#define BUTTON_PIN    0

// ===================== KALIBRASI SOIL =====================
#define SOIL_DRY      4095
#define SOIL_WET      0

// ===================== INTERVAL =====================
#define INTERVAL_BACA    3000    // Baca sensor tiap 3 detik
#define INTERVAL_HISTORY 300000  // Simpan history tiap 5 menit

DHT dht(DHT_PIN, DHT_TYPE);
FirebaseData fbdo;
FirebaseAuth auth;
FirebaseConfig config;

bool pompaNyala      = false;
int  manualOverride  = -1;  // -1=auto, 0=paksa OFF, 1=paksa ON
unsigned long lastRead    = 0;
unsigned long lastHistory = 0;

// ===================== NTP =====================
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

String getTimestamp() {
  struct tm ti;
  if (!getLocalTime(&ti) || ti.tm_year < 120) return "n/a";
  char buf[25];
  strftime(buf, sizeof(buf), "%Y-%m-%d %H:%M:%S", &ti);
  return String(buf);
}

// ===================== RESET WiFi =====================
void resetWiFi() {
  Serial.println("Reset WiFi credentials...");
  WiFi.disconnect(true, true);
  delay(1000);
  ESP.restart();
}

// ===================== FIREBASE STREAM =====================
void streamCallback(FirebaseStream data) {
  Serial.printf("Stream: %s -> %s\n",
    data.dataPath().c_str(), data.stringData().c_str());
  if (data.dataPath() == "/") {
    int val = data.intData();
    if (val >= -1 && val <= 1) manualOverride = val;
  }
}

void streamTimeoutCallback(bool timeout) {
  if (timeout) Serial.println("Stream timeout, reconnecting...");
}

// ===================== SETUP =====================
void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("\nESP32 Sensor Booting...");

  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, HIGH);  // Relay OFF (active low)
  pinMode(BUTTON_PIN, INPUT_PULLUP);

  dht.begin();

  // WiFi
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("WiFi connecting");
  unsigned long ws = millis();
  while (WiFi.status() != WL_CONNECTED) {
    if (millis() - ws > 20000) { WiFi.begin(WIFI_SSID, WIFI_PASSWORD); ws = millis(); }
    delay(500); Serial.print(".");
  }
  Serial.println("\nWiFi: " + WiFi.localIP().toString());

  syncNTP();

  // Firebase
  config.api_key = API_KEY;
  config.database_url = DATABASE_URL;
  auth.user.email = "esp32@monitoring.local";
  auth.user.password = "esp32pass123";
  config.token_status_callback = tokenStatusCallback;
  Firebase.begin(&config, &auth);
  Firebase.reconnectWiFi(true);

  // Stream listener untuk kontrol pompa
  if (!Firebase.RTDB.beginStream(&fbdo, "/relay"))
    Serial.printf("Stream error: %s\n", fbdo.errorReason().c_str());
  Firebase.RTDB.setStreamCallback(&fbdo, streamCallback, streamTimeoutCallback);

  Serial.println("Firebase connected");
  Serial.println("========================================");
}

// ===================== LOOP =====================
void loop() {
  // Tombol reset WiFi (tahan 3 detik)
  if (digitalRead(BUTTON_PIN) == LOW) {
    delay(3000);
    if (digitalRead(BUTTON_PIN) == LOW) resetWiFi();
  }

  // Reconnect WiFi
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi putus, reconnecting...");
    WiFi.reconnect();
    unsigned long rs = millis();
    while (WiFi.status() != WL_CONNECTED) {
      if (millis() - rs > 15000) ESP.restart();
      delay(500);
    }
    syncNTP();
  }

  unsigned long now = millis();
  if (now - lastRead < INTERVAL_BACA) return;
  lastRead = now;

  // 1. Baca Sensor
  float t = dht.readTemperature();
  float h = dht.readHumidity();
  int   rawSoil = analogRead(SOIL_PIN);
  int   soil    = map(rawSoil, SOIL_DRY, SOIL_WET, 0, 100);
  soil = constrain(soil, 0, 100);

  if (isnan(t) || isnan(h)) {
    Serial.println("DHT error!");
    return;
  }

  // 2. Logika Pompa
  if (manualOverride == 1)       pompaNyala = true;
  else if (manualOverride == 0)  pompaNyala = false;
  else {
    if (soil < 40)       pompaNyala = true;
    else if (soil >= 60)  pompaNyala = false;
  }

  // 3. Eksekusi Relay
  digitalWrite(RELAY_PIN, pompaNyala ? LOW : HIGH);

  // 4. Timestamp
  String ts = getTimestamp();

  // 5. Serial Monitor
  Serial.printf("T:%.1fC | H:%.1f%% | Soil:%d%% | Pompa:%s | %s\n",
    t, h, soil, pompaNyala ? "ON" : "OFF", ts.c_str());

  // 6. Kirim ke Firebase
  if (Firebase.ready()) {
    // Latest (tiap 3 detik)
    FirebaseJson jsonL;
    jsonL.set("suhu", t);
    jsonL.set("kelembapan", h);
    jsonL.set("soil", soil);
    jsonL.set("pompa", pompaNyala ? "ON" : "OFF");
    jsonL.set("updated_at", ts);
    Firebase.RTDB.setJSON(&fbdo, "/sensor/latest", &jsonL);

    // History (tiap 5 menit)
    if (now - lastHistory >= INTERVAL_HISTORY) {
      lastHistory = now;
      FirebaseJson jsonH;
      jsonH.set("suhu", t);
      jsonH.set("kelembapan", h);
      jsonH.set("soil", soil);
      jsonH.set("pompa", pompaNyala ? "ON" : "OFF");
      jsonH.set("created_at", ts);
      Firebase.RTDB.pushJSON(&fbdo, "/sensor/history", &jsonH);
      Serial.println("History saved");
    }
  }
}
