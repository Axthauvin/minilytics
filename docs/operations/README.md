# Operations and storage

## Dashboard access

On the first dashboard visit, create the initial administrator account. Unauthenticated visitors are redirected to the login page.

Administrators can create a single-use invitation link for a collaborator. It expires after seven days. Public registration is not available.

## Data

Minilytics stores its local configuration in a data directory outside the web root: `MINILYTICS_DATA_DIR` if set, otherwise `minilytics-data/` next to the web root.

By default, each website has its own SQLite database. You can switch the analytics store in **Settings → Database** to MySQL or MariaDB; Minilytics then creates isolated tables per website in the configured database. `sites.json` stores the website registry, `database.json` stores the selected connector (including its password, protected with file permissions), and `auth.db` continues to store dashboard accounts and invitations. Because the directory is not served by the web server, no `.htaccess` or Nginx rule is needed to protect it.

Retention runs when a site's database is opened. The default is 395 days; each site can use a value from 1 to 760 days.

## Web server configuration

The release archive ships an `.htaccess` file, so **Apache** and **LiteSpeed** need no extra configuration. Other web servers ignore `.htaccess`: apply the equivalent rules yourself. They:

- deny direct access to `vendor/`, `src/`, `bin/`, `tests/`, Composer files and any `.db`, `.json` or `.lock` file;
- serve `index.html` (the landing page, when deployed) before `index.php` at the site root;
- make browsers revalidate `minilytics.js`, so sites always run the current tracker;
- allow uploads of up to 128 MB for data imports;
- pass the `Authorization` header to PHP and run the PHP files under `/.well-known/`, for [AI assistants](../mcp/README.md) (Nginx and Caddy already pass the header).

Administrators see a warning in the dashboard when these rules are missing (it checks whether `/composer.json` is publicly readable).

### Nginx

```nginx
server {
    listen 80;
    server_name analytics.example.com;
    root /var/www/minilytics;
    index index.html index.php;

    # Data imports upload ZIP archives of up to 128 MB.
    client_max_body_size 128M;

    # Application code, Composer files, databases and dotfiles are never served.
    location ~ ^/(vendor|src|bin|tests)(/|$) { return 403; }
    location ~ \.(db|db-wal|db-shm|json|lock)$ { return 403; }
    location ~ /\.(?!well-known/) { return 403; }

    location = /minilytics.js {
        add_header Cache-Control "no-cache, must-revalidate";
    }

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

Adjust `fastcgi_pass` to your PHP-FPM socket or address. The deny rules must stay above the `\.php$` block: Nginx uses the first matching regular expression location.

### Caddy

```caddy
analytics.example.com {
    root * /var/www/minilytics
    encode gzip

    # Application code, Composer files, databases and dotfiles are never served.
    # /.well-known/ stays reachable: it serves the sign-in metadata of the MCP server.
    @blocked {
        path_regexp ^/(vendor|src|bin|tests)(/|$)|\.(db|db-wal|db-shm|json|lock)$|/\.
        not path /.well-known/*
    }
    respond @blocked 403

    header /minilytics.js Cache-Control "no-cache, must-revalidate"

    # Data imports upload ZIP archives of up to 128 MB.
    request_body {
        max_size 128MB
    }

    # Serve index.html before index.php at the site root.
    php_fastcgi unix//run/php/php8.3-fpm.sock {
        try_files {path} {path}/index.html {path}/index.php
    }
    file_server
}
```

### PHP limits

With PHP-FPM, the `php_value` lines of `.htaccess` are not applied either. To import large exports, set these in `php.ini` or your FPM pool configuration:

```ini
upload_max_filesize = 128M
post_max_size = 128M
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
```

### Behind Cloudflare

This is about the domain Minilytics itself runs on: browsers send events straight to it, so whether your tracked websites use Cloudflare does not matter.

Behind Cloudflare, every request reaches your server from a Cloudflare address, so all visitors would share one IP: one rate limit, the same visitor key and no internal traffic filtering. Turn on **Minilytics is behind Cloudflare** in **Settings → Tracking** so Minilytics reads the visitor's address from the `CF-Connecting-IP` header instead. The dashboard suggests it when it detects that your own requests come through Cloudflare.

You can also set `MINILYTICS_TRUST_CLOUDFLARE=1` in the server environment; it then overrides the dashboard setting:

- Apache or LiteSpeed: add `SetEnv MINILYTICS_TRUST_CLOUDFLARE 1` to `.htaccess`;
- Nginx with PHP-FPM: add `fastcgi_param MINILYTICS_TRUST_CLOUDFLARE 1;` next to the other `fastcgi_param` lines;
- Caddy: add `env MINILYTICS_TRUST_CLOUDFLARE 1` inside the `php_fastcgi` block.

Leave it unset otherwise: anyone can send this header, and Minilytics would then trust a forged address. For the same reason, if you enable it, make sure your server only accepts traffic from Cloudflare.

## MySQL and MariaDB checklist

- Enable the PHP `pdo_mysql` extension.
- Create a dedicated user; grant it `CREATE`, `ALTER`, `INDEX`, `SELECT`, `INSERT`, `UPDATE` and `DELETE`. The connector can create the named database during its connection test when “Create the database if it is missing” is enabled.
- In Hostinger hPanel, use the values displayed in **Databases → Management**. Usually, a site running under the same hosting account uses `localhost` and port `3306`; do not assume this for remote hosting.
- Test the connector in **Settings → Database** before saving it. The application creates tables lazily as each website receives or reads data.
- Export or back up the `*.db` files of the data directory before switching. Migration is deliberately not automatic so an incorrect connection can never overwrite local analytics.

## Backups

Run this command from a scheduled task to copy the SQLite databases:

```bash
php bin/backup.php --destination /secure/backups/minilytics
```

The destination directory is created with restrictive permissions when it does not already exist. Choose a destination on another disk or machine than the data directory, otherwise a lost disk or an accidental `rm -rf` takes the backups with it.

See also: [privacy](../privacy/README.md), [importing Umami data](../importing/README.md), [documentation index](../README.md).
