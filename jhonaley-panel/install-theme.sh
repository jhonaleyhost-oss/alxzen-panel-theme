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
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-}"

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

read_env_value() {
    local key="$1" file="$PANEL_DIR/.env"
    [ -f "$file" ] || return 0
    grep -E "^${key}=" "$file" | tail -n1 | cut -d= -f2- | tr -d '"' | tr -d "'"
}

if [ -f "$PANEL_DIR/.env" ]; then
    DB_HOST="$(read_env_value DB_HOST || true)"; DB_HOST="${DB_HOST:-127.0.0.1}"
    DB_PORT="$(read_env_value DB_PORT || true)"; DB_PORT="${DB_PORT:-3306}"
    DB_NAME="$(read_env_value DB_DATABASE || true)"; DB_NAME="${DB_NAME:-panel}"
    DB_USER="$(read_env_value DB_USERNAME || true)"; DB_USER="${DB_USER:-pterodactyl}"
    DB_PASS="$(read_env_value DB_PASSWORD || true)"
fi

if [ -z "$PHP_FPM_SERVICE" ]; then
    PHP_MINOR="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
    if [ -n "$PHP_MINOR" ] && systemctl list-unit-files "php${PHP_MINOR}-fpm.service" >/dev/null 2>&1; then
        PHP_FPM_SERVICE="php${PHP_MINOR}-fpm"
    else
        PHP_FPM_SERVICE="$(systemctl list-unit-files 'php*-fpm.service' 2>/dev/null | awk '/php[0-9].*-fpm\.service/ {sub(/\.service/,"",$1); print $1; exit}')"
        PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
    fi
fi
ok "Database: $DB_USER@$DB_HOST:$DB_PORT/$DB_NAME"
ok "PHP-FPM: $PHP_FPM_SERVICE"

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
    if [ -n "$DB_PASS" ]; then
        mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" 2>/dev/null | gzip > "$BK_DB" \
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
copy_if_exists "$THEME_DIR/package.json"          "$PANEL_DIR/package.json"
copy_if_exists "$THEME_DIR/yarn.lock"             "$PANEL_DIR/yarn.lock"
copy_if_exists "$THEME_DIR/tsconfig.json"         "$PANEL_DIR/tsconfig.json"

# Assets
info "Assets (logo + favicon)..."
copy_if_exists "$THEME_DIR/public/assets"   "$PANEL_DIR/public/"
copy_if_exists "$THEME_DIR/public/favicons" "$PANEL_DIR/public/"
copy_if_exists "$THEME_DIR/public/favicon.ico" "$PANEL_DIR/public/favicon.ico"
copy_if_exists "$THEME_DIR/public/favicon.png" "$PANEL_DIR/public/favicon.png"

# Branding config
info "Branding (app name + author)..."
copy_if_exists "$THEME_DIR/config/app.php" "$PANEL_DIR/config/app.php"

# Custom features (Announcement, Expiration, Mount, Location, Nest, Node, dll)
# PENTING: overlay SELURUH app/ + routes/ — theme ini fork Pterodactyl,
# jadi Models / Requests / Services / Transformers / Repositories yang
# direferensikan controller & routes HARUS ikut ter-copy. Kalau tidak,
# muncul "Class Pterodactyl\Models\Announcement not found" dan 500 error
# di halaman admin (nodes, location, mount, nest, announcement, dll).
info "Backend overlay: seluruh app/ + routes/..."
copy_if_exists "$THEME_DIR/app"    "$PANEL_DIR/"
copy_if_exists "$THEME_DIR/routes" "$PANEL_DIR/"
# Bersihkan file .bak yang ikut ter-copy
find "$PANEL_DIR/app" -name "*.bak" -delete 2>/dev/null || true

# Migrations — copy semua migration dari theme yang belum ada di panel.
# Laravel aman: yang sudah pernah dijalankan (tercatat di tabel migrations)
# akan di-skip otomatis pas `php artisan migrate`.
info "Migrations (announcements, expiration, dll)..."
MIG_COUNT=0
if [ -d "$THEME_DIR/database/migrations" ]; then
    for m in "$THEME_DIR/database/migrations/"*.php; do
        [ -e "$m" ] || continue
        dst="$PANEL_DIR/database/migrations/$(basename "$m")"
        case "$(basename "$m")" in
            2026_02_18_020322_add_expires_at_to_servers_table.php|2026_06_03_000000_create_announcements_table.php)
                cp -f "$m" "$dst"
                MIG_COUNT=$((MIG_COUNT+1))
                ;;
            *)
        if [ ! -f "$dst" ]; then
            cp -f "$m" "$dst"
            MIG_COUNT=$((MIG_COUNT+1))
        fi
                ;;
        esac
    done
fi
ok "→ $MIG_COUNT migration baru disalin"

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

php artisan optimize:clear >/dev/null 2>&1 || true

mysql_query() {
    [ -n "$DB_PASS" ] || return 1
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" "$@"
}

column_exists() {
    local table="$1" column="$2"
    mysql_query -N -B -e "SHOW COLUMNS FROM \`$table\` LIKE '$column';" 2>/dev/null | grep -q .
}

table_exists() {
    local table="$1"
    mysql_query -N -B -e "SHOW TABLES LIKE '$table';" 2>/dev/null | grep -q .
}

add_column_if_missing() {
    local table="$1" column="$2" ddl="$3"
    if table_exists "$table" && ! column_exists "$table" "$column"; then
        mysql_query -e "ALTER TABLE \`$table\` ADD COLUMN $ddl;" >/dev/null 2>&1 \
            && ok "Kolom $table.$column ditambahkan" \
            || warn "Gagal menambah kolom $table.$column — lanjut, migration Laravel akan coba lagi."
    fi
}

mark_migration_done() {
    local migration="$1"
    mysql_query -e "CREATE TABLE IF NOT EXISTS migrations (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL, batch INT NOT NULL);" >/dev/null 2>&1 || true
    local batch
    batch="$(mysql_query -N -B -e "SELECT COALESCE(MAX(batch),0)+1 FROM migrations;" 2>/dev/null || echo 1)"
    mysql_query -e "INSERT INTO migrations (migration, batch) SELECT '$migration', ${batch:-1} WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration='$migration');" >/dev/null 2>&1 || true
}

step "STEP 4A  Repair schema sebelum migrate"
if command -v mysql >/dev/null && mysql_query -e "SELECT 1;" >/dev/null 2>&1; then
    if table_exists servers && column_exists servers expires_at; then
        mark_migration_done "2026_02_18_020322_add_expires_at_to_servers_table"
        ok "Migration expires_at ditandai aman."
    fi

    if table_exists announcements; then
        add_column_if_missing announcements title "title VARCHAR(255) NOT NULL DEFAULT 'Announcement' AFTER id"
        add_column_if_missing announcements content "content TEXT NULL AFTER title"
        add_column_if_missing announcements type "type VARCHAR(20) NOT NULL DEFAULT 'info' AFTER content"
        add_column_if_missing announcements priority "priority TINYINT NOT NULL DEFAULT 2 AFTER type"
        add_column_if_missing announcements is_active "is_active TINYINT(1) NOT NULL DEFAULT 0 AFTER priority"
        add_column_if_missing announcements target_display "target_display JSON NULL AFTER is_active"
        add_column_if_missing announcements expires_at "expires_at TIMESTAMP NULL AFTER target_display"
        add_column_if_missing announcements created_by "created_by INT UNSIGNED NULL AFTER expires_at"
        add_column_if_missing announcements created_at "created_at TIMESTAMP NULL AFTER created_by"
        add_column_if_missing announcements updated_at "updated_at TIMESTAMP NULL AFTER created_at"
        mysql_query -e "CREATE TABLE IF NOT EXISTS announcement_reads (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, announcement_id BIGINT UNSIGNED NOT NULL, read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY announcement_reads_user_announcement_unique (user_id, announcement_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >/dev/null 2>&1 || warn "Gagal memastikan tabel announcement_reads."
        ok "Schema announcements/announcement_reads aman."
    fi
else
    warn "MySQL tidak bisa dicek otomatis — lanjut ke php artisan migrate."
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
YARN_LOG="$BACKUP_DIR/yarn-install-$TS.log"
info "Log live juga disimpan di $YARN_LOG"
set +e
timeout 30m yarn install --network-timeout 600000 --frozen-lockfile 2>&1 | tee "$YARN_LOG"
YARN_INSTALL_EXIT=${PIPESTATUS[0]}
set -e
[ $YARN_INSTALL_EXIT -eq 0 ] || err "yarn install gagal/timeout (exit $YARN_INSTALL_EXIT). Cek log: $YARN_LOG. Restore: tar -xzf $BK_FILES -C /var/www/"

info "Building production bundle (3-8 menit)..."
BUILD_LOG="$BACKUP_DIR/yarn-build-$TS.log"
info "Log live juga disimpan di $BUILD_LOG"
set +e
timeout 30m env NODE_OPTIONS="--max-old-space-size=2048" yarn build:production 2>&1 | tee "$BUILD_LOG"
BUILD_EXIT=${PIPESTATUS[0]}
set -e
if [ $BUILD_EXIT -eq 0 ]; then
    ok "Build sukses."
else
    err "Build gagal/timeout (exit $BUILD_EXIT). Cek log: $BUILD_LOG. Restore: cd /var/www && rm -rf pterodactyl && tar -xzf $BK_FILES"
fi

[ -f "$PANEL_DIR/public/assets/manifest.json" ] || err "Build selesai tapi public/assets/manifest.json tidak ada — dashboard/login tidak akan load tema. Cek log: $BUILD_LOG"
ok "Manifest frontend tersedia."

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

# ─── Step 6.5: Announcement banner (safe, no model dependency) ───────────────
step "STEP 6.5  Setup announcement banner (safe mode)"
cd "$PANEL_DIR"

DB_HOST_ENV="${DB_HOST:-127.0.0.1}"
DB_PORT_ENV="${DB_PORT:-3306}"
DB_NAME_ENV="${DB_NAME:-panel}"
DB_USER_ENV="${DB_USER:-pterodactyl}"
DB_PASS_ENV="${DB_PASS:-}"

MYSQL_CMD=(mysql -h "$DB_HOST_ENV" -P "$DB_PORT_ENV" -u "$DB_USER_ENV")
[ -n "$DB_PASS_ENV" ] && MYSQL_CMD+=(-p"$DB_PASS_ENV")
MYSQL_CMD+=("$DB_NAME_ENV")

HAS_TABLE=$("${MYSQL_CMD[@]}" -N -B -e "SHOW TABLES LIKE 'announcements';" 2>/dev/null | wc -l)
if [ "${HAS_TABLE:-0}" -eq 0 ]; then
    warn "Tabel 'announcements' belum ada. Banner di-skip (tidak akan menyebabkan 500)."
else
    HAS_TYPE=$("${MYSQL_CMD[@]}" -N -B -e "SHOW COLUMNS FROM announcements LIKE 'type';" 2>/dev/null | wc -l)
    if [ "${HAS_TYPE:-0}" -eq 0 ]; then
        "${MYSQL_CMD[@]}" -e "ALTER TABLE announcements ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'info' AFTER content;" 2>/dev/null \
            && ok "Kolom 'type' ditambahkan ke tabel announcements." \
            || warn "Gagal ALTER announcements — banner tetap dipasang (tanpa warna type)."
    fi

    PARTIAL_DIR="$PANEL_DIR/resources/views/partials"
    PARTIAL_FILE="$PARTIAL_DIR/announcements.blade.php"
    mkdir -p "$PARTIAL_DIR"
    # Partial pakai DB facade langsung — TIDAK butuh Model Announcement.
    # Semua akses dibungkus try/catch → apapun errornya, tidak akan 500.
    PARTIAL_B64='QHBocAogICAgdHJ5IHsKICAgICAgICAkX19hbm4gPSBcSWxsdW1pbmF0ZVxTdXBwb3J0XEZhY2FkZXNcREI6OnRhYmxlKCdhbm5vdW5jZW1lbnRzJykKICAgICAgICAgICAgLT53aGVyZShmdW5jdGlvbigkcSkgewogICAgICAgICAgICAgICAgaWYgKFxJbGx1bWluYXRlXFN1cHBvcnRcRmFjYWRlc1xTY2hlbWE6Omhhc0NvbHVtbignYW5ub3VuY2VtZW50cycsICdhY3RpdmUnKSkgewogICAgICAgICAgICAgICAgICAgICRxLT53aGVyZSgnYWN0aXZlJywgMSk7CiAgICAgICAgICAgICAgICB9CiAgICAgICAgICAgIH0pCiAgICAgICAgICAgIC0+b3JkZXJCeURlc2MoJ2NyZWF0ZWRfYXQnKQogICAgICAgICAgICAtPmxpbWl0KDUpCiAgICAgICAgICAgIC0+Z2V0KCk7CiAgICB9IGNhdGNoIChcVGhyb3dhYmxlICRlKSB7CiAgICAgICAgJF9fYW5uID0gY29sbGVjdCgpOwogICAgfQpAZW5kcGhwCkBpZigkX19hbm4gJiYgJF9fYW5uLT5jb3VudCgpKQo8ZGl2IHN0eWxlPSJwb3NpdGlvbjpzdGlja3k7dG9wOjA7ei1pbmRleDo5OTk5OyI+CkBmb3JlYWNoKCRfX2FubiBhcyAkYSkKICAgIEBwaHAKICAgICAgICAkdHlwZSA9ICRhLT50eXBlID8/ICdpbmZvJzsKICAgICAgICAkYmcgPSAkdHlwZSA9PT0gJ2NyaXRpY2FsJyA/ICcjYzAzOTJiJyA6ICgkdHlwZSA9PT0gJ3dhcm5pbmcnID8gJyNkMzU0MDAnIDogJyMyOTgwYjknKTsKICAgIEBlbmRwaHAKICAgIDxkaXYgc3R5bGU9ImJhY2tncm91bmQ6e3sgJGJnIH19O2NvbG9yOiNmZmY7cGFkZGluZzo4cHggMTZweDtmb250LXNpemU6MTRweDsiPgogICAgICAgIDxzdHJvbmc+e3sgJGEtPnRpdGxlIH19PC9zdHJvbmc+CiAgICAgICAgPHNwYW4gc3R5bGU9Im9wYWNpdHk6Ljk7bWFyZ2luLWxlZnQ6OHB4OyI+e3sgJGEtPmNvbnRlbnQgfX08L3NwYW4+CiAgICA8L2Rpdj4KQGVuZGZvcmVhY2gKPC9kaXY+CkBlbmRpZgo='
    echo "$PARTIAL_B64" | base64 -d > "$PARTIAL_FILE"
    ok "Partial ditulis: resources/views/partials/announcements.blade.php"

    WRAPPER="$PANEL_DIR/resources/views/templates/wrapper.blade.php"
    if [ -f "$WRAPPER" ]; then
        if grep -q "partials.announcements" "$WRAPPER"; then
            ok "Wrapper sudah include partials.announcements — skip."
        else
            cp -f "$WRAPPER" "$WRAPPER.bak-ann-$TS"
            sed -i "0,/<div id=\"app\"/s//@include('partials.announcements')\\n        <div id=\"app\"/" "$WRAPPER" \
                && ok "Wrapper di-inject @include('partials.announcements')." \
                || warn "Gagal inject wrapper — cek manual: $WRAPPER"
        fi
        chown www-data:www-data "$WRAPPER" "$PARTIAL_FILE" 2>/dev/null || true
    else
        warn "wrapper.blade.php tidak ditemukan — banner tidak akan muncul."
    fi

    php artisan view:clear    >/dev/null 2>&1 || true
    php artisan optimize:clear >/dev/null 2>&1 || true
    ok "Announcement banner siap (aman, tidak akan 500)."
fi

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