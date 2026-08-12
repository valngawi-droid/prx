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
    } > /usr/local/etc/php/conf.d/chiperx.ini

# Composer (ambil dari image resmi)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.json
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .
RUN chown -R www-data:www-data storage && chmod -R 750 storage

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
