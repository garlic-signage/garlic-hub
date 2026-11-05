#!/bin/bash

# coverage only, not to slow down the tests, look at https://thephp.cc/articles/pcov-or-xdebug
XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-html public/clover/ --coverage-clover  public/clover/clover.xml
vendor/bin/phpstan analyze