#!/bin/bash
# ============================================================
# Hair Clinic Pro - Full Setup Script
# Run this in YOUR terminal:
#   bash /home/sharma/Hair-clinic-pro/setup.sh
# ============================================================

set -e  # Stop on first error

echo ""
echo "================================================"
echo "  Hair Clinic Pro - Automated Setup"
echo "================================================"
echo ""

# ── STEP 1: Update packages ─────────────────────────────
echo "▸ [1/9] Updating package list..."
sudo apt update -y

# ── STEP 2: Install MySQL ────────────────────────────────
echo ""
echo "▸ [2/9] Installing MySQL Server..."
sudo apt install -y mysql-server mysql-client

# ── STEP 3: Start MySQL ──────────────────────────────────
echo ""
echo "▸ [3/9] Starting MySQL..."
sudo systemctl start mysql
sudo systemctl enable mysql

# ── STEP 4: Set MySQL root password ─────────────────────
echo ""
echo "▸ [4/9] Configuring MySQL root user..."
sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'password'; FLUSH PRIVILEGES;"
echo "   MySQL root password set to: password"

# ── STEP 5: Create DB + import data ─────────────────────
echo ""
echo "▸ [5/9] Creating database and importing schema..."
mysql -u root -ppassword -e "CREATE DATABASE IF NOT EXISTS hair_clinic_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -ppassword hair_clinic_system < /home/sharma/Hair-clinic-pro/database.sql
echo "   Database 'hair_clinic_system' created and populated!"

# ── STEP 6: Install PHP 8.3 ─────────────────────────────
echo ""
echo "▸ [6/9] Installing PHP 8.3 and extensions..."
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update -y
sudo apt install -y \
    php8.3 php8.3-cli php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-bcmath php8.3-curl php8.3-zip \
    php8.3-tokenizer php8.3-dom php8.3-fileinfo
echo "   PHP version: $(php --version | head -1)"

# ── STEP 7: Install Composer ─────────────────────────────
echo ""
echo "▸ [7/9] Installing Composer..."
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
echo "   Composer version: $(composer --version)"

# ── STEP 8: Setup Laravel backend ───────────────────────
echo ""
echo "▸ [8/9] Setting up Laravel backend..."
cd /home/sharma/Hair-clinic-pro/backend

# Create .env from example
cp .env.example .env

# Set DB_PASSWORD in .env
sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=password/' .env

echo "   Installing PHP dependencies (this may take 2-3 minutes)..."
composer install --no-interaction --prefer-dist --optimize-autoloader

# Generate app key
php artisan key:generate --force

# Set storage permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache

echo "   Laravel backend configured!"

# ── STEP 9: Install frontend dependencies ───────────────
echo ""
echo "▸ [9/9] Installing frontend dependencies..."
cd /home/sharma/Hair-clinic-pro/frontend
npm install

echo ""
echo "================================================"
echo "  ✅ Setup Complete!"
echo "================================================"
echo ""
echo "  Now open TWO terminals and run:"
echo ""
echo "  Terminal 1 (Backend):"
echo "    cd /home/sharma/Hair-clinic-pro/backend"
echo "    php artisan serve"
echo ""
echo "  Terminal 2 (Frontend):"
echo "    cd /home/sharma/Hair-clinic-pro/frontend"
echo "    npm run dev"
echo ""
echo "  Then open:  http://127.0.0.1:5174"
echo ""
echo "  Login credentials:"
echo "    admin        / password  (Full access)"
echo "    receptionist / password"
echo "    doctor       / password"
echo "    inventory    / password"
echo "    pharmacy     / password"
echo ""
