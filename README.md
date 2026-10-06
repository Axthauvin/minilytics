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

Download the production archive from [GitHub Releases](https://github.com/axthauvin/minilytics/releases/latest) and extract it into your website directory:

```bash
curl -LO https://github.com/axthauvin/minilytics/releases/latest/download/minilytics.tar.gz
tar -xzf minilytics.tar.gz && rm minilytics.tar.gz
```

Ensure your web server has write access to `data/`, then visit `https://your-domain.com/dashboard/` to create the initial administrator account.

> [!NOTE]
> On Apache, the included `.htaccess` files protect stored data automatically (ensure `AllowOverride All` is enabled). On Nginx, ensure direct access to `/data/` is blocked. Set `MINILYTICS_TRACKING_SECRET` when running more than one application node.

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
