#!/usr/bin/env bash

# Same caching step as the Ploi deploy script.
/usr/bin/php /var/www/html/artisan optimize --no-ansi -q
