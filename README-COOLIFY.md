# Send Coolify Kurulum

## Coolify ayarları

- Build Pack: `Docker Compose`
- Base Directory: `/`
- Docker Compose Location: `/docker-compose.yml`
- Domain: `https://send.hawarserver.com`

## Environment Variables

```env
APP_NAME=Send
APP_ENV=production
APP_DEBUG=false
APP_URL=https://send.hawarserver.com
DB_HOST=db
DB_PORT=3306
DB_DATABASE=hawarsend
DB_USERNAME=hawarsend
DB_PASSWORD=Send_DB_2026!
DB_ROOT_PASSWORD=Send_ROOT_2026!

ADMIN_EMAIL=admin@send.hawarserver.com
ADMIN_PASSWORD=HawarAdmin_2026!

UPLOAD_MAX_SIZE=2147483648
DEFAULT_EXPIRE_HOURS=24
ALLOW_GUEST_UPLOAD=true

SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
SMTP_FROM=
SMTP_FROM_NAME=Send
```

Admin panel:

`https://send.hawarserver.com/admin/login.php`

Bu sürümde Docker build hafifletildi ve Coolify için host 80 port bind kaldırıldı. Coolify zaten 80/443 portlarını kendi proxy'siyle kullanır; uygulama sadece container içinde 80 portunu expose eder.
