FROM prestashop/prestashop:latest

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
USER root