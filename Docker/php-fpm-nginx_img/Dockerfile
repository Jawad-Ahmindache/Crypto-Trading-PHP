FROM jahmindache/php-8-fpm-zts_img
RUN apt update -y && apt upgrade -y
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
   && php -r "if (hash_file('sha384', 'composer-setup.php') === '55ce33d7678c5a611085589f1f3ddf8b3c52d662cd01d4ba75c0ee0459970c2200a51f492d557530c71c15d8dba01eae') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
   && php composer-setup.php \
   && php -r "unlink('composer-setup.php');" \
   && mv composer.phar /usr/local/bin/composer \ 
   && apt install npm -y \
   && apt install libpng-dev -y \ 
   && apt install libcurl4-openssl-dev -y \
   && apt install openssl -y


#PHP EXTENSION
RUN docker-php-ext-install pdo pdo_mysql \ 
    && apt install -y libzip-dev \
    && docker-php-ext-install zip \
    && docker-php-ext-install sockets \
    && docker-php-ext-install gd \
    && docker-php-ext-install curl \
    && pecl install parallel \
    && docker-php-ext-enable parallel  
