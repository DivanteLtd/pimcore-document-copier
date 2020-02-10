#!/bin/bash

echo -e "\e[34m=> Install packages \e[0m"
php -d memory_limit=-1 /usr/bin/composer install --no-interaction --prefer-dist

echo -e "\e[34m=> Install pimcore \e[0m"

export PIMCORE_ENVIRONMENT=test

bin/console doctrine:database:drop --if-exists --force --env=test
bin/console doctrine:database:create --if-not-exists --env=test

vendor/pimcore/pimcore/bin/pimcore-install \
    --admin-username admin \
    --admin-password admin \
    --mysql-username root \
    --mysql-password root \
    --mysql-database database2 \
    --mysql-host-socket mysql \
    --env=test \
    --no-debug \
    --no-interaction \
    --ignore-existing-config

bin/console pimcore:migrations:migrate -n --allow-no-migration --env=test

echo -e "\e[34m=> Run tests \e[0m"

vendor/bin/codecept run --coverage -c ./codeception.dist.yml
