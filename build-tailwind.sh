#!/bin/sh
# Rebuilds public_html/tailwind.css from public_html/tailwind-src.css using the
# standalone Tailwind CLI installed on wserver (/usr/local/bin/tailwindcss).
# Run this after changing tailwind-src.css or after adding/removing utility
# classes in any .php file under public_html/ or lib/.
set -eu

REMOTE_ROOT="/home/www/parking.cweb.com.au"

ssh wserver "tailwindcss \
  -i '$REMOTE_ROOT/public_html/tailwind-src.css' \
  -o '$REMOTE_ROOT/public_html/tailwind.css' \
  --minify"

echo "Rebuilt public_html/tailwind.css"
