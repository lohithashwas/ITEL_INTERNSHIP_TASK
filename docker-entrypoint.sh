#!/bin/bash
set -e

# Replace the default port 80 with the one provided by Railway ($PORT)
if [ -n "$PORT" ]; then
    echo "Configuring Apache to listen on port $PORT"
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf
fi

# Ensure Apache doesn't crash with the MPM error by removing potentially conflicting configs
# (Common fix for "More than one MPM loaded" in certain Docker environments)
rm -f /etc/apache2/mods-enabled/mpm_event.load
rm -f /etc/apache2/mods-enabled/mpm_worker.load

# Start Apache
exec apache2-foreground
