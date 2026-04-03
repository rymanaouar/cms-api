FROM php:8.0-apache
RUN docker-php-ext-install pdo pdo_mysql
COPY . /var/www/html/
RUN a2enmod rewrite
COPY apache.conf /etc/apache2/sites-available/000-default.conf
EXPOSE 80 
