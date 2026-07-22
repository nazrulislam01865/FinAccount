# Local XAMPP installation commands

Run these commands from the extracted FleetManagement project directory.

```bash
pkill -f "php artisan serve" 2>/dev/null || true
pkill -f "php artisan migrate" 2>/dev/null || true

sudo /Applications/XAMPP/xamppfiles/xampp stopmysql || true
sudo /Applications/XAMPP/xamppfiles/xampp startmysql

/Applications/XAMPP/xamppfiles/bin/php artisan up
/Applications/XAMPP/xamppfiles/bin/php artisan optimize:clear
/Applications/XAMPP/xamppfiles/bin/php artisan migrate --force
/Applications/XAMPP/xamppfiles/bin/php artisan optimize:clear

rm -f public/storage
/Applications/XAMPP/xamppfiles/bin/php artisan storage:link

/Applications/XAMPP/xamppfiles/bin/php artisan serve
```

Or use the included helper:

```bash
chmod +x scripts/recover_local_xampp.sh
./scripts/recover_local_xampp.sh "$(pwd)"
/Applications/XAMPP/xamppfiles/bin/php artisan serve
```

Do not run `migrate:fresh` because it deletes existing database data.
