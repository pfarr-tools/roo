#!/bin/sh

set -eu

php-fpm --test >/dev/null 2>&1
