#!/bin/sh

/app/composer.phar install
mkdir /run/nginx
nginx
php-fpm
