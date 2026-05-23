FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    wget \
    git \
    zip \
    unzip \
    nginx \
    libssl-dev \
    libcurl4-openssl-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    curl \
    openssl \
    pdo \
    pdo_mysql \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Create necessary directories
RUN mkdir -p /app/runtime/log /app/runtime/temp /app/public && \
    chmod -R 777 /app/runtime /app/public 2>/dev/null || true

# Create Nginx configuration directories
RUN mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled

# Copy Nginx configurations
COPY nginx.conf /etc/nginx/nginx.conf
COPY default.conf /etc/nginx/sites-available/default

# Enable the site
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Install Composer dependencies (only if composer.json exists)
RUN if [ -f composer.json ]; then \
    composer install --no-interaction --optimize-autoloader --no-dev 2>/dev/null || true; \
fi

# Create startup script
RUN cat > /start.sh << 'EOF'
#!/bin/bash
set -e

# Start PHP-FPM in the background
php-fpm &

# Wait for PHP-FPM to start
sleep 2

# Start Nginx in the foreground
exec nginx -g 'daemon off;'
EOF

RUN chmod +x /start.sh

# Expose port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/arkoselabs/health || exit 1

# Start services
CMD ["/start.sh"]
