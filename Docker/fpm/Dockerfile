
FROM php:8.4-fpm
RUN apt update && apt upgrade -y
RUN apt install -y zip unzip git vim htop


WORKDIR /var/www/html/
COPY . .


EXPOSE  9000
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
ENTRYPOINT ["Docker/fpm/entrypoint.sh"]
CMD ["php-fpm"]