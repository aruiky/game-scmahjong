FROM php:8.5.11-apache

# PHP 页面、接口和前端静态资源
COPY html/ /var/www/html/

# NAS 上传文件的权限位可能无法被 Apache/PHP 用户读取，构建时统一设置只读网页权限。
RUN find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && mkdir -p /app/data

RUN php -l /var/www/html/index.php \
    && php -l /var/www/html/api.php \
    && php -l /var/www/html/game.php \
    && php -l /var/www/html/history.php \
    && php -l /var/www/html/history_store.php

EXPOSE 80

ENV MAHJONG_DATA_DIR=/app/data
ENTRYPOINT ["/bin/sh", "-c", "mkdir -p \"$MAHJONG_DATA_DIR\" && chown www-data:www-data \"$MAHJONG_DATA_DIR\" && if [ -e \"$MAHJONG_DATA_DIR/games.json\" ]; then chown www-data:www-data \"$MAHJONG_DATA_DIR/games.json\"; fi && exec docker-php-entrypoint \"$@\"", "--"]
CMD ["apache2-foreground"]
