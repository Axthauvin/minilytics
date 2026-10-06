<div align="center">
  <img src="favicon.svg" alt="Minilytics logo" width="200" />
  <h1>Minilytics</h1>
  <p>Private, PHP based, self-hosted and beautiful web analytics</p>
</div>

Minilytics is a self-hosted web analytics application. Add its lightweight tracker to a website, then use the private dashboard to understand pageviews, events, sessions, and traffic sources without relying on a third-party analytics service.

TODO: add screenshot

## Quick start

### Requirements

- PHP 8.0 or later with the SQLite3 extension enabled
- A web server that can write to `data/`
- Outbound HTTPS access from PHP, zlib support and roughly 250 MB of free disk space to automatically download the local DB-IP City Lite geolocation database (country, region and approximate city)

The Zip extension is required only to import a ZIP export from another tracking service (like Umami, Google Analytics, or Matomo).

### Install from release (Production)

Download the production archive directly from the [latest GitHub release](https://github.com/axthauvin/minilytics/releases/latest).

```bash
# 1. Create target directory and download the latest archive
mkdir -p /var/www/minilytics
cd /var/www/minilytics
curl -LO https://github.com/axthauvin/minilytics/releases/latest/download/minilytics.tar.gz

# 2. Extract files
tar -xzf minilytics.tar.gz
rm minilytics.tar.gz

# 3. Grant write permissions on data/ to your web server user (e.g. www-data, apache, nginx)
sudo chown -R www-data:www-data data
sudo chmod -R 775 data
```

#### Web server configuration

- **Apache** :
  - Ensure `mod_rewrite` is enabled (`sudo a2enmod rewrite`).
  - Ensure `AllowOverride All` is set in your VirtualHost configuration so the provided `.htaccess` files (especially `data/.htaccess` protecting your SQLite databases) are respected.
- **Nginx** :
  - Restrict direct HTTP access to the `data/` folder:
    ```nginx
    location ^~ /data/ {
        deny all;
        return 404;
    }
    ```

Set the `MINILYTICS_TRACKING_SECRET` environment variable when running more than one application node.

Finally, navigate to `https://your-domain.com/dashboard/` to create the initial administrator account.

### Run locally

Clone the repository or download its source, then start PHP's built-in server from the project directory:

```bash
php -S localhost:8080
```

Open [http://localhost:8080/dashboard/](http://localhost:8080/dashboard/) and create the first administrator account. Create a website in the dashboard, copy its generated tracking snippet into your website's `<head>`, and then visit your website to see pageviews appear in the dashboard.


## Documentation

- [Tracking and how to track custom events](docs/tracking/README.md)
- [Privacy and data protection](docs/privacy/README.md)
- [Operations and storage](docs/operations/README.md)
- [Importing Umami data](docs/importing/README.md)

## Run tests

The test suite uses [Node.js](https://nodejs.org/) to run smoke tests against the tracker and demo snippet, but **node is not required to run Minilytics, only for testing**. Install Node.js and its dependencies, then run the tests:

````bash

Run the tracker smoke test after changing the tracker or demo snippet:

```bash
node --test tests/tracker-smoke.test.js
````
