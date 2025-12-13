FROM php:8.1-apache

# Installer les dépendances système et les extensions PHP
RUN apt-get update && apt-get install -y \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Installer les extensions PHP via docker-php-ext-install
RUN docker-php-ext-install pdo pdo_mysql

# Copier les fichiers PHP dans le conteneur
COPY src/ /var/www/html/

# Donner les bonnes permissions
RUN chown -R www-data:www-data /var/www/html

# Activer mod_rewrite pour Apache
RUN a2enmod rewrite

EXPOSE 80

CMD ["apache2-foreground"]
