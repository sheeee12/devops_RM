# 1. On prend Apache avec PHP 8.2
FROM php:8.2-apache

# 2. On installe les extensions pour que PHP puisse parler à MySQL
RUN docker-php-ext-install pdo pdo_mysql

# 3. On active le mode rewrite d'Apache (souvent utile en PHP)
RUN a2enmod rewrite

# 4. On copie ton code (qui contient maintenant le dossier /vendor créé par Jenkins)
COPY . /var/www/html/

# 5. Sécurité : On donne la propriété des fichiers à l'utilisateur web
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80