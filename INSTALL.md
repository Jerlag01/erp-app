# erp-app – installation guide

How to install the ERP (employee side at `/`, administration at `/admin`) on a new server, from an empty machine to a working HTTPS site, and how to update it to a new release.

- **Repository:** https://github.com/Jerlag01/erp-app (branch `main`)
- **Stack:** Laravel 13, Filament 5, PHP 8.4, MySQL/MariaDB, nginx. PDFs are made with dompdf. No Node.js build is needed.
- **Example names in this guide:** domain `erp.example.be`, install path `/var/www/erp-app`, database `erp`, database user `erp`. Replace them with your own values.
- All commands run as `root` unless they start with `sudo -u www-data`.

---

## 1. Requirements

| Component | Version / note |
|---|---|
| OS | Ubuntu 24.04 LTS or Debian 12 (other Linux works, package names may differ) |
| PHP | **8.4** with FPM |
| PHP extensions | bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, mysql (pdo_mysql), openssl, tokenizer, xml, zip |
| Database | MySQL 8.0+ or MariaDB 10.6+ |
| Web server | nginx |
| Composer | 2.x |
| Cron | for the scheduler (price updates, payment reminders, weekly prestation sheets) |
| Other | git, unzip, certbot (Let's Encrypt) |

- `intl` is required by Filament (the panels will not load without it).
- `gd` is used for the PDFs (images, letterheads) and `zip` for the "all documents" download of battery dossiers.

---

## 2. Install the server packages

```bash
apt update
apt install -y software-properties-common ca-certificates curl unzip git
add-apt-repository -y ppa:ondrej/php        # Debian: use https://packages.sury.org/php instead
apt update
apt install -y php8.4-fpm php8.4-cli php8.4-bcmath php8.4-curl php8.4-gd php8.4-intl \
               php8.4-mbstring php8.4-mysql php8.4-xml php8.4-zip php8.4-opcache
apt install -y nginx mysql-server certbot python3-certbot-nginx     # or: mariadb-server

curl -sS https://getcomposer.org/installer | php8.4 -- --install-dir=/usr/local/bin --filename=composer
php8.4 -m | grep -E 'intl|pdo_mysql|mbstring|gd|zip'
```

---

## 3. Database

```bash
mysql -u root
```

```sql
CREATE DATABASE erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'erp'@'localhost' IDENTIFIED BY 'CHANGE-ME-strong-password';
GRANT ALL PRIVILEGES ON erp.* TO 'erp'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> Use a database of its own for the ERP. Never run `migrate:fresh` (or the test suite with a changed configuration) against a database that holds real data or is shared with other applications: it drops **all** tables in it.

---

## 4. Get the code

```bash
mkdir -p /var/www
git clone https://github.com/Jerlag01/erp-app.git /var/www/erp-app
cd /var/www/erp-app
git config --global --add safe.directory /var/www/erp-app    # lets root run git in this folder later (it is owned by www-data)
```

The repository is private: use a GitHub deploy key (read-only, recommended for a server) or a personal access token for the clone.

Not in git (and therefore created during the install): `.env`, `vendor/`, `public/storage` and everything stored in `storage/`.

---

## 5. Configuration (`.env`)

```bash
cd /var/www/erp-app
cp .env.example .env
nano .env
```

Set at least these values:

```dotenv
APP_NAME="ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.example.be
APP_LOCALE=nl
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=nl_BE

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp
DB_USERNAME=erp
DB_PASSWORD="CHANGE-ME-strong-password"

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

# Mail: can stay on "log" here; the real SMTP settings are entered in the admin panel
# (Settings > Email) and override these.
MAIL_MAILER=log
MAIL_FROM_ADDRESS="erp@example.be"
MAIL_FROM_NAME="${APP_NAME}"
```

Notes:
- `QUEUE_CONNECTION=sync`: mails and PDFs are made right away, no queue worker is needed.
- `SESSION_SECURE_COOKIE=true` requires HTTPS (section 10). While testing over plain HTTP, set it to `false`.
- `APP_KEY` is filled in by the next step. **Keep it safe and never change it on a running install**: the national register numbers and IBANs of the employee register, the SMTP password and the Peppol API key are stored encrypted with it.

---

## 6. Install the application

```bash
cd /var/www/erp-app

composer install --no-dev --optimize-autoloader --no-interaction
php8.4 artisan key:generate --force          # only on a new install, never on a moved site (section 14)

php8.4 artisan vendor:publish --tag=livewire:assets --force
php8.4 artisan filament:assets

# Tables and the standard data: project categories (600–650), waste codes, battery models and
# cables, intervention categories, standard invoice lines, and the 1974 COW sites with their
# areas (database/data/work_sites.json; only missing COW codes are added)…
php8.4 artisan migrate --force

php8.4 artisan storage:link
```

---

## 7. First administrator

The ERP has no sign-up page. Create the first administrator with:

```bash
cd /var/www/erp-app
php8.4 artisan erp:create-user --admin
```

It asks for the name, e-mail address and password. Without `--admin` the user gets the standard employee role. Further accounts are made in the admin panel (Users), or automatically with a welcome mail when an employee is added to the employee register.

---

## 8. File permissions and upload size

```bash
chown -R www-data:www-data /var/www/erp-app
find /var/www/erp-app/storage /var/www/erp-app/bootstrap/cache -type d -exec chmod 775 {} \;
```

Run every later `artisan` command as root, then repeat the `chown` above, or run artisan as the web user (`sudo -u www-data php8.4 artisan …`).

**Uploads.** Project files (plans, schematics, pictures), certificates and knowledge-base images can be large. PHP allows only 2 MB by default:

```bash
printf 'upload_max_filesize = 100M\npost_max_size = 110M\n' > /etc/php/8.4/fpm/conf.d/99-uploads.ini
systemctl restart php8.4-fpm
php-fpm8.4 -i | grep -E 'upload_max_filesize|post_max_size'
```

**PHP-FPM sandbox.** Only when the app lives under `/usr/…` (for example `/usr/share/nginx/html/…`) and you get a 500 with "read-only file system":

```bash
mkdir -p /etc/systemd/system/php8.4-fpm.service.d
cat > /etc/systemd/system/php8.4-fpm.service.d/erp-app-writable.conf <<'EOF'
[Service]
ReadWritePaths=/usr/share/nginx/html/devops/erp-app/storage /usr/share/nginx/html/devops/erp-app/bootstrap/cache
EOF
systemctl daemon-reload && systemctl restart php8.4-fpm
```

---

## 9. nginx

Create `/etc/nginx/sites-available/erp-app`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name erp.example.be;
    root /var/www/erp-app/public;

    index index.php;
    charset utf-8;
    client_max_body_size 110m;

    access_log /var/log/nginx/erp-app.access.log;
    error_log  /var/log/nginx/erp-app.error.log;

    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
    add_header Referrer-Policy same-origin;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120s;     # year PDFs of the prestation overview can take a while
    }

    # Static files: cache long, but fall back to Laravel when the file does not exist
    location ~* \.(?:ico|css|js|mjs|map|jpe?g|png|webp|gif|svg|woff2?)$ {
        try_files $uri /index.php?$query_string;
        expires 365d;
        access_log off;
    }

    # Keep Let's Encrypt working, block every other dot-file (.env, .git…)
    location ^~ /.well-known/acme-challenge/ { allow all; }
    location ~ /\.(?!well-known) { deny all; }
}
```

```bash
ln -s /etc/nginx/sites-available/erp-app /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

---

## 10. HTTPS

Point the DNS records of the domain to the server, then:

```bash
certbot --nginx -d erp.example.be --redirect
```

---

## 11. Scheduler (cron)

Several features run on a schedule: the daily stock price update (05:30), the automatic payment reminders (08:00) and the weekly prestation sheets by e-mail (day and time from Settings > Prestations, default Friday 18:00). Add the scheduler once, as the web user:

```bash
(crontab -u www-data -l 2>/dev/null; echo '* * * * * cd /var/www/erp-app && php8.4 artisan schedule:run >> /dev/null 2>&1') | crontab -u www-data -
crontab -u www-data -l
cd /var/www/erp-app && php8.4 artisan schedule:list
```

---

## 12. Production optimisation

```bash
cd /var/www/erp-app
php8.4 artisan optimize          # config, routes, views, events cache
php8.4 artisan filament:optimize # Filament component + icon cache
chown -R www-data:www-data /var/www/erp-app
systemctl restart php8.4-fpm     # clears OPcache
```

After **every** code or `.env` change, run these again: with the caches on, changes do not show until the caches are rebuilt. (`php8.4 artisan optimize:clear` removes the caches, for example while testing.)

---

## 13. First steps in the admin panel

Go to `https://erp.example.be/admin` and log in with the account from section 7.

1. **Settings > General:** site name, company name, warning days.
2. **Settings > Modules:** switch on the modules you use (fleet, stock, IT, work sites, projects, planning, interventions, batteries, prestations, finance, waste, knowledge base…). Every module can be switched off again at any time.
3. **Settings > Email:** SMTP server and sender, then **Send test email**. Mails are welcome mails, planning changes, invoices, payment reminders and the weekly prestation sheets.
4. **Settings > Appearance:** colours, logo, favicon.
5. The tabs of the modules you switched on (shown only for active modules):
   - **Finance:** company details, VAT number, IBAN, numbering, reminders, Peppol.
   - **Planning:** timeslots and the e-mail addresses that get a copy of planning changes.
   - **Prestations:** hours per week and day, lunch break, mobility allowance, and who receives the weekly PDFs.
   - **Batteries:** letterhead and other templates, client details, prices, and the fixed texts of the shipping note.
6. **System > Email templates:** the texts of every e-mail the ERP sends, per language.
7. **Roles:** give each role the rights it needs. Employees see a module only with its rights (finance not at all by default). Administrators can do everything.
8. **Holidays**, **Teams**, **Users** / the **employee register**.

---

## 14. Moving an existing install to a new server

On the **old** server:

```bash
cd /path/to/erp-app
mysqldump --single-transaction --routines -u <user> -p erp > /tmp/erp.sql
tar czf /tmp/erp-storage.tgz -C storage app       # all uploads and generated PDFs (private and public)
grep ^APP_KEY= .env                               # copy this value
```

On the **new** server, follow sections 1–6 but:
- **do not** run `key:generate`: put the old `APP_KEY` in `.env` (otherwise the encrypted employee data, SMTP password and Peppol key cannot be read);
- import the database and the files instead of starting empty, then run `migrate` to apply any newer migrations:

```bash
mysql -u erp -p erp < /tmp/erp.sql
tar xzf /tmp/erp-storage.tgz -C /var/www/erp-app/storage
cd /var/www/erp-app
php8.4 artisan migrate --force
php8.4 artisan storage:link
```

Then continue with sections 7 (only if you need a new admin) and 8–12. Change `APP_URL` if the domain changes.

---

## 15. Updating to a new release

New releases are published on the `main` branch of https://github.com/Jerlag01/erp-app. A release can also carry a tag (for example `v1.2.0`); then you can install exactly that release.

### 15.1 Before the update

```bash
cd /var/www/erp-app
mysqldump --single-transaction -u erp -p erp > /root/erp-$(date +%F).sql   # database backup
git fetch --tags origin
git log --oneline HEAD..origin/main            # what the update brings
git status                                     # must be clean: no files changed on the server
git rev-parse --short HEAD                     # note the current version, for a rollback
```

### 15.2 Update

```bash
cd /var/www/erp-app
php8.4 artisan down                            # maintenance page
git merge --ff-only origin/main                # latest release
#   or, for one tagged release instead:  git checkout v1.2.0
composer install --no-dev --optimize-autoloader --no-interaction
php8.4 artisan vendor:publish --tag=livewire:assets --force
php8.4 artisan filament:assets
php8.4 artisan migrate --force                 # new tables and standard data of new modules
php8.4 artisan optimize && php8.4 artisan filament:optimize
chown -R www-data:www-data /var/www/erp-app
systemctl restart php8.4-fpm                   # clears OPcache
php8.4 artisan up
```

After the update:
- New modules arrive switched on. Check **Settings > Modules** and the new settings tabs, and give the roles the new rights.
- Check that the scheduler still lists everything: `php8.4 artisan schedule:list`.

`git merge --ff-only` stops when files were changed on the server itself. Undo those changes (`git checkout -- <file>`) or move them into the repository first; never edit code directly on the server.

### 15.3 Going back to the previous version

```bash
cd /var/www/erp-app
php8.4 artisan down
git checkout <the version noted in 15.1>
mysql -u erp -p erp < /root/erp-<date>.sql      # only when the update ran migrations
composer install --no-dev --optimize-autoloader --no-interaction
php8.4 artisan optimize && php8.4 artisan filament:optimize
chown -R www-data:www-data /var/www/erp-app
systemctl restart php8.4-fpm
php8.4 artisan up
```

Afterwards `git checkout main` brings the folder back on the branch for the next update.

---

## 16. Backups

Back up these three things; everything else can be rebuilt from git:

| What | How |
|---|---|
| Database | `mysqldump --single-transaction -u erp -p erp > erp-$(date +%F).sql` |
| Files | `storage/app/` – private files (project files, certificates, attachments, signatures, battery and prestation PDFs) and `storage/app/public/` (logo, letterhead, images) |
| Configuration | `.env` (contains `APP_KEY` and the database password) |

---

## 17. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| HTTP 500, nothing in `storage/logs` | PHP cannot write to `storage/`: fix ownership (section 8), or add the systemd `ReadWritePaths` drop-in |
| 500 with *The "intl" PHP extension is required…* | `apt install php8.4-intl && systemctl restart php8.4-fpm` |
| Panels load without styling / buttons do nothing | Livewire/Filament assets give 404: check the static `location` block (section 9), run `php8.4 artisan vendor:publish --tag=livewire:assets --force` and `php8.4 artisan filament:assets` |
| Uploads fail above 2 MB | PHP upload limits, see section 8 |
| Weekly prestation PDFs, reminders or price updates do not run | The cron of section 11 is missing: `crontab -u www-data -l`, `php8.4 artisan schedule:list` |
| Changes do not show up | Caches are on: `php8.4 artisan optimize:clear`, then `optimize` + `filament:optimize` again, then restart php8.4-fpm |
| Mails not arriving | Settings > Email > **Send test email**; errors are in `storage/logs/laravel.log` |
| Employee data or SMTP password unreadable after a move | `APP_KEY` differs from the old server (section 14) |
| Logged out immediately / 419 errors | `SESSION_SECURE_COOKIE=true` on a site without HTTPS, or a wrong `APP_URL` |

Logs: `storage/logs/laravel.log` (application), `/var/log/nginx/erp-app.error.log` (nginx), `journalctl -u php8.4-fpm` (PHP-FPM).

---

## 18. Final checklist

- [ ] `https://erp.example.be` shows the login; `/admin` logs in with the first admin
- [ ] Settings > Email > **Send test email** arrives
- [ ] Uploading a file of 20 MB in a project works
- [ ] `php8.4 artisan schedule:list` shows the scheduled tasks and the cron of `www-data` exists
- [ ] A PDF opens (for example a week of the prestation sheet)
- [ ] `APP_ENV=production` and `APP_DEBUG=false` in `.env`
- [ ] Backups of the database, `storage/app` and `.env` are scheduled
