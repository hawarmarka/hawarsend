# 🚀 HawarSend — Dosya Paylaşım Platformu

WeTransfer / Send.vis.ee tarzında modern, production-ready, Docker destekli açık kaynak dosya paylaşım platformu.

![PHP](https://img.shields.io/badge/PHP-8.2+-blue)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B%20%7C%20MariaDB-orange)
![Docker](https://img.shields.io/badge/Docker-ready-2496ED)
![License](https://img.shields.io/badge/license-MIT-green)

---

## 📋 İçindekiler

1. [Özellikler](#özellikler)
2. [Gereksinimler](#gereksinimler)
3. [GitHub'a Yükleme](#githuba-yükleme)
4. [Docker Compose ile Çalıştırma](#docker-compose-ile-çalıştırma)
5. [Server Panelinden Deploy](#server-panelinden-deploy)
6. [.env Yapılandırması](#env-yapılandırması)
7. [Domain Bağlama](#domain-bağlama)
8. [Admin Paneli](#admin-paneli)
9. [Storage & Volume Yapısı](#storage--volume-yapısı)
10. [Cron Temizleme](#cron-temizleme)
11. [Veritabanı Yedekleme](#veritabanı-yedekleme)
12. [Upload Limit Artırma](#upload-limit-artırma)
13. [Güvenlik Notları](#güvenlik-notları)
14. [Sorun Giderme](#sorun-giderme)

---

## Özellikler

- ✅ Tekli / çoklu dosya yükleme (sürükle & bırak)
- ✅ AJAX upload + gerçek zamanlı progress bar
- ✅ Benzersiz paylaşım linki (`/d/TOKEN`)
- ✅ Şifre korumalı linkler
- ✅ Süreli linkler (1 saat → süresiz)
- ✅ İndirme limiti
- ✅ Dosya önizleme (resim / video / ses / PDF)
- ✅ QR kod + WhatsApp / Telegram / Mail paylaşımı
- ✅ Üyelik sistemi
- ✅ Kullanıcı paneli (dosya yönetimi)
- ✅ Tam özellikli admin paneli
- ✅ Tüm ayarlar admin panelinden yönetilebilir
- ✅ Bakım modu
- ✅ Reklam & Analytics kod alanları
- ✅ SMTP e-posta desteği
- ✅ Docker + Docker Compose
- ✅ Otomatik migration & admin oluşturma
- ✅ Healthcheck endpoint
- ✅ Tamamen Türkçe arayüz
- ✅ Koyu tema, glassmorphism tasarım
- ✅ Mobil uyumlu

---

## Gereksinimler

**Docker ile (önerilen):**
- Docker 24+
- Docker Compose v2

**Manuel kurulum:**
- PHP 8.2+ (pdo_mysql, mbstring, gd, zip, fileinfo, exif, curl)
- MySQL 8.0+ veya MariaDB 10.6+
- Nginx veya Apache
- 2 GB+ disk alanı

---

## GitHub'a Yükleme

```bash
git clone https://github.com/KULLANICI/hawarsend.git
cd hawarsend

# .env dosyasını oluştur
cp .env.example .env
nano .env   # veya vim .env

# Çalıştır
docker compose up -d --build
```

---

## Docker Compose ile Çalıştırma

### 1. Repoyu klonla

```bash
git clone https://github.com/KULLANICI/hawarsend.git
cd hawarsend
```

### 2. .env dosyasını hazırla

```bash
cp .env.example .env
```

`.env` dosyasını düzenle ve şu alanları doldur:

```env
APP_URL=https://yourdomain.com
DB_PASSWORD=guclu_sifre_buraya
DB_ROOT_PASSWORD=root_sifre_buraya
ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=admin_sifresi
```

### 3. Başlat

```bash
docker compose up -d --build
```

### 4. Durumu kontrol et

```bash
docker compose ps
docker compose logs -f
```

### Faydalı Komutlar

```bash
# Yeniden başlat
docker compose restart

# Durdur
docker compose down

# Durdur + volume'ları sil (DİKKAT: tüm veriler silinir!)
docker compose down -v

# Migration'ı manuel çalıştır
docker compose exec app php database/migrations.php

# Admin oluştur
docker compose exec app php database/create_admin.php

# Cleanup cron'u manuel çalıştır
docker compose exec app php cron/cleanup.php

# Container'a bağlan
docker compose exec app bash

# Loglara bak
docker compose logs -f app
docker compose logs -f db
```

---

## Server Panelinden Deploy

### Coolify / Dokploy / CapRover ile

1. Yeni uygulama oluştur → **"Docker Compose"** seç
2. Repository URL gir: `https://github.com/KULLANICI/hawarsend`
3. Branch: `main`
4. **Environment Variables** bölümüne `.env` içeriğini yapıştır
5. **Deploy** butonuna tıkla
6. Container ayağa kalkınca domain bağla

### Dockerfile ile (tek container)

Bazı panel sistemleri tek Dockerfile ile çalışır. Bu durumda harici MySQL gerekir:

```bash
# .env içinde DB_HOST'u dış MySQL adresine yönelt
DB_HOST=mysql.yourdomain.com
```

---

## .env Yapılandırması

| Değişken | Açıklama | Örnek |
|---|---|---|
| `APP_URL` | Sitenin tam URL'i | `https://hawarsend.com` |
| `APP_ENV` | Ortam | `production` |
| `APP_DEBUG` | Hata ayıklama | `false` |
| `DB_HOST` | Veritabanı adresi | `db` (Docker) |
| `DB_PORT` | Veritabanı portu | `3306` |
| `DB_DATABASE` | Veritabanı adı | `hawarsend` |
| `DB_USERNAME` | Kullanıcı adı | `hawarsend` |
| `DB_PASSWORD` | Şifre | güçlü bir şifre |
| `DB_ROOT_PASSWORD` | MySQL root şifresi | güçlü bir şifre |
| `UPLOAD_MAX_SIZE` | Maksimum dosya boyutu (byte) | `2147483648` (2 GB) |
| `DEFAULT_EXPIRE_HOURS` | Varsayılan saklama süresi (saat) | `24` |
| `ALLOW_GUEST_UPLOAD` | Misafir yükleme | `true` |
| `ADMIN_EMAIL` | Admin e-postası | `admin@domain.com` |
| `ADMIN_PASSWORD` | Admin şifresi | güçlü bir şifre |
| `SMTP_HOST` | SMTP sunucusu | `smtp.gmail.com` |
| `SMTP_PORT` | SMTP portu | `587` |
| `SMTP_USER` | SMTP kullanıcı | `user@gmail.com` |
| `SMTP_PASS` | SMTP şifre | |
| `SMTP_FROM` | Gönderici e-posta | `noreply@domain.com` |

---

## Domain Bağlama

### Nginx Reverse Proxy (Docker dışı)

```nginx
server {
    listen 80;
    server_name hawarsend.com www.hawarsend.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name hawarsend.com www.hawarsend.com;

    ssl_certificate     /etc/letsencrypt/live/hawarsend.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/hawarsend.com/privkey.pem;

    location / {
        proxy_pass         http://localhost:8080;
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        client_max_body_size 2G;
        proxy_read_timeout 600s;
    }
}
```

### SSL Sertifikası (Let's Encrypt)

```bash
certbot --nginx -d hawarsend.com -d www.hawarsend.com
```

---

## Admin Paneli

**URL:** `https://yourdomain.com/admin/login.php`

**Varsayılan Giriş:**
- E-posta: `admin@hawarsend.com`
- Şifre: `admin123`

> ⚠️ **İlk girişten sonra şifreyi mutlaka değiştirin!**

Admin panelinden yapılabilecekler:
- Tüm dosyaları görüntüleme ve silme
- Kullanıcı yönetimi (aktif/engel/sil)
- Rapor yönetimi
- Site ayarları (ad, logo, favicon, hero metinleri)
- Upload limitleri ve yasaklı uzantılar
- SMTP e-posta yapılandırması
- Reklam kodları ve Analytics
- Özel CSS/JS ekleme
- `<head>` ve footer kodları
- Bakım modu
- Disk temizleme

---

## Storage & Volume Yapısı

Yüklenen dosyalar Docker volume'una kaydedilir. Container silinse bile dosyalar kaybolmaz.

```yaml
volumes:
  uploads_data:   # /var/www/html/storage/uploads
  db_data:        # MySQL veri dizini
  logs_data:      # Uygulama logları
```

### Dosya Depolama Yapısı

```
storage/uploads/
└── {TOKEN}/
    ├── a1b2c3d4e5f6.jpg   ← Rastgele isimli
    └── f9e8d7c6b5a4.pdf
```

- Gerçek dosya adları asla URL'de görünmez
- PHP çalıştırma uploads dizininde engellenmiştir
- İndirme işlemi token doğrulamasıyla PHP üzerinden yapılır

---

## Cron Temizleme

Süresi dolmuş dosyaların otomatik temizlenmesi:

```bash
# Container içinde (otomatik çalışır)
docker compose exec app crontab -l

# Manuel tetikleme
docker compose exec app php cron/cleanup.php

# Manuel kurulumda crontab ekle
crontab -e
# Şunu ekle:
0 2 * * * /usr/local/bin/php /var/www/html/cron/cleanup.php >> /var/www/html/storage/logs/cron.log 2>&1
```

---

## Veritabanı Yedekleme

```bash
# Yedek al
docker compose exec db mysqldump -u hawarsend -p hawarsend > backup_$(date +%Y%m%d).sql

# Yedeği geri yükle
docker compose exec -T db mysql -u hawarsend -p hawarsend < backup_20240101.sql
```

---

## Upload Limit Artırma

`.env` içinde:
```env
UPLOAD_MAX_SIZE=5368709120   # 5 GB
```

`docker-compose.yml` içinde PHP ortam değişkenleri:
```yaml
environment:
  PHP_UPLOAD_MAX: 5G
  PHP_POST_MAX: 5G
```

Değişiklikten sonra yeniden başlat:
```bash
docker compose up -d --build
```

---

## Güvenlik Notları

- Tüm SQL sorguları PDO prepared statements kullanır
- CSRF token sistemi tüm formlarda aktif
- Dosyalar rastgele hex isimle kaydedilir
- PHP çalıştırma uploads dizininde Nginx tarafından engellenir
- `.php`, `.exe`, `.sh` ve diğer tehlikeli uzantılar engellidir
- IP bazlı rate limiting aktif
- Admin paneli için brute-force koruması (5 deneme / 30 dakika)
- Tüm şifreler `password_hash(BCRYPT)` ile saklanır
- `X-Frame-Options`, `X-Content-Type-Options` gibi güvenlik başlıkları aktif
- `/app`, `/database`, `/storage`, `/install` dizinlerine dış erişim engellenir

**Kurulumdan sonra yapılması gerekenler:**
1. `/install` dizinini sunucudan sil veya engelini koy
2. Admin şifreni değiştir
3. `.env` dosyasının dış erişime kapalı olduğundan emin ol
4. HTTPS kullan (Let's Encrypt ücretsiz)

---

## Sorun Giderme

### Container başlamıyor

```bash
docker compose logs app
docker compose logs db
```

### Veritabanı bağlantı hatası

- `.env` içinde `DB_HOST=db` (Docker Compose servis adı)
- MySQL container'ın hazır olmasını bekle: `docker compose logs db`

### Upload başarısız

- `storage/uploads` dizininin yazılabilir olduğunu kontrol et
- PHP upload limitlerini kontrol et

### Permission hatası

```bash
docker compose exec app chown -R www-data:www-data /var/www/html/storage
docker compose exec app chmod -R 755 /var/www/html/storage
```

### Migration yeniden çalıştırma

```bash
docker compose exec app php database/migrations.php
```

---

## Healthcheck

`GET /health.php` → JSON

```json
{
  "status": "ok",
  "db": "connected",
  "storage": "writable",
  "timestamp": "2024-01-01T12:00:00+00:00"
}
```

---

## Lisans

MIT License — Özgürce kullanabilir, değiştirebilir ve dağıtabilirsiniz.

---

**HawarSend** — *Dosyalarını hızlı ve güvenli paylaş.*
