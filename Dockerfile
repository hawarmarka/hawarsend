FROM php:8.2-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive

# Coolify düşük RAM'li sunucularda build aşamasında mbstring derlemesi çökebiliyor.
# Bu yüzden sadece projenin gerçekten ihtiyaç duyduğu PHP eklentileri kuruluyor.
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    cron \
    curl \
    ca-certificates \
    unzip \
    git \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    pkg-config \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j2 pdo pdo_mysql zip gd exif opcache \
    && apt-get purge -y --auto-remove pkg-config \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP upload / production configuration.
RUN { \
      echo "upload_max_filesize = 30G"; \
      echo "post_max_size = 30G"; \
      echo "memory_limit = 2048M"; \
      echo "max_execution_time = 3600"; \
      echo "max_input_time = 3600"; \
      echo "file_uploads = On"; \
    } > /usr/local/etc/php/conf.d/uploads.ini \
    && { \
      echo "expose_php = Off"; \
      echo "display_errors = Off"; \
      echo "log_errors = On"; \
      echo "error_log = /var/www/html/storage/logs/php_errors.log"; \
    } > /usr/local/etc/php/conf.d/security.ini

# Nginx config.
COPY nginx/default.conf /etc/nginx/sites-available/default

# Supervisor config.
RUN mkdir -p /etc/supervisor/conf.d
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html
COPY . .

# Runtime folders and permissions.
RUN mkdir -p storage/uploads storage/temp storage/logs public/assets/images \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage public/assets/images \
    && printf "Options -Indexes\n<FilesMatch \"\\.php$\">\nRequire all denied\n</FilesMatch>\n" > /var/www/html/storage/uploads/.htaccess \
    && chmod +x /var/www/html/entrypoint.sh

# Hourly expired-file cleanup.
RUN echo "0 * * * * www-data php /var/www/html/cron/cleanup.php >> /var/www/html/storage/logs/cron.log 2>&1" > /etc/cron.d/hawarsend-cleanup \
    && chmod 0644 /etc/cron.d/hawarsend-cleanup

EXPOSE 80
ENTRYPOINT ["/var/www/html/entrypoint.sh"]
