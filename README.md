<div align="center">
  <img src="favicon.svg" alt="Minilytics logo" width="160" />
  <h1>Minilytics</h1>
  <p><strong>Minimal, self-hosted web analytics powered by PHP and SQLite.</strong></p>
</div>

Minilytics is a lightweight web analytics platform built for simplicity, performance, and privacy.

It was built because today's most modern analytics tools require heavy Node.js runtimes, complex Docker setups, or dedicated database servers. Minilytics is **minimal by design**. It's designed to run anywhere standard PHP is available, consumes negligible server resources, while trying to provide a clean and modern user experience.

---

## Quick Start

### Requirements

- PHP 8.0 or later with the `sqlite3` extension enabled
- Web server write access to the `data/` directory
- Outbound HTTPS access and `zlib` support (to download the local DB-IP City Lite geolocation database)

*Note: The `zip` extension is only needed if you import historical data from external services.*

### Production Installation

Download the latest production archive from [GitHub Releases](https://github.com/axthauvin/minilytics/releases/latest) and extract it into your website root directory:

```bash
curl -LO https://github.com/axthauvin/minilytics/releases/latest/download/minilytics.tar.gz
tar -xzf minilytics.tar.gz && rm minilytics.tar.gz
```

1. Ensure your web server has write access to the `data/` directory.
2. Navigate to `https://your-domain.com/dashboard/` to create the initial administrator account.
3. Add your website in the dashboard and paste the tracking snippet into your website's `<head>`.

> On Apache, the included `.htaccess` files protect stored SQLite databases automatically (ensure `AllowOverride All` is enabled). On Nginx, block direct HTTP access to `/data/`.

### Local Development

To run Minilytics locally without installing a full web server, clone the repository and start PHP's built-in development server:

```bash
php -S localhost:8080
```

Open [http://localhost:8080/dashboard/](http://localhost:8080/dashboard/) to access the dashboard.

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
node --test tests/tracker-smoke.test.js
```
