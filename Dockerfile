FROM php:8.1-apache

# Install MySQLi and PDO extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure Apache to listen on $PORT environment variable for Render.com compatibility
ENV PORT=80
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Copy project files to Apache web root
COPY . /var/www/html/

EXPOSE 80
