#FROM centos:latest
FROM rockylinux:8

# Install apache, PHP, and supplimentary programs. openssh-server, curl, and lynx-cur are for debugging the container.
RUN yum update -y
RUN yum install -y httpd
RUN yum install -y php php-fpm php-json php-devel curl wget openssh-server net-tools procps

# Create dir for php-fpm
RUN mkdir /run/php-fpm; mkdir /startup

# Copy SSH_Config
COPY sshd_config /etc/ssh/
COPY startup_container.sh /startup
COPY ssh_setup.sh /startup
RUN chmod +x /startup/startup_container.sh \
    && chmod +x /startup/ssh_setup.sh \
    && echo "root:Docker!" | chpasswd \
    && (sleep 1;/startup/ssh_setup.sh 2>&1 > /dev/null)


# Redirect httpd logs to stdout
RUN ln -sf /proc/self/fd/1 /var/log/httpd/access_log && ln -sf /proc/self/fd/1 /var/log/httpd/error_log

# To expose env variables to php
RUN sed -i "s|;*clear_env\s*=\s*yes|clear_env = no|g" /etc/php-fpm.d/www.conf
RUN sed -i "s|;*clear_env\s*=\s*no|clear_env = no|g" /etc/php-fpm.d/www.conf

# Replace for FPM TCP
# /etc/httpd/conf.d/php.conf SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost" SetHandler "proxy:fcgi://127.0.0.1:9000"
# /etc/php-fpm.d/www.conf listen = /run/php-fpm/www.sock listen = 127.0.0.1:9000
RUN sed -i "s|;*listen\s*=\s*/run/php-fpm/www.sock|listen = 127.0.0.1:9000|g" /etc/php-fpm.d/www.conf
RUN sed -i 's,SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost",SetHandler "proxy:fcgi://127.0.0.1:9000",g' /etc/httpd/conf.d/php.conf

# Copy PHP page and delete index.html
COPY index.php /var/www/html/index.php
COPY info.php /var/www/html/info.php
COPY styles.css /var/www/html/styles.css
COPY favicon.ico /var/www/html/favicon.ico
COPY app_arch.png /var/www/html/app_arch.png
COPY OpenHackLogoTP.png /var/www/html/OpenHackLogoTP.png
COPY wth-logo.png /var/www/html/wth-logo.png
COPY yada-logo.png /var/www/html/yada-logo.png
COPY fta-logo.png /var/www/html/fta-logo.png

# Expose apache.
EXPOSE 80 2222

# Create healthcheck pages
RUN echo '{"health": "OK"}' >/var/www/html/healthcheck.html
RUN echo "<?php phpinfo() ?>" > /var/www/html/healthcheck.php

# By default start up apache in the foreground, override with /bin/bash for interactive.
#CMD /usr/sbin/php-fpm -D; /usr/sbin/httpd -D FOREGROUND

ENTRYPOINT ["/startup/startup_container.sh"]