FROM php:7.1-fpm

RUN apt-get update \
  && apt-get install -y \
      libmcrypt-dev \
      zlib1g-dev \
      libbz2-dev \
      libxslt-dev \
      libjpeg-dev \
      libpng-dev \
      libfreetype6-dev \
      postgresql-server-dev-all \
      nginx \
  && docker-php-ext-install bcmath mcrypt zip bz2 mbstring pcntl xsl \
  && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
  && docker-php-ext-install gd  \
  && docker-php-ext-install soap  \
  && docker-php-ext-install pgsql

#
RUN pecl install apcu
RUN echo "extension=apcu.so" > /usr/local/etc/php/conf.d/apcu.ini

# Time Zone
RUN echo "date.timezone=${PHP_TIMEZONE:-UTC}" > $PHP_INI_DIR/conf.d/date_timezone.ini

# nginx
COPY nginx.conf /etc/nginx/

RUN ln -sf /dev/stderr /var/log/php-fpm.log
RUN ln -sf /dev/stdout /var/log/nginx/access.log
RUN ln -sf /dev/stderr /var/log/nginx/error.log

# create workdir
RUN mkdir -p /opt/billings
WORKDIR /opt/billings

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/tmp --filename=composer
COPY composer.json .
COPY composer.lock .
ENV COMPOSER_ALLOW_SUPERUSER 1
RUN /tmp/composer install

# COPY
COPY . .

#
EXPOSE 80

#
ENTRYPOINT [ "/opt/billings/entrypoint.sh" ]
