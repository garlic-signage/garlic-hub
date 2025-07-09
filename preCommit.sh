#!/bin/bash

# execute this script before every commit

# shellcheck disable=SC2034 # needed not to optimize testing speed look at https://thephp.cc/articles/pcov-or-xdebug
XDEBUG_MODE=coverage
php vendor/bin/phpunit --coverage-html public/clover/ --coverage-clover  public/clover/clover.xml
vendor/bin/phpstan analyze
vendor/bin/coverage-badge public/clover/clover.xml misc/coverage.svg coverage