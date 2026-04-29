# =============================================================================
# Smart Blood System - Docker Build for Render Deployment (PostgreSQL Only)
# PHP 8.2 with Laravel 12 - Production-Ready & Optimized
# =============================================================================

# Stage 1: Builder - Compile dependencies
FROM php:8.2-cli AS builder

WORKDIR /app

# Install build dependencies (will be discarded in runtime stage)
# PostgreSQL-only setup: minimal, focused dependencies
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
    && rm -rf /var/lib/apt/lists/*

# Configure GD extension with all required components BEFORE installation
# Order matters: configure MUST run before install
RUN docker-php-ext-configure gd \
    --with-freetype=/usr/include/freetype2 \
    --with-jpeg=/usr/include

# Install ONLY required PHP extensions (PostgreSQL-focused)
# Lean extension set: pdo_pgsql + essential Laravel requirements
RUN docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    mbstring \
    zip \
    gd \
    xml \
    bcmath

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
    --classmap-authoritative \
    --no-scripts

# Copy entire project
COPY . .

# DO NOT run Laravel cache commands here!
# Reason: Environment variables are NOT available at build time
# These MUST be run at runtime when Render injects environment variables
# Attempting to cache config/routes before deployment causes:
# - Missing environment variable errors
# - Stale cached values on deployment
# - Application failures on startup
#
# Cache warming will happen automatically on first request (Laravel 12)
# Or explicitly run via: php artisan optimize (at runtime)
#
# Create required directories only
RUN mkdir -p storage/logs bootstrap/cache

# =============================================================================
# Stage 2: Runtime - Minimal production image (PostgreSQL-only)
# =============================================================================
FROM php:8.2-cli

WORKDIR /app

# Install runtime dependencies ONLY (minimal, PostgreSQL-focused footprint)
# NO *-dev packages, NO database tools (except PostgreSQL client)
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

# Copy compiled PHP extensions from builder stage
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Enable PostgreSQL extension (load pre-compiled, no compilation needed)
RUN docker-php-ext-enable \
    pdo_pgsql \
    mbstring \
    zip \
    gd \
    xml \
    bcmath

# Verify PostgreSQL extension is loaded (critical for Render)
RUN php -m | grep -i pdo_pgsql || \
    (echo "ERROR: pdo_pgsql extension not loaded!" && exit 1)

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

# Create required directories with proper writable permissions
# 775 = rwxrwxr-x (owner+group can write, others read-only)
# Storage and bootstrap/cache MUST be writable for Laravel to:
# - Create log files (storage/logs)
# - Store cached views, sessions, etc.
# - Store uploaded files
RUN mkdir -p storage/logs storage/app bootstrap/cache database && \
    chmod -R 775 storage bootstrap/cache

# Create non-root user for security (UID 1000 = standard unprivileged)
RUN useradd -m -u 1000 laravel && \
    chown -R laravel:laravel /app

USER laravel

# Expose port for Render
EXPOSE 10000

# Note: Healthcheck removed for production safety
# Reason: Healthcheck attempts to hit /health endpoint which may not exist
# Render will automatically health-check by monitoring if port 10000 is responding
# If needed in future, add explicit /health route to Laravel and uncomment below:
# HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
#     CMD curl -f http://localhost:10000/health || exit 1

# Start Laravel development server on 0.0.0.0:10000 (required for Docker/Render)
# Run Laravel optimization commands at runtime when environment variables are available
# These MUST run here (not at build time) because they require DB/config environment vars
CMD sh -c "php artisan package:discover && \
    php artisan config:clear && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan serve --host=0.0.0.0 --port=10000"
