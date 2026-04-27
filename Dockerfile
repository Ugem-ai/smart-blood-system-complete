# =============================================================================
# Smart Blood System - Docker Build for Render Deployment
# PHP 8.2 with Laravel 12
# =============================================================================

# Stage 1: Build stage
FROM php:8.2-cli AS builder

# Set working directory
WORKDIR /app

# Install system dependencies required for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required for Laravel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    mbstring \
    zip \
    gd \
    xml \
    bcmath \
    tokenizer \
    ctype \
    fileinfo

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy composer files
COPY composer.json composer.lock* ./

# Install PHP dependencies (production only)
RUN composer install \
    --no-interaction \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative

# Copy entire project
COPY . .

# Generate optimized Laravel files
RUN php artisan optimize:clear || true
RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan view:cache

# Create necessary directories with proper permissions
RUN mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && chmod -R 777 storage \
    && chmod -R 777 bootstrap/cache

# =============================================================================
# Stage 2: Runtime stage (smaller final image)
# =============================================================================
FROM php:8.2-cli

WORKDIR /app

# Install only runtime dependencies (much smaller footprint)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip4 \
    libpng6 \
    libjpeg62-turbo \
    libfreetype6 \
    libonig5 \
    libxml2 \
    git \
    curl \
    sqlite3 \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (runtime versions only)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    mbstring \
    zip \
    gd \
    xml \
    bcmath \
    tokenizer \
    ctype \
    fileinfo

# Copy PHP configuration for production
RUN echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/laravel.ini \
    && echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/laravel.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/laravel.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/laravel.ini

# Copy application from builder stage
COPY --from=builder /app /app

# Create and set proper permissions
RUN mkdir -p storage/logs bootstrap/cache database \
    && chmod -R 775 storage \
    && chmod -R 775 bootstrap/cache \
    && chmod -R 775 database

# Create a non-root user for security
RUN useradd -m -u 1000 laravel \
    && chown -R laravel:laravel /app
USER laravel

# Expose port for Render
EXPOSE 10000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:10000/health || exit 1

# Start Laravel development server on 0.0.0.0:10000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=10000"]
