# syntax=docker/dockerfile:1.7
FROM php:8.3-apache-bookworm

ARG INSTALL_NATIVE_API=true

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates; \
    php -m | grep -qi '^sodium$'; \
    if [ "${INSTALL_NATIVE_API}" = "true" ]; then \
      apt-get install -y --no-install-recommends python3 python3-venv; \
      python3 -m venv /opt/librouteros-venv; \
      /opt/librouteros-venv/bin/pip install --no-cache-dir --upgrade pip; \
      /opt/librouteros-venv/bin/pip install --no-cache-dir librouteros; \
    fi; \
    rm -rf /var/lib/apt/lists/*

ENV PATH="/opt/librouteros-venv/bin:${PATH}"

COPY . /var/www/html/
COPY build/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY build/php.ini /usr/local/etc/php/conf.d/mikrotik-wireguard.ini
COPY build/entrypoint.sh /usr/local/bin/mikrotik-wireguard-entrypoint

RUN set -eux; \
    php -l /var/www/html/src/WireGuardManager.php; \
    php -r 'require "/var/www/html/src/WireGuardManager.php"; $s=WireGuardManager::generateConfig("10.0.0.2", "private", "public", "vpn.example:51820", "10.0.0.0/24", "1.1.1.1, 1.0.0.1"); if (strpos($s, "DNS = 1.1.1.1, 1.0.0.1") === false || strpos($s, "Endpoint = vpn.example:51820") === false) { exit(1); }'; \
    sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf; \
    a2enmod deflate expires headers setenvif; \
    chmod +x /usr/local/bin/mikrotik-wireguard-entrypoint; \
    mkdir -p /data /var/www/html/configs; \
    chown -R www-data:www-data /var/www/html /data

EXPOSE 8080
VOLUME ["/data"]
ENTRYPOINT ["mikrotik-wireguard-entrypoint"]
CMD ["apache2-foreground"]
