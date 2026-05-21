#!/bin/bash
set -e

echo "🍈 Monitoring Tanaman Melon - Auto Install"
echo "=========================================="

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Check root
if [ "$EUID" -ne 0 ]; then
    echo "❌ Jalankan sebagai root: sudo bash install.sh"
    exit 1
fi

# Detect OS
if [ -f /etc/debian_version ]; then
    PKG_MANAGER="apt"
elif [ -f /etc/redhat-release ]; then
    PKG_MANAGER="yum"
else
    echo "❌ OS tidak didukung. Gunakan Ubuntu/Debian."
    exit 1
fi

echo -e "${YELLOW}[1/7] Install dependencies...${NC}"
apt update -qq
apt install -y php php-fpm php-mbstring php-xml php-curl php-zip php-sqlite3 php-mysql apache2 composer git curl unzip

# Enable mod_rewrite
a2enmod rewrite 2>/dev/null || true

echo -e "${YELLOW}[2/7] Clone project...${NC}"
INSTALL_DIR="/var/www/monitoring-melon"
if [ -d "$INSTALL_DIR" ]; then
    echo "⚠️  Directory exists, backing up..."
    mv "$INSTALL_DIR" "${INSTALL_DIR}.bak.$(date +%s)"
fi

git clone https://github.com/XdropAgent/monitoring-melon.git "$INSTALL_DIR"
cd "$INSTALL_DIR"

echo -e "${YELLOW}[3/7] Install PHP dependencies...${NC}"
composer install --no-dev --optimize-autoloader

echo -e "${YELLOW}[4/7] Setup Laravel...${NC}"
cp .env.example .env
php artisan key:generate

# Prompt Firebase config
echo ""
echo -e "${GREEN}Masukkan Firebase config:${NC}"
read -p "Firebase API Key: " FIREBASE_API_KEY
read -p "Firebase Database URL: " FIREBASE_DATABASE_URL
read -p "Firebase Project ID: " FIREBASE_PROJECT_ID
read -p "ESP32 CAM IP (contoh: http://192.168.1.100): " ESP32_CAM_IP

# Write to .env
cat >> .env << EOF

FIREBASE_API_KEY=$FIREBASE_API_KEY
FIREBASE_DATABASE_URL=$FIREBASE_DATABASE_URL
FIREBASE_PROJECT_ID=$FIREBASE_PROJECT_ID
ESP32_CAM_IP=$ESP32_CAM_IP
EOF

echo -e "${YELLOW}[5/7] Setup database...${NC}"
touch database/database.sqlite
php artisan migrate --force

# Create admin user
echo ""
echo -e "${GREEN}Buat akun admin:${NC}"
read -p "Nama: " ADMIN_NAME
read -p "Email: " ADMIN_EMAIL
read -s -p "Password: " ADMIN_PASS
echo ""

php artisan tinker --execute="
\App\Models\User::create([
    'name' => '$ADMIN_NAME',
    'email' => '$ADMIN_EMAIL',
    'password' => bcrypt('$ADMIN_PASS'),
    'email_verified_at' => now()
]);
"

echo -e "${YELLOW}[6/7] Setup Apache...${NC}"
# Detect available port
WEB_PORT=80
if ss -tlnp | grep -q ":80 "; then
    WEB_PORT=8083
    echo "⚠️  Port 80 digunakan, pakai port $WEB_PORT"
    sed -i "s/Listen 80/Listen $WEB_PORT/" /etc/apache2/ports.conf 2>/dev/null || true
fi

cat > /etc/apache2/sites-available/monitoring-melon.conf << EOF
<VirtualHost *:$WEB_PORT>
    ServerName _
    DocumentRoot $INSTALL_DIR/public
    <Directory $INSTALL_DIR/public>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/monitoring-melon-error.log
    CustomLog \${APACHE_LOG_DIR}/monitoring-melon-access.log combined
</VirtualHost>
EOF

a2ensite monitoring-melon 2>/dev/null || true
a2dissite 000-default 2>/dev/null || true
systemctl restart apache2

# Set permissions
chown -R www-data:www-data "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"
chmod -R 775 "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"

echo -e "${YELLOW}[7/7] Done!${NC}"
echo ""
echo "=========================================="
echo -e "${GREEN}✅ Installasi selesai!${NC}"
echo ""
echo "🌐 Akses dashboard:"
PUBLIC_IP=$(curl -s ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')
echo "   http://$PUBLIC_IP:$WEB_PORT"
echo ""
echo "🔐 Login:"
echo "   Email: $ADMIN_EMAIL"
echo "   Password: (yang tadi diinput)"
echo ""
echo "📡 ESP32 CAM stream:"
echo "   http://$ESP32_CAM_IP"
echo ""
echo "☁️  Cloudflare Tunnel (opsional):"
echo "   Jalankan: cloudflared tunnel login"
echo "   Lalu ikuti panduan di README.md"
echo "=========================================="
