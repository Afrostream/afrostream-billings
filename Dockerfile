FROM php:5.6-fpm-alpine

# Packages
RUN apk --update add \
    autoconf \
    build-base \
    curl \
    git \
    subversion \
    freetype-dev \
    libjpeg-turbo-dev \
    libmcrypt-dev \
    libpng-dev \
    libbz2 \
    libstdc++ \
    libxslt-dev \
    openldap-dev \
    nginx \
    make \
    unzip \
    postgresql-dev \
    wget && \
    docker-php-ext-install bcmath mcrypt zip bz2 mbstring pcntl xsl && \
    docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ && \
    docker-php-ext-install gd && \
    docker-php-ext-install soap && \
    docker-php-ext-install pgsql && \
    apk del build-base && \
    rm -rf /var/cache/apk/*

# PEAR tmp fix
RUN echo "@testing http://dl-4.alpinelinux.org/alpine/edge/testing/" >> /etc/apk/repositories && \
    apk add --update php5-pear@testing && \
    rm -rf /var/cache/apk/*

# Memory Limit
RUN echo "memory_limit=-1" > $PHP_INI_DIR/conf.d/memory-limit.ini
RUN echo "always_populate_raw_post_data=-1" > $PHP_INI_DIR/conf.d/custom.ini

# Time Zone
RUN echo "date.timezone=${PHP_TIMEZONE:-UTC}" > $PHP_INI_DIR/conf.d/date_timezone.ini

# Register the COMPOSER_HOME environment variable
ENV COMPOSER_HOME /composer

# Add global binary directory to PATH and make sure to re-export it
ENV PATH /composer/vendor/bin:$PATH

# Allow Composer to be run as root
ENV COMPOSER_ALLOW_SUPERUSER 1

# Set up the volumes and working directory
VOLUME ["/app"]
WORKDIR /app

# Setup the Composer installer
RUN curl -o /tmp/composer-setup.php https://getcomposer.org/installer \
  && curl -o /tmp/composer-setup.sig https://composer.github.io/installer.sig \
  && php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { unlink('/tmp/composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }"

COPY nginx.conf /etc/nginx/
COPY entrypoint.sh /

RUN ln -sf /dev/stderr /var/log/php-fpm.log
RUN ln -sf /dev/stderr /var/log/nginx/access.log
RUN ln -sf /dev/stderr /var/log/nginx/error.log

# Set up the command arguments
ENTRYPOINT [ "/entrypoint.sh" ]