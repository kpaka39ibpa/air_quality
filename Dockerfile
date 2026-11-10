FROM php:8.2-apache

# Включаем модуль rewrite и разрешаем Apache доступ к монтируемой директории
RUN a2enmod rewrite

RUN echo '<Directory /var/www/html/>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    DirectoryIndex index.html index.php\n\
</Directory>' > /etc/apache2/conf-available/override.conf \
    && a2enconf override

EXPOSE 80