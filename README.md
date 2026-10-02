# Minilytics

A lightweight, privacy-friendly, open-source analytics solution for web applications.

## How to run the server

```bash
php -S localhost:8080
```

- **Dashboard**: Open `http://localhost:8080/dashboard/` in your browser.
- **Demo Website**: Open `http://localhost:8080/demo.html` to trigger tracking events.

## Storage Architecture (Multi-Tenant SQLite)

All analytics data is stored inside a `data/` folder at the root. Each website has its **own isolated SQLite database**:

```text
data/
├── .htaccess           # Access protection for database files on Apache
├── sites.json          # Registry of configured websites
├── demo_site.db        # Isolated database for demo_site
└── <site_id>.db        # One SQLite database per website
```

Creating a new site from the dashboard automatically instantiates `data/<site_id>.db`.

## Dashboard Architecture

The dashboard code is organized under `dashboard/` with a clean `src/` directory structure:

```text
dashboard/
├── index.php                 # Main dashboard shell & SPA router
└── src/
    ├── api/                  # Backend REST JSON endpoints
    │   ├── db.php            # Multi-database manager & connection helper
    │   ├── stats.php         # Analytics overview & timeseries per site
    │   ├── events.php        # Filterable events stream & search per site
    │   ├── sessions.php      # User sessions & journey inspector per site
    │   ├── sites.php         # List & create new websites
    │   ├── import.php        # Import controller endpoint
    │   └── importers/        # Extensible platform importer modules
    │       ├── ImporterInterface.php
    │       ├── BaseImporter.php
    │       ├── UmamiImporter.php
    │       ├── GoogleAnalyticsImporter.php (Coming Soon)
    │       ├── PlausibleImporter.php       (Coming Soon)
    │       ├── MatomoImporter.php          (Coming Soon)
    │       ├── SimpleAnalyticsImporter.php (Coming Soon)
    │       └── ImporterRegistry.php
    ├── components/           # Modular UI components
    │   ├── sidebar.php       # Left navigation bar (collapsible)
    │   ├── header.php        # Top bar with site selector, live visitors & controls
    │   └── modal.php         # Add site, inspect payloads, sessions & import modal
    ├── pages/                # Page templates
    │   ├── websites.php      # Websites portal & manager
    │   ├── overview.php      # Views chart & top content metrics
    │   ├── events.php        # Live filterable events table
    │   └── sessions.php      # User sessions & journey trails
    └── assets/
        ├── css/
        │   ├── dashboard.css # Minimalist UI styling inspired by Swetrix
        │   ├── overview.css  # Overview page styling
        │   └── import.css    # Import modal, provider cards & dropzone styles
        └── js/
            ├── api.js        # Client API request helper
            ├── chart.js      # Smooth Bézier canvas area chart
            ├── websites.js   # Websites management controller
            ├── import.js     # Data import modal controller
            ├── overview.js   # Overview controller
            ├── events.js     # Events filtering & pagination
            ├── sessions.js   # Sessions list & timeline inspector
            └── app.js        # Main SPA router & live auto-refresh
```

## How to integrate on a website

Add a single `<script>` tag inside your HTML `<head>`:

```html
<script
  defer
  src="http://localhost:8080/minilytics.js"
  data-site-id="my_site"
></script>
```

### How to track events on your website:

**Using the JavaScript API**:

```javascript
// Track a custom event
minilytics.track("button_click", { buttonId: "signup", plan: "pro" });
```

**Using declarative auto-tracking directly in HTML**:

```html
<button data-minilytics-event="signup_button" data-minilytics-plan="pro">
  Sign Up
</button>
```

## Data Import (Umami & Extensible Providers)

Minilytics includes a high-performance analytics ingestion engine that allows you to easily import historical analytics from other platforms:

- **Umami Analytics** _(Available)_: Import full `.zip` export archives (including `website_event.csv` and `event_data.csv`), automatically mapping pageviews, custom events, event parameters, visitors, and sessions.
- **Google Analytics (GA4)** _(Coming Soon)_: GA4 BigQuery exports & CSV dumps.
- **Plausible Analytics** _(Coming Soon)_: CSV export format.
- **Matomo** _(Coming Soon)_: Database archives & CSV exports.
- **Simple Analytics** _(Coming Soon)_: CSV dumps.

### 1. In the Web Dashboard:

- Click **"Import Data"** in the top portal header or in the sidebar.
- Drag & drop your Umami `.zip` export file (or choose a server/local path).
- Minilytics automatically inspects the archive, detecting the domain and suggesting a website name.
- Choose whether to create a new website or merge into an existing one, then click **Start Import**.

### 2. Using the Pure-PHP CLI Tool (SSH / Terminal):

On a standard Apache / Linux server without Python, you can run the import directly in the terminal using PHP:

```bash
# Import from a ZIP archive
php import.php --zip umami-export-sample.zip --site-id ecrismalettre

# Or import from an extracted folder
php import.php --folder umami-import --site-id ecrismalettre --site-name "Ecris Ma Lettre"
```

_(A Python script `import-umami.py` is also available if you prefer using Python locally)._

## Apache Configuration & Security

The project includes pre-configured `.htaccess` files for production Apache servers:

- **Large file uploads**: Sets `upload_max_filesize` and `post_max_size` to `128M` to allow large ZIP archives to be uploaded smoothly.
- **Database protection**: `data/.htaccess` denies direct web access to all `.db` and `sites.json` files so your analytics databases cannot be downloaded via HTTP.
