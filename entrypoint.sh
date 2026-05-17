#!/bin/bash
set -e

echo "=== Send başlatılıyor ==="

mkdir -p /var/www/html/storage/uploads /var/www/html/storage/temp /var/www/html/storage/logs /var/www/html/public/assets/images
chown -R www-data:www-data /var/www/html/storage /var/www/html/public/assets/images
chmod -R 775 /var/www/html/storage /var/www/html/public/assets/images

cat > /var/www/html/storage/uploads/.htaccess <<'HTACCESS'
Options -Indexes
<FilesMatch "\.php$">
Require all denied
</FilesMatch>
HTACCESS

cat > /var/www/html/storage/uploads/index.html <<'HTML'
<!doctype html><html><body>Access denied</body></html>
HTML

# Coolify/proxy domain'i APP_URL olarak vermediyse FQDN'den üret.
if [ -z "${APP_URL:-}" ] || [ "${APP_URL}" = "http://localhost" ]; then
  if [ -n "${COOLIFY_FQDN:-}" ]; then
    export APP_URL="https://${COOLIFY_FQDN}"
  else
    export APP_URL="http://localhost"
  fi
fi

cat > /var/www/html/.env <<ENV
APP_NAME=${APP_NAME:-Send}
APP_ENV=${APP_ENV:-production}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL}
DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-hawarsend}
DB_USERNAME=${DB_USERNAME:-hawarsend}
DB_PASSWORD=${DB_PASSWORD:-strong_password_here}
UPLOAD_MAX_SIZE=${UPLOAD_MAX_SIZE:-32212254720}
DEFAULT_EXPIRE_HOURS=${DEFAULT_EXPIRE_HOURS:-24}
ALLOW_GUEST_UPLOAD=${ALLOW_GUEST_UPLOAD:-true}
ADMIN_EMAIL=${ADMIN_EMAIL:-admin@send.hawarserver.com}
ADMIN_PASSWORD=${ADMIN_PASSWORD:-admin123}
SMTP_HOST=${SMTP_HOST:-}
SMTP_PORT=${SMTP_PORT:-587}
SMTP_USER=${SMTP_USER:-}
SMTP_PASS=${SMTP_PASS:-}
SMTP_FROM=${SMTP_FROM:-}
SMTP_FROM_NAME=${SMTP_FROM_NAME:-Send}
ENV
chown www-data:www-data /var/www/html/.env
chmod 640 /var/www/html/.env

echo "=== Veritabanı bekleniyor ==="
MAX_TRIES=60
COUNT=0
until php -r "new PDO('mysql:host=${DB_HOST:-db};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-hawarsend};charset=utf8mb4','${DB_USERNAME:-hawarsend}','${DB_PASSWORD:-strong_password_here}', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); echo 'OK';" 2>/dev/null | grep -q "OK"; do
  COUNT=$((COUNT + 1))
  if [ "$COUNT" -ge "$MAX_TRIES" ]; then
    echo "Veritabanına bağlanılamadı. Yine de servisler başlatılıyor; logları kontrol et."
    break
  fi
  echo "Veritabanı hazır değil, bekleniyor... ($COUNT/$MAX_TRIES)"
  sleep 2
done

if [ "$COUNT" -lt "$MAX_TRIES" ]; then
  echo "=== Migration çalıştırılıyor ==="
  php /var/www/html/database/migrations.php || echo "Migration tamamlanamadı; logları kontrol et."

  echo "=== Admin kontrol ediliyor ==="
  php /var/www/html/database/create_admin.php || echo "Admin oluşturma atlandı veya admin zaten mevcut."
fi

echo "=== Supervisor başlatılıyor ==="
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
