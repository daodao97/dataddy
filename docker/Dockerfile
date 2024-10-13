FROM composer:latest as composer

FROM newfuture/yaf:fpm

RUN apk update && \
    apk add --no-cache runit sudo tzdata \
    php7-mysqli php7-mbstring php7-tokenizer php7-pgsql php7-pdo_pgsql php7-mongodb 

RUN echo "yaf.use_namespace = On" >> /etc/php7/conf.d/yaf.ini

RUN chmod u+s $(which crontab)      

RUN rm -rf /var/cache/apk/*

# 设置所需的时区，例如亚洲/上海
ENV TZ=Asia/Shanghai

# 创建软链接，指向你想要的时区文件
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

COPY --from=composer /usr/bin/composer /usr/bin/composer

ENTRYPOINT [ "/entry.sh" ]

CMD ["runsvdir", "/yaf/docker/service"]