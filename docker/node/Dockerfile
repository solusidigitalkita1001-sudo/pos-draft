FROM node:20-alpine

# ─────────────────────────────────────────────────────────────────────────
# Install PHP CLI di image Node.
# Dibutuhkan karena starter kit ini memakai @laravel/vite-plugin-wayfinder,
# yang menjalankan "php artisan wayfinder:generate" setiap Vite dev server
# start. Tanpa PHP, container akan crash-loop.
# ─────────────────────────────────────────────────────────────────────────
RUN apk add --no-cache \
    php83 \
    php83-cli \
    php83-common \
    php83-tokenizer \
    php83-mbstring \
    php83-openssl \
    php83-curl \
    php83-dom \
    php83-xml \
    php83-xmlwriter \
    php83-simplexml \
    php83-fileinfo \
    php83-session \
    php83-pdo \
    php83-pdo_mysql \
    php83-pdo_sqlite \
    php83-ctype \
    php83-phar \
    php83-iconv \
    php83-bcmath \
    && ln -sf /usr/bin/php83 /usr/bin/php

WORKDIR /app

# Install dependencies dulu (layer caching)
COPY package*.json ./

RUN npm install

# Copy semua source
COPY . .

EXPOSE 5174

# Jalankan Vite dev server (HMR) - untuk development saja
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5174"]