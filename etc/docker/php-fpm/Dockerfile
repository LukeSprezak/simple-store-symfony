FROM php:8.5.11-fpm-bookworm

ENV TZ=Europe/Warsaw
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions gd xdebug pdo_mysql mbstring bcmath xml zip intl sysvsem apcu memcached amqp redis mongodb

RUN apt-get -y update \
    && apt-get -y --no-install-recommends install git nano curl librabbitmq-dev libssh-dev \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash
RUN apt-get install -y --no-install-recommends symfony-cli

RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g nodemon yarn

RUN usermod -u 1000 www-data

RUN mkdir -p /var/www

WORKDIR /var/www

EXPOSE 9000
