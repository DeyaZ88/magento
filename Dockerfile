FROM payfortstart/magento-app:db

RUN mkdir -p /usr/src/magento
COPY . /usr/src/magento
RUN chown -R www-data:www-data /usr/src/magento
RUN tar cf - -C /usr/src/magento . | tar xf -
RUN rm -rf var/cache/*
