#!/usr/bin/env bash
set -euo pipefail

php_bin="$(command -v php || true)"
if [[ -z "${php_bin}" ]]; then
  echo "php not found in PATH."
  exit 1
fi

entries=(
  "* * * * * cd /var/www/html && ${php_bin} artisan schedule:run >> /dev/null 2>&1"
  "* * * * * cd /var/www/html-homol && ${php_bin} artisan schedule:run >> /dev/null 2>&1"
)

tmp="$(mktemp)"
crontab -l 2>/dev/null > "${tmp}" || true

for entry in "${entries[@]}"; do
  if ! grep -Fq "${entry}" "${tmp}"; then
    echo "${entry}" >> "${tmp}"
  fi
done

crontab "${tmp}"
rm -f "${tmp}"

echo "Cron atualizado com schedule:run para prod e homol."
crontab -l
