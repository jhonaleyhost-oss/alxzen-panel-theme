#!/bin/bash
# ==============================================================================
# Jhonaley Store Theme Installer for Pterodactyl Panel
# Auto: backup → overlay theme → migrate → rebuild → restart
# Usage:  sudo bash install-theme.sh
# ==============================================================================
set -e

# ─── Config (sesuaikan kalau path beda) ──────────────────────────────────────
PANEL_DIR="${PANEL_DIR:-/var/www/pterodactyl}"
THEME_DIR="${THEME_DIR:-$(cd "$(dirname "$0")" && pwd)}"
BACKUP_DIR="${BACKUP_DIR:-/root/pterodactyl-backups}"
DB_NAME="${DB_NAME:-panel}"
DB_USER="${DB_USER:-pterodactyl}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.1-fpm}"

# ─── Colors ──────────────────────────────────────────────────────────────────
R='\033[0;31m'; G='\033[0;32m'; Y='\033[1;33m'; B='\033[0;34m'; C='\033[0;36m'; N='\033[0m'
ok()   { echo -e "  ${G}✓${N} $1"; }
info() { echo -e "  ${B}ℹ${N} $1"; }
warn() { echo -e "  ${Y}⚠${N} $1"; }
err()  { echo -e "  ${R}✗ ERROR:${N} $1" >&2; exit 1; }
step() { echo; echo -e "${C}▶ $1${N}"; echo -e "  $(printf '%.0s─' {1..60})"; }

# ─── Banner ──────────────────────────────────────────────────────────────────
clear
echo -e "${C}"
cat <<'EOF'
  ╔══════════════════════════════════════════════════════════╗
  ║      Jhonaley Store — Pterodactyl Theme Installer        ║
  ║       Backup • Overlay • Migrate • Build • Restart       ║
  ╚══════════════════════════════════════════════════════════╝
EOF
echo -e "${N}"

# ─── Pre-flight checks ───────────────────────────────────────────────────────
step "STEP 0  Pre-flight check"
[ "$EUID" -eq 0 ] || err "Harus dijalankan sebagai root (pakai sudo)."
[ -d "$PANEL_DIR" ] || err "Panel tidak ditemukan di $PANEL_DIR. Set PANEL_DIR=/path/anda."
[ -f "$PANEL_DIR/artisan" ] || err "$PANEL_DIR bukan Pterodactyl panel (artisan tidak ada)."
[ -d "$THEME_DIR/resources/scripts" ] || err "Theme source tidak ditemukan di $THEME_DIR."
command -v php  >/dev/null || err "PHP tidak terinstall."
command -v yarn >/dev/null || warn "Yarn belum terinstall — akan dipasang otomatis saat Step 5."
command -v mysqldump >/dev/null || warn "mysqldump tidak ada — DB backup di-skip."
ok "Panel: $PANEL_DIR"
ok "Theme: $THEME_DIR"

echo
read -rp "  Lanjut install tema? Backup otomatis akan dibuat. [y/N] " CONFIRM
[[ "$CONFIRM" =~ ^[Yy]$ ]] || { warn "Dibatalkan."; exit 0; }

# ─── Step 1: Backup ──────────────────────────────────────────────────────────
step "STEP 1  Backup"
mkdir -p "$BACKUP_DIR"
TS=$(date +%Y%m%d-%H%M%S)
BK_FILES="$BACKUP_DIR/panel-files-$TS.tar.gz"
BK_DB="$BACKUP_DIR/panel-db-$TS.sql.gz"

info "Backup file panel → $BK_FILES"
set +e
tar --warning=no-file-changed --warning=no-file-removed \
    --exclude="$(basename "$PANEL_DIR")/node_modules" \
    --exclude="$(basename "$PANEL_DIR")/vendor" \
    --exclude="$(basename "$PANEL_DIR")/storage/logs" \
    --exclude="$(basename "$PANEL_DIR")/storage/framework/cache" \
    -czf "$BK_FILES" -C "$(dirname "$PANEL_DIR")" "$(basename "$PANEL_DIR")"
TAR_EXIT=$?
set -e
# tar exit 1 = file berubah saat dibaca (normal untuk panel running), masih valid
if [ $TAR_EXIT -eq 0 ] || [ $TAR_EXIT -eq 1 ]; then
    ok "File backup: $(du -h "$BK_FILES" | cut -f1)"
else
    err "Backup gagal (tar exit $TAR_EXIT). Cek disk space: df -h"
fi

if command -v mysqldump >/dev/null; then
    info "Backup database '$DB_NAME' → $BK_DB"
    if [ -f "$PANEL_DIR/.env" ]; then
        DB_PASS=$(grep -E '^DB_PASSWORD=' "$PANEL_DIR/.env" | cut -d= -f2- | tr -d '"' | tr -d "'")
    fi
    if [ -n "$DB_PASS" ]; then
        mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" 2>/dev/null | gzip > "$BK_DB" \
            && ok "DB backup: $(du -h "$BK_DB" | cut -f1)" \
            || warn "DB backup gagal — lanjut tanpa DB backup."
    else
        warn "DB password tidak terbaca dari .env — DB backup di-skip."
    fi
fi

# ─── Step 2: Maintenance mode ────────────────────────────────────────────────
step "STEP 2  Maintenance mode"
cd "$PANEL_DIR"
php artisan down --retry=60 >/dev/null 2>&1 || php artisan down >/dev/null 2>&1 || true
systemctl stop pteroq.service 2>/dev/null || true
ok "Panel dalam maintenance mode."

# ─── Step 3: Overlay theme files ─────────────────────────────────────────────
step "STEP 3  Overlay theme files"

copy_if_exists() {
    local src="$1" dst="$2"
    if [ -e "$src" ]; then
        mkdir -p "$(dirname "$dst")"
        cp -rf "$src" "$dst"
        ok "→ ${dst#$PANEL_DIR/}"
    fi
}

# Frontend
info "Frontend (React + Blade + Tailwind)..."
copy_if_exists "$THEME_DIR/resources/scripts"     "$PANEL_DIR/resources/"
copy_if_exists "$THEME_DIR/resources/views"       "$PANEL_DIR/resources/"
copy_if_exists "$THEME_DIR/resources/lang"        "$PANEL_DIR/resources/"
copy_if_exists "$THEME_DIR/tailwind.config.js"    "$PANEL_DIR/tailwind.config.js"
copy_if_exists "$THEME_DIR/babel.config.js"       "$PANEL_DIR/babel.config.js"
copy_if_exists "$THEME_DIR/webpack.config.js"     "$PANEL_DIR/webpack.config.js"
copy_if_exists "$THEME_DIR/postcss.config.js"     "$PANEL_DIR/postcss.config.js"

# Assets
info "Assets (logo + favicon)..."
copy_if_exists "$THEME_DIR/public/assets"   "$PANEL_DIR/public/"
copy_if_exists "$THEME_DIR/public/favicons" "$PANEL_DIR/public/"
copy_if_exists "$THEME_DIR/public/favicon.ico" "$PANEL_DIR/public/favicon.ico"
copy_if_exists "$THEME_DIR/public/favicon.png" "$PANEL_DIR/public/favicon.png"

# Branding config
info "Branding (app name + author)..."
copy_if_exists "$THEME_DIR/config/app.php" "$PANEL_DIR/config/app.php"

# Custom features (Expiration + Root Protection + Branded version service)
info "Backend controllers + services + routes..."
# Copy SELURUH app/ supaya semua controller yang direferensikan routes/admin.php tersedia
# (NodeSystemUsageController, ExpirationController, dll)
copy_if_exists "$THEME_DIR/app/Http/Controllers/Admin" "$PANEL_DIR/app/Http/Controllers/"
copy_if_exists "$THEME_DIR/app/Console/Commands" "$PANEL_DIR/app/Console/"
copy_if_exists "$THEME_DIR/app/Services/Helpers/SoftwareVersionService.php" \
               "$PANEL_DIR/app/Services/Helpers/SoftwareVersionService.php"
copy_if_exists "$THEME_DIR/routes/admin.php" "$PANEL_DIR/routes/admin.php"
# Bersihkan file .bak yg ikut ke-copy
find "$PANEL_DIR/app/Http/Controllers/Admin" -name "*.bak" -delete 2>/dev/null || true

# Migrations untuk fitur Expiration
info "Migrations..."
if ls "$THEME_DIR/database/migrations/"*expiration* >/dev/null 2>&1; then
    cp -f "$THEME_DIR/database/migrations/"*expiration* "$PANEL_DIR/database/migrations/"
    ok "→ migrations/*expiration*"
fi

# ─── Step 4: DB migration ────────────────────────────────────────────────────
step "STEP 4  Database migration"
cd "$PANEL_DIR"

# Pastikan composer dependencies ada (vendor/autoload.php)
if [ ! -f "$PANEL_DIR/vendor/autoload.php" ]; then
    warn "vendor/autoload.php tidak ada. Jalankan composer install..."
    command -v composer >/dev/null || err "Composer tidak terinstall. Install dulu: curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer"
    set +e
    sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -20
    COMPOSER_EXIT=${PIPESTATUS[0]}
    if [ $COMPOSER_EXIT -ne 0 ]; then
        # coba sebagai root kalau gagal sebagai www-data
        composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -20
        COMPOSER_EXIT=${PIPESTATUS[0]}
    fi
    set -e
    [ $COMPOSER_EXIT -eq 0 ] && [ -f "$PANEL_DIR/vendor/autoload.php" ] \
        && ok "Composer dependencies terinstall." \
        || err "composer install gagal. Coba manual: cd $PANEL_DIR && composer install --no-dev"
fi

set +e
php artisan migrate --force 2>&1 | tail -10
MIG_EXIT=${PIPESTATUS[0]}
set -e
[ $MIG_EXIT -eq 0 ] && ok "Migration selesai." || err "Migration gagal (exit $MIG_EXIT). Restore: cd /var/www && rm -rf pterodactyl && tar -xzf $BK_FILES"

# ─── Step 5: Frontend rebuild ────────────────────────────────────────────────
step "STEP 5  Rebuild frontend (yarn build:production)"
warn "Step ini butuh 3-8 menit. RAM minimal 2GB."
cd "$PANEL_DIR"

# ─── Pastikan Node.js versi cukup (theme butuh Node >= 22) ───────────────────
NODE_RAW=$(node -v 2>/dev/null || echo "")
NODE_VER=$(echo "$NODE_RAW" | sed 's/v//' | cut -d. -f1)
[ -z "$NODE_VER" ] && NODE_VER=0
if [ "${NODE_VER:-0}" -lt 22 ]; then
    if [ -z "$NODE_RAW" ]; then
        warn "Node.js belum terinstall. Install Node.js 22 LTS..."
    else
        warn "Node.js versi terlalu lama ($NODE_RAW). Install Node.js 22 LTS..."
    fi
    set +e
    # Tunggu apt lock (unattended-upgrades sering jalan di background)
    info "Menunggu apt lock bebas (max 180 detik)..."
    for i in $(seq 1 60); do
        if ! fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 \
           && ! fuser /var/lib/dpkg/lock >/dev/null 2>&1 \
           && ! fuser /var/lib/apt/lists/lock >/dev/null 2>&1; then
            break
        fi
        sleep 3
    done
    # Hentikan unattended-upgrades sementara supaya tidak balik kunci
    systemctl stop unattended-upgrades >/dev/null 2>&1 || true
    systemctl stop apt-daily.service apt-daily-upgrade.service >/dev/null 2>&1 || true
    killall -9 unattended-upgr >/dev/null 2>&1 || true
    # Ubuntu/Debian Node.js 12 sering konflik dengan NodeSource Node.js 22
    # (contoh: libnode-dev memiliki /usr/include/node/common.gypi).
    # Bersihkan paket Node lama dulu supaya dpkg tidak gagal overwrite.
    DEBIAN_FRONTEND=noninteractive apt-get remove -y libnode-dev nodejs npm nodejs-doc >/dev/null 2>&1
    dpkg --configure -a >/dev/null 2>&1
    DEBIAN_FRONTEND=noninteractive apt-get -f install -y >/dev/null 2>&1
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    DEBIAN_FRONTEND=noninteractive apt-get install -y nodejs
    NODE_APT_EXIT=$?
    hash -r
    if [ $NODE_APT_EXIT -eq 0 ] && command -v npm >/dev/null; then
        npm install -g yarn
        YARN_GLOBAL_EXIT=$?
    else
        YARN_GLOBAL_EXIT=1
    fi
    set -e
    NEW_NODE=$(node -v 2>/dev/null || echo "none")
    NEW_VER=$(echo "$NEW_NODE" | sed 's/v//' | cut -d. -f1)
    if [ "${NEW_VER:-0}" -lt 22 ]; then
        err "Install Node.js 22 gagal (current: $NEW_NODE). Jalankan manual: sudo apt-get remove -y libnode-dev nodejs npm nodejs-doc && curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash - && sudo apt-get install -y nodejs"
    fi
    if [ $YARN_GLOBAL_EXIT -ne 0 ] || ! command -v yarn >/dev/null; then
        err "Node.js $NEW_NODE terinstall, tapi install Yarn gagal. Jalankan manual: sudo npm install -g yarn"
    fi
    ok "Node.js $NEW_NODE terinstall."
fi

if ! command -v yarn >/dev/null; then
    info "Yarn belum ada, install via npm..."
    npm install -g yarn >/dev/null 2>&1 || err "Install Yarn gagal. Jalankan manual: sudo npm install -g yarn"
fi

# Pastikan node_modules ada
info "Install yarn dependencies..."
set +e
yarn install 2>&1 | tail -20
YARN_INSTALL_EXIT=${PIPESTATUS[0]}
set -e
[ $YARN_INSTALL_EXIT -eq 0 ] || err "yarn install gagal (exit $YARN_INSTALL_EXIT). Cek log di atas. Restore: tar -xzf $BK_FILES -C /var/www/"

info "Building production bundle (3-8 menit)..."
set +e
NODE_OPTIONS="--max-old-space-size=2048" yarn build:production 2>&1 | tail -30
BUILD_EXIT=${PIPESTATUS[0]}
set -e
if [ $BUILD_EXIT -eq 0 ]; then
    ok "Build sukses."
else
    err "Build gagal (exit $BUILD_EXIT). Restore: cd /var/www && rm -rf pterodactyl && tar -xzf $BK_FILES"
fi

# ─── Step 6: Clear cache + permission ────────────────────────────────────────
step "STEP 6  Clear cache + permission"
cd "$PANEL_DIR"
php artisan view:clear   >/dev/null && ok "view cache cleared"
php artisan config:clear >/dev/null && ok "config cache cleared"
php artisan cache:clear  >/dev/null && ok "app cache cleared"
php artisan route:clear  >/dev/null && ok "route cache cleared"
php artisan optimize     >/dev/null && ok "optimized"

chown -R www-data:www-data "$PANEL_DIR"
chmod -R 755 "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache"
ok "Permission set ke www-data."

# ─── Step 7: Restart services + maintenance off ──────────────────────────────
step "STEP 7  Restart services"
systemctl start pteroq.service       2>/dev/null && ok "pteroq.service started"  || warn "pteroq.service skip"
systemctl restart "$PHP_FPM_SERVICE" 2>/dev/null && ok "$PHP_FPM_SERVICE restarted" || warn "$PHP_FPM_SERVICE skip"
systemctl reload nginx               2>/dev/null && ok "nginx reloaded"          || warn "nginx skip"

php artisan up
ok "Panel kembali online."

# ─── Done ────────────────────────────────────────────────────────────────────
echo
echo -e "${G}╔══════════════════════════════════════════════════════════╗${N}"
echo -e "${G}║       ✓ INSTALASI TEMA JHONALEY STORE SELESAI            ║${N}"
echo -e "${G}╚══════════════════════════════════════════════════════════╝${N}"
echo
echo -e "  ${C}Backup tersimpan:${N}"
echo -e "    • $BK_FILES"
[ -f "$BK_DB" ] && echo -e "    • $BK_DB"
echo
echo -e "  ${C}Buka panel di browser → hard refresh (Ctrl+Shift+R):${N}"
APP_URL=$(grep -E '^APP_URL=' "$PANEL_DIR/.env" 2>/dev/null | cut -d= -f2- | tr -d '"')
echo -e "    ${B}${APP_URL:-https://panel.domain.kamu}${N}"
echo
echo -e "  ${Y}Kalau ada masalah, restore dengan:${N}"
echo -e "    cd /var/www && rm -rf pterodactyl && tar -xzf $BK_FILES"
[ -f "$BK_DB" ] && echo -e "    gunzip < $BK_DB | mysql -u $DB_USER -p $DB_NAME"
echo