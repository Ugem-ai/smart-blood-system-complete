# =============================================================================
# Smart Blood System - Docker Build for Render Deployment
# PHP 8.2 with Laravel 12 - Production-Ready & Optimized
# =============================================================================

# Stage 1: Builder - Compile dependencies
FROM php:8.2-cli AS builder

WORKDIR /app

# Install build dependencies (will be discarded in runtime stage)
# Critical: Must include ALL *-dev packages before extension compilation
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
    libsqlite3-dev \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Configure GD extension with all required components BEFORE installation
# Order matters: configure MUST run before install
RUN docker-php-ext-configure gd \
    --with-freetype=/usr/include/freetype2 \
    --with-jpeg=/usr/include

# Install PHP extensions in correct order (PDO drivers before other extensions)
# pdo must be installed first (other pdo_* depend on it)
RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    mbstring \
    zip \
    gd \
    xml \
    bcmath \
    ctype \
    fileinfo \
    tokenizer

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy only composer files (for layer caching)
COPY composer.json composer.lock* ./

# Install PHP dependencies (production only, with optimizations)
RUN composer install \
    --no-interaction \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative

# Copy entire project
COPY . .

# Pre-warm Laravel caches and optimizations
RUN mkdir -p storage/logs bootstrap/cache && \
    php artisan optimize:clear 2>/dev/null || true && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache 2>/dev/null || true

# =============================================================================
# Stage 2: Runtime - Minimal production image
# =============================================================================
FROM php:8.2-cli

WORKDIR /app

# Install runtime dependencies only (minimal footprint)
# ONLY runtime libraries, NO *-dev packages
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    sqlite3 \
    libsqlite3-0 \
    libzip5 \
    libpng16-16 \
    libjpeg62-turbo \
    libfreetype6 \
    libonig5 \
    libxml2 \
    libpq5 \
    && rm -rf /var/lib/apt/lists/*

# Copy compiled PHP extensions from builder stage
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Enable PHP extensions (load pre-compiled extensions, no compilation)
RUN docker-php-ext-enable \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    mbstring \
    zip \
    gd \
    xml \
    bcmath \
    ctype \
    fileinfo \
    tokenizer

# Verify extensions are loaded properly
RUN php -m | grep -E 'pdo|pdo_mysql|pdo_pgsql|pdo_sqlite' || \
    (echo "ERROR: Required extensions not loaded!" && exit 1)

# Set production-safe PHP configuration
RUN { \
    echo "max_execution_time = 300"; \
    echo "upload_max_filesize = 50M"; \
    echo "post_max_size = 50M"; \
    echo "memory_limit = 256M"; \
    echo "display_errors = Off"; \
    echo "log_errors = On"; \
    echo "error_log = /var/log/php_errors.log"; \
    } > /usr/local/etc/php/conf.d/laravel.ini

# Copy entire application from builder stage
COPY --from=builder /app /app

# Create required directories with secure permissions (755 = rwxr-xr-x)
RUN mkdir -p storage/logs storage/app bootstrap/cache database && \
    chmod -R 755 storage bootstrap/cache database

# Create non-root user for security (UID 1000 = standard unprivileged)
RUN useradd -m -u 1000 laravel && \
    chown -R laravel:laravel /app

USER laravel

# Expose port for Render
EXPOSE 10000

# Health check (tests if Laravel server is responding)
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:10000/health || exit 1

# Start Laravel development server on 0.0.0.0:10000 (required for Docker/Render)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=10000"]
