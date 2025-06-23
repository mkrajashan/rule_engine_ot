#!/bin/bash
php -d display_errors=0 -d error_reporting=6143 -d log_errors=0 ./vendor/bin/phpunit "$@" 2>/dev/null
