#!/bin/bash
set -e

# Replace the default port 80 with the one provided by Railway ($PORT)
if [ -n "$PORT" ]; then
    echo "Configuring Apache to listen on port $PORT"
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
    sed -i "s/VirtualHost \*:80/VirtualHost \*:$PORT/g" /etc/apache2/sites-available/000-default.conf
fi

# Force mpm_prefork and disable others to prevent crashes
# This resolves "More than one MPM loaded" and syntax errors in mpm_event.conf
echo "Applying MPM fix..."
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf
rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load

# Start Apache
exec apache2-foreground
