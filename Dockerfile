FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli

COPY . /var/www/html/

WORKDIR /var/www/html

CMD ["bash", "-lc", "a2dismod mpm_event mpm_worker 2>/dev/null || true; rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* 2>/dev/null || true; a2enmod mpm_prefork; exec apache2-foreground"]
