FROM prestashop/prestashop:latest

# Enable Apache mod_rewrite for pretty URLs
RUN a2enmod rewrite

EXPOSE 80