# =============================================================================
# Smart Blood System - Docker Build for Render Deployment (PostgreSQL Only)
# PHP 8.2 with Laravel 12 - Production-Ready & Optimized
# =============================================================================

# Stage 1: Builder - Compile dependencies
FROM php:8.2-cli AS builder

WORKDIR /app

# Install build dependencies + Node.js
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    autoconf \
    pkg-config \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    git \
    curl \
    unzip \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd \
    --with-freetype=/usr/include/freetype2 \
    --with-jpeg=/usr/include

RUN docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    mbstring \
    zip \
    gd \
    xml \
    bcmath

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHP dependencies
COPY composer.json composer.lock* ./
RUN composer install \
    --no-interaction \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# Install Node dependencies and build frontend assets
COPY package.json package-lock.json* ./
RUN npm ci --production=false

# Copy project and build Vite assets
COPY . .
RUN npm run build

# Create required directories
RUN mkdir -p storage/logs bootstrap/cache

# =============================================================================
# Stage 2: Runtime
# =============================================================================
FROM php:8.2-cli

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    libzip5 \
    libpng16-16 \
    libjpeg62-turbo \
    libfreetype6 \
    libonig5 \
    libxml2 \
    libpq5 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

RUN docker-php-ext-enable \
    pdo_pgsql \
    mbstring \
    zip \
    gd \
    xml \
    bcmath

RUN php -m | grep -i pdo_pgsql || \
    (echo "ERROR: pdo_pgsql extension not loaded!" && exit 1)

RUN { \
    echo "max_execution_time = 300"; \
    echo "upload_max_filesize = 50M"; \
    echo "post_max_size = 50M"; \
    echo "memory_limit = 256M"; \
    echo "display_errors = Off"; \
    echo "log_errors = On"; \
    echo "error_log = /var/log/php_errors.log"; \
    } > /usr/local/etc/php/conf.d/laravel.ini

COPY --from=builder /app /app

RUN mkdir -p storage/logs storage/app bootstrap/cache database && \
    chmod -R 775 storage bootstrap/cache

RUN useradd -m -u 1000 laravel && \
    chown -R laravel:laravel /app

USER laravel

EXPOSE 10000

CMD sh -c "php artisan config:clear && \
    php artisan migrate --force && \
    php artisan serve --host=0.0.0.0 --port=10000"