# syntax=docker/dockerfile:1

FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl ca-certificates gnupg \
    libpq-dev libzip-dev \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    poppler-utils \
    chromium \
    fonts-liberation fonts-dejavu-core \
    libnss3 libatk-bridge2.0-0 libgtk-3-0 libgbm1 libasound2 \
    && docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql pcntl zip gd \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g puppeteer-core@23 \
    && rm -rf /var/lib/apt/lists/*

ENV PUPPETEER_SKIP_DOWNLOAD=true \
    PHOENIX_REPORTS_CHROME_PATH=/usr/bin/chromium \
    PHOENIX_REPORTS_PDF_DRIVER=auto \
    PHOENIX_REPORTS_CHROME_NO_SANDBOX=true

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN useradd -ms /bin/bash phoenix \
    && chown -R phoenix:phoenix /var/www/html

USER phoenix
