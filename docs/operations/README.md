# Operations and storage

## Dashboard access

On the first dashboard visit, create the initial administrator account. Unauthenticated visitors are redirected to the login page.

Administrators can create a single-use invitation link for a collaborator. It expires after seven days. Public registration is not available.

## Data

Minilytics stores its local configuration in `data/`. By default, each website has its own SQLite database. You can switch the analytics store in **Settings → Database** to MySQL or MariaDB; Minilytics then creates isolated tables per website in the configured database. `sites.json` stores the website registry, `database.json` stores the selected connector (including its password, protected with file permissions), while `auth.db` continues to store dashboard accounts and invitations. Under Apache, `data/.htaccess` protects these files from direct HTTP access.

Retention runs when a site's database is opened. The default is 395 days; each site can use a value from 1 to 760 days.

## MySQL and MariaDB checklist

- Enable the PHP `pdo_mysql` extension.
- Create a dedicated user; grant it `CREATE`, `ALTER`, `INDEX`, `SELECT`, `INSERT`, `UPDATE` and `DELETE`. The connector can create the named database during its connection test when “Create the database if it is missing” is enabled.
- In Hostinger hPanel, use the values displayed in **Databases → Management**. Usually, a site running under the same hosting account uses `localhost` and port `3306`; do not assume this for remote hosting.
- Test the connector in **Settings → Database** before saving it. The application creates tables lazily as each website receives or reads data.
- Export or back up `data/*.db` before switching. Migration is deliberately not automatic so an incorrect connection can never overwrite local analytics.

## Backups

Run this command from a scheduled task to copy the SQLite databases:

```bash
php bin/backup.php --destination /secure/backups/minilytics
```

The destination directory is created with restrictive permissions when it does not already exist.

See also: [privacy](../privacy/README.md), [importing Umami data](../importing/README.md), [documentation index](../README.md).
