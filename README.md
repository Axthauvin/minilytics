<div align="center">
  <img src="app/favicon.svg" alt="Minilytics logo" width="160" />
  <h1>Minilytics</h1>
  <p><strong>Minimal, self-hosted web analytics powered by PHP with SQLite, MySQL, or MariaDB.</strong></p>

  <p>
    <a href="#quick-start">Quick Start</a> &bull;
    <a href="#production-installation">Installation</a> &bull;
    <a href="#local-development">Development</a> &bull;
    <a href="#documentation">Documentation</a> &bull;
    <a href="#testing">Testing</a> &bull;
    <a href="#license">License</a>
  </p>
</div>

Minilytics is a lightweight web analytics platform built for simplicity, performance, and privacy.

It was built because today's most modern analytics tools require heavy Node.js runtimes, complex Docker setups, or dedicated database servers. Minilytics is **minimal by design**. It's designed to run anywhere standard PHP is available, consumes negligible server resources, while trying to provide a clean and modern user experience.

![Screenshot of the Minilytics dashboard](docs/assets/dashboard.png)

---

## Quick Start

### Requirements

- PHP 8.1 or later with the `sqlite3` extension enabled
- For MySQL or MariaDB: the `pdo_mysql` PHP extension and a database/user with `CREATE`, `ALTER`, `INDEX`, `SELECT`, `INSERT`, `UPDATE` and `DELETE` permissions
- A writable data directory outside the web root (default: `minilytics-data/` next to the web root, or set `MINILYTICS_DATA_DIR`)
- Outbound HTTPS access and `zlib` support (to download the local DB-IP City Lite geolocation database)

_Note: The `zip` extension is only needed if you import historical data from external services._

### Production Installation

Download the latest production archive from [GitHub Releases](https://github.com/axthauvin/minilytics/releases/latest) and extract it into your website root directory:

```bash
curl -LO https://github.com/axthauvin/minilytics/releases/latest/download/minilytics.tar.gz
tar -xzf minilytics.tar.gz && rm minilytics.tar.gz
```

1. Ensure your web server can write to `../minilytics-data/` (created automatically if the parent is writable), or point `MINILYTICS_DATA_DIR` to another folder **outside** the web root.
2. Navigate to `https://your-domain.com/dashboard/` to create the initial administrator account.
3. Add your website in the dashboard and paste the tracking snippet into your website's `<head>`.

### How do we store analytics data?

SQLite is the default (because it's lightweight and doesn't require a separated server), but you can change it any time !
To use a managed database, open **Settings → Database**, select MySQL or MariaDB, enter the host, port, database name, username and password, then use **Test connection** before saving. The test can create the named database when it is missing if the database user has the `CREATE` permission. **Existing SQLite analytics are not copied automatically**.

> Data is stored outside the web root so redeploying or re-extracting the archive can never overwrite or expose it.

### Local Development

Clone the repository, install the PHP dependencies with [Composer](https://getcomposer.org/) and start the development server:

```bash
cd app
composer install
composer serve # or php -S localhost:8080 -t app scripts/dev-router.php
```

Open [http://localhost:8080/dashboard/](http://localhost:8080/dashboard/) for the dashboard and [http://localhost:8080/](http://localhost:8080/) for the landing page. The development router (`scripts/dev-router.php`) overlays `landing/` on top of `app/`. Without Composer, run `php -S localhost:8080 -t app scripts/dev-router.php` from the repository root.

Release archives already bundle `vendor/`, so Composer is only needed when running from a clone of the repository.

---

## Documentation

- [Tracking and custom events](docs/tracking/README.md)
- [Privacy and data protection](docs/privacy/README.md)
- [Operations, backups and storage](docs/operations/README.md)
- [Importing data from other services](docs/importing/README.md)

---

## Testing

Node.js is used only to run unit and smoke tests during development. **Node.js is not required to run Minilytics in production.**

```bash
node --test app/tests/*.test.js
```

---

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
