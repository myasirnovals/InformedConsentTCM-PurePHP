FROM php:8.2-apache

# 1. Install system dependencies (SQLite3, etc.)
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. Install required PHP extensions
RUN docker-php-ext-install pdo_sqlite mbstring

# 3. Configure Apache DocumentRoot to point to /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 4. Enable Apache rewrite module
RUN a2enmod rewrite

# 5. Copy source code
WORKDIR /var/www/html
COPY . /var/www/html

# 6. Create storage directories & permissions
RUN mkdir -p storage/signatures storage/pdf storage/logs && \
    php setup/init_db.php && \
    chown -R www-data:www-data /var/www/html/storage && \
    chmod -R 775 /var/www/html/storage

EXPOSE 80

CMD ["apache2-foreground"]
