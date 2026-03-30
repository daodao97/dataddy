FROM composer:2 AS composer

FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
        runit \
        sudo \
        tzdata \
        postgresql-libs \
        linux-headers \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" \
        mysqli \
        pdo_mysql \
        pgsql \
        pdo_pgsql \
    && pecl install mongodb yaf \
    && docker-php-ext-enable mongodb yaf \
    && apk del .build-deps \
    && rm -rf /tmp/pear /var/cache/apk/*

RUN printf "yaf.use_namespace = On\ndate.timezone = Asia/Shanghai\n" \
    > /usr/local/etc/php/conf.d/dataddy.ini

RUN addgroup -S www && adduser -S -G www www \
    && chmod u+s "$(which crontab)" \
    && chown root:www /usr/sbin/crond \
    && chmod 4755 /usr/sbin/crond

ENV TZ=Asia/Shanghai

RUN ln -snf /usr/share/zoneinfo/"$TZ" /etc/localtime \
    && echo "$TZ" > /etc/timezone

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /yaf

CMD ["runsvdir", "/yaf/docker/service"]
