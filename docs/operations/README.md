# Operations and storage

## Dashboard access

On the first dashboard visit, create the initial administrator account. Unauthenticated visitors are redirected to the login page.

Administrators can create a single-use invitation link for a collaborator. It expires after seven days. Public registration is not available.

## Data

Minilytics stores data in `data/`. Each website has its own SQLite database. `sites.json` stores the website registry, while `auth.db` stores accounts and invitations. Under Apache, `data/.htaccess` protects these files from direct HTTP access.

Retention runs when a site's database is opened. The default is 395 days; each site can use a value from 1 to 760 days.

## Backups

Run this command from a scheduled task to copy the SQLite databases:

```bash
php backup.php --destination /secure/backups/minilytics
```

The destination directory is created with restrictive permissions when it does not already exist.

See also: [privacy](../privacy/README.md), [importing Umami data](../importing/README.md), [documentation index](../README.md).
