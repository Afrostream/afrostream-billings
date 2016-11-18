#!/bin/sh

if [ ! -e /app/composer.phar ]
then
  php /tmp/composer-setup.php
fi
/app/composer.phar install
mkdir /run/nginx
nginx
php-fpm
