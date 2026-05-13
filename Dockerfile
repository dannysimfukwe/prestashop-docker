FROM prestashop/prestashop:latest

COPY entrypoint.sh /entrypoint.sh
COPY cleanup.php /var/www/html/cleanup.php
RUN chmod +x /entrypoint.sh \
    && chmod 644 /var/www/html/cleanup.php

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]