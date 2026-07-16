
FROM php:8.1-apache

# تثبيت امتدادات PHP المطلوبة
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mysqli gd \
    && rm -rf /var/lib/apt/lists/*

# تفعيل mod_rewrite و mod_headers لـ Apache
RUN a2enmod rewrite headers

# إعدادات Apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# إعدادات PHP الآمنة (Production-ready)
RUN echo "display_errors = Off" >> /usr/local/etc/php/php.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/php.ini \
    && echo "error_log = /var/log/php_errors.log" >> /usr/local/etc/php/php.ini \
    && echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT" >> /usr/local/etc/php/php.ini \
    && echo "expose_php = Off" >> /usr/local/etc/php/php.ini \
    && echo "file_uploads = On" >> /usr/local/etc/php/php.ini \
    && echo "upload_max_filesize = 2M" >> /usr/local/etc/php/php.ini \
    && echo "post_max_size = 3M" >> /usr/local/etc/php/php.ini \
    && echo "max_input_time = 30" >> /usr/local/etc/php/php.ini \
    && echo "max_execution_time = 30" >> /usr/local/etc/php/php.ini \
    && echo "session.cookie_httponly = 1" >> /usr/local/etc/php/php.ini \
    && echo "session.cookie_samesite = Strict" >> /usr/local/etc/php/php.ini \
    && echo "session.use_strict_mode = 1" >> /usr/local/etc/php/php.ini \
    && echo "session.use_only_cookies = 1" >> /usr/local/etc/php/php.ini \
    && echo "session.sid_length = 48" >> /usr/local/etc/php/php.ini \
    && echo "session.sid_bits_per_character = 6" >> /usr/local/etc/php/php.ini \
    && echo "session.gc_maxlifetime = 3600" >> /usr/local/etc/php/php.ini \
    && echo "allow_url_fopen = Off" >> /usr/local/etc/php/php.ini \
    && echo "allow_url_include = Off" >> /usr/local/etc/php/php.ini \
    && echo "disable_functions = exec,passthru,system,proc_open,popen,parse_ini_file,show_source" >> /usr/local/etc/php/php.ini

# نسخ ملفات التطبيق
COPY api/ /var/www/html/api/
COPY includes/ /var/www/html/includes/
COPY init-passwords.php /var/www/html/init-passwords.php

# إنشاء مجلد uploads بصلاحيات مقيّدة
RUN mkdir -p /var/www/html/uploads \
    && chown www-data:www-data /var/www/html/uploads \
    && chmod 750 /var/www/html/uploads

# سكربت بدء التشغيل: تهيئة كلمات المرور ثم تشغيل Apache
RUN echo '#!/bin/bash\nphp /var/www/html/init-passwords.php\napache2-foreground' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

# تعيين مجلد العمل
WORKDIR /var/www/html

EXPOSE 80

CMD ["start.sh"]
