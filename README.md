     1|# 🍈 Monitoring Tanaman Melon
     2|
     3|Dashboard monitoring tanaman melon berbasis PHP/Laravel dengan ESP32 + Firebase Realtime Database.
     4|
     5|## Fitur
     6|
     7|- **Dashboard Real-time** — Suhu, kelembapan, soil moisture
     8|- **Live Camera Stream** — ESP32 CAM MJPEG stream
     9|- **Foto Terjadwal** — Otomatis foto jam 12:00, 16:00, 20:00
    10|- **Grafik Time-series** — Chart.js real-time
    11|- **Hapus Data** — Per rentang tanggal atau semua
    12|- **Auth** — Login/register Laravel Breeze
    13|- **Responsive** — Desktop & mobile
    14|
    15|## Arsitektur
    16|
    17|```
    18|ESP32 Sensor (DHT22, Soil, Relay) --> Firebase RTDB --> Laravel Dashboard
    19|ESP32 CAM (AI Thinker)            --> Firebase RTDB (foto)
    20|                                  --> Live Stream (WiFi lokal)
    21|```
    22|
    23|## Kebutuhan
    24|
    25|- VPS Ubuntu 22.04+ (minimal 1GB RAM)
    26|- Domain (opsional, bisa pakai IP langsung)
    27|- ESP32 Wroom + DHT22 + Soil Sensor + Relay
    28|- ESP32 CAM AI Thinker
    29|- Akun Firebase (gratis)
    30|- WiFi lokal
    31|
    32|## Installasi Otomatis
    33|
    34|```bash
    35|curl -sL https://raw.githubusercontent.com/XdropAgent/monitoring-melon/main/install.sh | bash
    36|```
    37|
    38|## Installasi Manual
    39|
    40|### 1. Clone & Setup
    41|
    42|```bash
    43|git clone https://github.com/XdropAgent/monitoring-melon.git
    44|cd monitoring-melon
    45|composer install
    46|cp .env.example .env
    47|php artisan key:generate
    48|php artisan migrate
    49|```
    50|
    51|### 2. Edit .env
    52|
    53|```bash
    54|nano .env
    55|```
    56|
    57|Isi Firebase config:
    58|```
    59|FIREBASE_API_KEY=AIzaSy...dari Firebase Console
    60|FIREBASE_DATABASE_URL=https://project-id-default-rtdb.firebaseio.com
    61|FIREBASE_PROJECT_ID=project-id
    62|ESP32_CAM_IP=http://IP_ESP32_CAM
    63|```
    64|
    65|### 3. Buat User
    66|
    67|```bash
    68|php artisan tinker
    69|```
    70|```php
    71|App\Models\User::create([
    72|    'name' => 'Admin',
    73|    'email' => 'admin@monitoring.local',
    74|    'password' => bcrypt('password123'),
    75|    'email_verified_at' => now()
    76|]);
    77|```
    78|
    79|### 4. Setup Web Server
    80|
    81|**Apache:**
    82|```bash
    83|a2enmod rewrite
    84|# Edit ports.conf: Listen 8083 (atau port lain)
    85|# Buat virtual host pointing ke /public
    86|systemctl restart apache2
    87|```
    88|
    89|**Nginx:**
    90|```bash
    91|# Buat server block pointing ke /public
    92|# FastCGI ke php-fpm
    93|systemctl restart nginx
    94|```
    95|
    96|### 5. Setup Cloudflare Tunnel (opsional, untuk domain HTTPS)
    97|
    98|```bash
    99|wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64
   100|chmod +x cloudflared-linux-amd64 && mv cloudflared-linux-amd64 /usr/local/bin/cloudflared
   101|cloudflared tunnel login
   102|cloudflared tunnel create monitoring-melon
   103|# Edit /etc/cloudflared/config.yml
   104|cloudflared tunnel route dns <TUNNEL_ID> monitoring.domain.com
   105|cloudflared service install
   106|systemctl enable --now cloudflared
   107|```
   108|
   109|## ESP32 Firmware
   110|
   111|### ESP32 Sensor (`esp32-sensor/`)
   112|
   113|1. Arduino IDE, install library: `Firebase ESP Client`, `DHT sensor library`
   114|2. Buka `esp32-sensor.ino`, edit WiFi & Firebase config
   115|3. Board: ESP32 Dev Module, Upload
   116|
   117|### ESP32 CAM (`esp32-cam/`)
   118|
   119|1. Arduino IDE
   120|2. Buka `esp32-cam.ino`, edit WiFi & Firebase config
   121|3. Board: **AI Thinker ESP32-CAM**
   122|4. Partition: **Huge APP (3MB No OTA/1MB SPIFFS)**
   123|5. Upload (tekan RESET saat upload dimulai)
   124|
   125|## Firebase Security Rules
   126|
   127|```json
   128|{
   129|  "rules": {
   130|    "sensor": { ".read": true, ".write": true },
   131|    "foto": { ".read": true, ".write": true },
   132|    "relay": { ".read": true, ".write": true }
   133|  }
   134|}
   135|```
   136|
   137|## Struktur Firebase
   138|
   139|```
   140|/sensor/latest      -> {suhu, kelembapan, soil, pompa, updated_at}
   141|/sensor/history     -> [{suhu, kelembapan, soil, pompa, created_at}, ...]
   142|/foto               -> [{image: base64, timestamp, waktu}, ...]
   143|/relay              -> -1 (auto) | 0 (OFF) | 1 (ON)
   144|```
   145|
   146|## License
   147|
   148|MIT
   149|

## Tips Hardware ESP32 CAM

### Masalah: Kamera freeze/jarak dekat

**Penyebab:** Power supply lemah + antenna PCB jelek

**Solusi wajib:**
1. Kapasitor 1000uF + 0.1uF di pin VIN & GND (dekat ESP32)
2. Power supply 5V 2A (jangan USB laptop)
3. External antenna (solder U.FL + antenna 2.4GHz, ~Rp 15rb)

**Sudah dioptimasi di firmware:**
- XCLK: 20MHz -> 10MHz (lebih stabil)
- JPEG quality: 10 -> 20 (lebih kecil, kurangi bandwidth)
- Frame buffer: 3 -> 2 (kurangi beban PSRAM)
- WiFi TX power: max 19.5dBm
- Stream delay: 50ms per frame
