# Project overview
The software in this repository is one of the main deliverables of the DACEM (Digitizing Academic Catalogues for Enhanced Mobility) project. For more information refer to the [official project website](https://projects.uni-foundation.eu/dacem/). There is a prototype [DACEM portal](https://dacem.eu) you can check to see the working software.

# Technical architecture overview
For information about the technical architecture and use cases, refer to the corresponding project report: (DACEM technical architecture for CCs)[https://drive.google.com/drive/folders/19FfG3cMaiXnyzxSkxoJgHIqxz8UqCBb4]

# API documentation
The DACEM software is capable of exposing curricular data (Institution, Organizational Unit, Programme, Course) in OCCAPI v2 format. Refer to the (API documentation)[https://occapi.uni-foundation.eu/specification/v2] for information on available endpoints and a detailed description of the API specification.

# DACEM installation / deployment guide

## Local development environment

The recommended preconfigured local development setup is using DDEV for containerization.

### Steps to create a local development setup

1. Install DDEV following the [official documentation](https://ddev.com/get-started/).
2. Clone the repository and enter the created directory. `git clone https://github.com/EuropeanUniversityFoundation/dacem.git`
3. Create DDEV configuration by running `ddev config`.
4. Copy the `.env.example` file to `.env`.
5. The default values in `.env` already match what DDEV expects, so most of them can be left as-is.
6. Modify / add administrator user information in the `.env` file on the lines starting with `ACCOUNT_`. You might want to use double quotes when setting the `ACCOUNT_PASS` variable.
7. Go to the `web/sites/default` folder. Copy `example.settings.local.php` to `settings.local.php` and review it.
8. Uncomment the following code block at the end of your `settings.php`
```
  if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
    include $app_root . '/' . $site_path . '/settings.local.php';
  }
```
9. Start the DDEV project with `ddev start` and take note of the URL where your project will be available.
10. Run `ddev composer install`.
11. Run `ddev scripts/config_install.sh`. This will create the database, install and configure the Drupal site.
12. Visit the URL output by the command in step 9 to test if the site works as expected. Default URL is `https://dacem.ddev.site`

## Deploying to a server

This section covers deploying DACEM to a traditional **LAMP stack** (Linux, Apache, MySQL/MariaDB, PHP) rather than DDEV. It assumes a dedicated server or VM where you have root/sudo access.

### Server prerequisites

1. **PHP 8.3** (or the version currently required by `web/core/composer.json` — check the `require.php` constraint after cloning) with the extensions Drupal needs: `php-cli`, `php-common`, `php-curl`, `php-gd`, `php-mbstring`, `php-mysql`, `php-xml`, `php-zip`, `php-bcmath`, `php-intl`, `php-opcache`, `php-soap`.
2. **Apache 2.4** with `mod_rewrite` and `mod_headers` enabled, and `AllowOverride All` for the DACEM vhost (Drupal ships its own `.htaccess` in `web/.htaccess` which relies on this).
3. **MySQL 8.0+ or MariaDB 10.6+**, with a database and dedicated user created for DACEM.
4. **Composer 2.x**, installed globally (`composer --version` to confirm).
5. **Git**, to clone/pull the repository.
6. Optionally, **Drush** is already pulled in as a project dependency (`drush/drush` in `composer.json`), so no separate global install is needed; it will be available at `vendor/bin/drush` after `composer install`.

### Steps to deploy

1. **Clone the repository** into a location outside the public webroot's document root ancestry, e.g. `/var/www/dacem`:
   ```
   git clone https://github.com/EuropeanUniversityFoundation/dacem.git /var/www/dacem
   cd /var/www/dacem
   ```
   Only the `web/` subdirectory should ever be exposed by Apache — never point the vhost at the repository root.

2. **Install PHP dependencies** for production (no dev dependencies, optimized autoloader). This also provides the local Drush binary the install script relies on:
   ```
   composer install --no-dev --optimize-autoloader
   ```

3. **Create the database and a dedicated MySQL user:**
   ```sql
   CREATE DATABASE dacem CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   CREATE USER 'dacem'@'localhost' IDENTIFIED BY 'a-strong-password';
   GRANT ALL PRIVILEGES ON dacem.* TO 'dacem'@'localhost';
   FLUSH PRIVILEGES;
   ```

4. **Set up `.env` on the server,** the same way as in local development:
   - Copy `.env.example` to `.env`.
   - Set `DB_NAME`, `DB_USER`, `DB_PASSWORD` to match the database created in step 3.
   - **Set `DB_HOST` to `localhost` (or `127.0.0.1`)** — the example value of `db` is DDEV's internal container hostname and will fail to connect on a plain LAMP server.
   - Set `ACCOUNT_NAME`, `ACCOUNT_MAIL`, and `ACCOUNT_PASS` for the admin account (leave `ACCOUNT_PASS` blank to have Drush generate one).
   - Set `SITE_NAME` and `SITE_MAIL` as appropriate.
   - Set `ENV_NAME=Production` (and comment out `ENV_NAME=Local`), matching the convention already used in `settings.local.php`.

5. **Set up `settings.local.php`,** as in local development: copy `web/sites/default/example.settings.local.php` to `settings.local.php`, review it for this environment, and confirm the include block at the end of `settings.php` (which is tracked in the repo) is uncommented.

6. **Make `web/sites/default` writable** so Drush can generate `settings.php` during install, then run the install script:
   ```
   chmod 775 web/sites/default
   ./scripts/config_install.sh
   ```
   The script reads `.env`, sources `vendor/bin/drush`, shows you the database and site settings it's about to use, and asks for confirmation before running `drush site-install --existing-config`. This installs Drupal using the configuration already tracked in `config/sync`, then resets `web/sites/default`, `settings.php`, and `settings.local.php` to sane permissions (`0755`/`0644`) automatically — no need to set those manually.

   For subsequent deployments to an already-installed site, don't re-run this script; use the standard update sequence instead (see "Notes on updates" below).

7. **Make sure the public files directory is writable** by the web server user (`www-data` on Debian/Ubuntu, `apache` on RHEL/CentOS):
   ```
   chown -R www-data:www-data web/sites/default/files
   ```

8. **Configure the Apache virtual host,** pointing the document root at `web/`:
   ```apache
   <VirtualHost *:80>
     ServerName dacem.example.org
     DocumentRoot /var/www/dacem/web

     <Directory /var/www/dacem/web>
       AllowOverride All
       Require all granted
     </Directory>
   </VirtualHost>
   ```
   Enable the site and reload Apache:
   ```
   a2ensite dacem.conf
   systemctl reload apache2
   ```

9. **Enable HTTPS.** Use [Certbot](https://certbot.eff.org/) (or your certificate provider of choice) to obtain a TLS certificate for the domain and configure Apache to redirect HTTP to HTTPS.

10. **Set up a cron job** for Drupal's scheduled tasks, running as the web server user or a dedicated deploy user:
    ```
    */15 * * * * cd /var/www/dacem && vendor/bin/drush cron > /dev/null 2>&1
    ```

11. **Set up backups.** The repository includes a `backup/` folder and a `scripts/generate_backup.sh` script, with retention controlled by the `KEEP_PREVIOUS_*` variables in `.env`. Schedule this via cron as well, and make sure backups are copied off-server.

12. **Verify the deployment** by visiting the configured domain, confirming the site loads over HTTPS, and checking `/admin/reports/status` (Drupal's status report) for any outstanding warnings — file system permissions and cron are the two most common issues to double-check after a first LAMP deployment.

## Notes on updates (for both deployment options)

This repository will be continouosly maintained installing the latest updates of Drupal core and contributed modules. You can update your own installation by using the commands below. It is recommended to back up your site before any updates.

For subsequent deployments to an already-installed site, don't re-run `scripts/config_install.sh` — it performs a fresh `site-install` and will overwrite the site. Instead use the standard update sequence:
```
git pull
composer install --no-dev --optimize-autoloader
vendor/bin/drush updatedb
vendor/bin/drush config-import
vendor/bin/drush cache-rebuild
```
Consider putting the site in maintenance mode (`drush state:set system.maintenance_mode 1`) before running updates on a live site, and taking it out afterward.

Check `/admin/reports/status` to see a brief site status overview and test your site.
