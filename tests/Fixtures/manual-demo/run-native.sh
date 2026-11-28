#!/bin/sh
set -eu
php -d memory_limit=128M /demo/seed-fixture.php
php -d memory_limit=128M /demo/print-pdf-maintenance.php prepare
php -d memory_limit=128M /demo/print-pdf-maintenance.php commit
pdftotext /app/storage/app/cap-management/beneficiary_payment_list_1_202611.pdf /tmp/payment-list.txt
php -d memory_limit=128M /demo/check-fixture.php
php -d memory_limit=128M /demo/print-pdf-maintenance.php undo
php -d memory_limit=128M /demo/check-fixture.php restored
