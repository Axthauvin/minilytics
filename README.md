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
    │   └── sites.php         # List & create new websites
    ├── components/           # Modular UI components
    │   ├── sidebar.php       # Left navigation bar (collapsible)
    │   ├── header.php        # Top bar with site selector, live visitors & controls
    │   └── modal.php         # Add site modal, JSON payload & session timeline modals
    ├── pages/                # Page templates
    │   ├── overview.php      # Views chart & top content metrics
    │   ├── events.php        # Live filterable events table
    │   └── sessions.php      # User sessions & journey trails
    └── assets/
        ├── css/
        │   └── dashboard.css # Minimalist UI styling inspired by Swetrix
        └── js/
            ├── api.js        # Client API request helper
            ├── chart.js      # Smooth Bézier canvas area chart
            ├── overview.js   # Overview controller
            ├── events.js     # Events filtering & pagination
            ├── sessions.js   # Sessions list & timeline inspector
            └── app.js        # Main SPA router & live auto-refresh
```

## How to integrate on a website

Add a single `<script>` tag inside your HTML `<head>`:

```html
<script defer src="http://localhost:8080/minilytics.js" data-site-id="my_site"></script>
```

### How to track events on your website:

**Using the JavaScript API**:
```javascript
// Track a custom event
minilytics.track('button_click', { buttonId: 'signup', plan: 'pro' });
```

**Using declarative auto-tracking directly in HTML**:
```html
<button data-minilytics-event="signup_button" data-minilytics-plan="pro">
  Sign Up
</button>
```
