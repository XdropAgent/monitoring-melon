# 🍈 Monitoring Tanaman Melon

Dashboard monitoring tanaman melon berbasis PHP/Laravel dengan ESP32 + Firebase Realtime Database.

## Fitur

- **Dashboard Real-time** — Suhu, kelembapan, soil moisture
- **Live Camera Stream** — ESP32 CAM MJPEG stream
- **Foto Terjadwal** — Otomatis foto jam 12:00, 16:00, 20:00
- **Grafik Time-series** — Chart.js real-time
- **Hapus Data** — Per rentang tanggal atau semua
- **Auth** — Login/register Laravel Breeze
- **Responsive** — Desktop & mobile

## Arsitektur

```
ESP32 Sensor (DHT22, Soil, Relay) --> Firebase RTDB --> Laravel Dashboard
ESP32 CAM (AI Thinker)            --> Firebase RTDB (foto)
                                  --> Live Stream (WiFi lokal)
```

## Kebutuhan

- VPS Ubuntu 22.04+ (minimal 1GB RAM)
- Domain (opsional, bisa pakai IP langsung)
- ESP32 Wroom + DHT22 + Soil Sensor + Relay
- ESP32 CAM AI Thinker
- Akun Firebase (gratis)
- WiFi lokal

## Installasi Otomatis

```bash
curl -sL https://raw.githubusercontent.com/XdropAgent/monitoring-melon/main/install.sh | bash
```

## Installasi Manual

### 1. Clone & Setup

```bash
git clone https://github.com/XdropAgent/monitoring-melon.git
cd monitoring-melon
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### 2. Edit .env

```bash
nano .env
```

Isi Firebase config:
```
FIREBASE_API_KEY=AIzaSy...dari Firebase Console
FIREBASE_DATABASE_URL=https://project-id-default-rtdb.firebaseio.com
FIREBASE_PROJECT_ID=project-id
ESP32_CAM_IP=http://IP_ESP32_CAM
```

### 3. Buat User

```bash
php artisan tinker
```
```php
App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@monitoring.local',
    'password' => bcrypt('password123'),
    'email_verified_at' => now()
]);
```

### 4. Setup Web Server

**Apache:**
```bash
a2enmod rewrite
# Edit ports.conf: Listen 8083 (atau port lain)
# Buat virtual host pointing ke /public
systemctl restart apache2
```

**Nginx:**
```bash
# Buat server block pointing ke /public
# FastCGI ke php-fpm
systemctl restart nginx
```

### 5. Setup Cloudflare Tunnel (opsional, untuk domain HTTPS)

```bash
wget https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64
chmod +x cloudflared-linux-amd64 && mv cloudflared-linux-amd64 /usr/local/bin/cloudflared
cloudflared tunnel login
cloudflared tunnel create monitoring-melon
# Edit /etc/cloudflared/config.yml
cloudflared tunnel route dns <TUNNEL_ID> monitoring.domain.com
cloudflared service install
systemctl enable --now cloudflared
```

## ESP32 Firmware

### ESP32 Sensor (`esp32-sensor/`)

1. Arduino IDE, install library: `Firebase ESP Client`, `DHT sensor library`
2. Buka `esp32-sensor.ino`, edit WiFi & Firebase config
3. Board: ESP32 Dev Module, Upload

### ESP32 CAM (`esp32-cam/`)

1. Arduino IDE
2. Buka `esp32-cam.ino`, edit WiFi & Firebase config
3. Board: **AI Thinker ESP32-CAM**
4. Partition: **Huge APP (3MB No OTA/1MB SPIFFS)**
5. Upload (tekan RESET saat upload dimulai)

## Firebase Security Rules

```json
{
  "rules": {
    "sensor": { ".read": true, ".write": true },
    "foto": { ".read": true, ".write": true },
    "relay": { ".read": true, ".write": true }
  }
}
```

## Struktur Firebase

```
/sensor/latest      -> {suhu, kelembapan, soil, pompa, updated_at}
/sensor/history     -> [{suhu, kelembapan, soil, pompa, created_at}, ...]
/foto               -> [{image: base64, timestamp, waktu}, ...]
/relay              -> -1 (auto) | 0 (OFF) | 1 (ON)
```

## License

MIT
