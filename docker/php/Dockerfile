# ============================================================
# ChiperX — PHP 8.2 FPM (produksi)
# ============================================================
FROM php:8.2-fpm-alpine

# Ekstensi yang dibutuhkan aplikasi
RUN apk add --no-cache curl-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql mbstring curl opcache

# OPcache (performa produksi)
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.interned_strings_buffer=16'; \
      echo 'opcache.max_accelerated_files=10000'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.jit=tracing'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# PHP-FPM: JANGAN hapus environment variable (default clear_env=yes
# membuang env dari docker-compose → DB_HOST dsb. tak terbaca PHP!)
RUN echo 'clear_env = no' >> /usr/local/etc/php-fpm.d/zz-docker.conf

# Hardening PHP
RUN { \
      echo 'expose_php=Off'; \
      echo 'display_errors=Off'; \
      echo 'log_errors=On'; \
      echo 'upload_max_filesize=55M'; \
      echo 'post_max_size=60M'; \
      echo 'date.timezone=Asia/Jakarta'; \
      echo 'session.cookie_httponly=1'; \
      echo 'session.use_strict_mode=1'; \
      echo 'session.save_path=/var/www/html/storage/sessions'; \
    } > /usr/local/etc/php/conf.d/chiperx.ini

# Composer (ambil dari image resmi)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
# .dockerignore mengabaikan isi storage/* → buat ulang strukturnya
# (session di storage agar login TIDAK hilang saat container restart)
RUN mkdir -p storage/logs storage/cache storage/backups storage/uploads storage/sessions
COPY composer.json composer.json
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .
RUN chown -R www-data:www-data storage && chmod -R 750 storage

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
