## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. 

## Clearing cache
php artisan cache:clear
ddcache
php artisan config:cache
php artisan config:clear
php artisan route:clear
php artisan config:cache
php artisan route:cache
php artisan optimize

## Create new module
php artisan module:make Contacts
php artisan module:make-migration create_whatsappwidgets_table embedwhatsapp
php artisan module:make-model Whatsappwidget embedwhatsapp
php artisan module:make-factory ReservationFactory --model=Reservation tablereservations
php artisan tinker 
\Modules\Tablereservations\Models\Reservation::factory()->count(10)->create();

https://github.com/akaunting/laravel-module

## Updates
git diff --name-only ba9c82505fd04ea420f6093c77e3c31fb4766d30 > .diff-files.txt && npm run zipupdate