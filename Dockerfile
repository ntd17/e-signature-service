FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libssl-dev \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    opcache

# Configure PHP
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/memory_limit = 128M/memory_limit = 256M/' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/max_execution_time = 30/max_execution_time = 60/' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 10M/' "$PHP_INI_DIR/php.ini"

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Copy composer.json
COPY composer.json ./

# Set environment variable to allow Composer to run as root
ENV COMPOSER_ALLOW_SUPERUSER=1

# Install dependencies
RUN composer update --no-scripts --no-autoloader

# Copy the rest of the application
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Create data directory with proper permissions
RUN mkdir -p data/contracts && chmod 777 data/contracts

# Configure PHP for development
RUN if [ "$APP_ENV" = "development" ]; then \
    mv "$PHP_INI_DIR/php.ini" "$PHP_INI_DIR/php.ini-production" \
    && mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && echo "error_reporting = E_ALL" >> "$PHP_INI_DIR/php.ini" \
    && echo "display_errors = On" >> "$PHP_INI_DIR/php.ini" \
    && echo "display_startup_errors = On" >> "$PHP_INI_DIR/php.ini" \
    ; fi

# Expose port
EXPOSE 8000

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:8000", "-t", "src"]
