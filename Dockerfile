# syntax=docker/dockerfile:1

# ---------- Dependencias PHP ----------
FROM composer:2 AS vendor
WORKDIR /app

# Primero solo los manifiestos: si no cambian, Docker reutiliza la caché de
# esta capa y no vuelve a descargar todo el vendor en cada build.
COPY composer.json composer.lock ./

# En producción la imagen no debe llevar Faker, Pest ni el debugbar. Pero
# en local sí: sin faker, db:seed revienta con "undefined function fake()",
# y sin Pest no se pueden ejecutar los tests dentro del contenedor.
ARG INSTALL_DEV=false

# --no-autoloader: todavía no está el código de la aplicación, así que el
# mapa de clases se generaría incompleto. Se genera abajo, ya con todo.
RUN if [ "$INSTALL_DEV" = "true" ]; then \
    composer install --no-scripts --no-autoloader --no-interaction --prefer-dist; \
    else \
    composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist; \
    fi

COPY . .

# --no-scripts también aquí: el post-autoload-dump de Laravel ejecuta
# artisan package:discover, y en el contenedor de build no hay .env ni
# APP_KEY. Laravel descubre los paquetes al arrancar si no existe el
# manifiesto cacheado.
RUN if [ "$INSTALL_DEV" = "true" ]; then \
    composer dump-autoload --optimize --no-scripts --no-interaction; \
    else \
    composer dump-autoload --optimize --no-dev --no-scripts --no-interaction; \
    fi

# ---------- Assets ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---------- Imagen final ----------
FROM php:8.4-fpm-alpine

# Composer también queda disponible en la imagen de desarrollo. El vendor
# sigue construyéndose en la etapa separada, pero así pueden ejecutarse dentro
# del contenedor los mismos controles que usa CI: validate, audit y scripts.
COPY --from=vendor /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache \
    libpng libjpeg-turbo libwebp freetype icu-libs oniguruma libzip sqlite-libs \
    && apk add --no-cache --virtual .build \
    $PHPIZE_DEPS libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    icu-dev oniguruma-dev libzip-dev sqlite-dev \
    # gd: RemoteImageStore reencodea con él las imágenes descargadas de
    # TheSportsDB y Commons. Sin gd no hay validación de imágenes de
    # terceros. sqlite: los tests corren sobre SQLite en memoria.
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
    gd pdo_mysql pdo_sqlite mbstring exif zip intl bcmath opcache \
    && apk del .build

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# Pest 5 inicializa su caché de mutaciones incluso en una ejecución normal.
# La creamos con propietario www-data para evitar el warning de mkdir() al
# ejecutar la suite dentro del contenedor sin privilegios.
ENV TMPDIR=/tmp \
    COMPOSER_HOME=/tmp/composer
RUN mkdir -p /tmp/pest-mutate-cache /tmp/composer \
    && chown -R www-data:www-data /tmp/pest-mutate-cache /tmp/composer \
    && chmod 1777 /tmp \
    && chmod 775 /tmp/pest-mutate-cache

WORKDIR /var/www/html

# El vendor viene con el autoloader ya optimizado desde la etapa anterior.
# Composer se conserva para los controles locales de calidad y mantenimiento.
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

# public/ también: si alguna vez se ejecuta storage:link, el proceso corre
# como www-data y necesita poder escribir el enlace ahí.
RUN chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R ug+rw storage bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]