# Minilytics

A lightweight, privacy-friendly, open-source analytics solution for web applications.

## How to run the server
```bash
php -S localhost:8080
```

## How to integrate on a website

Add a single `<script>` tag inside your HTML `<head>`:

```html
<script defer src="http://localhost:8080/minilytics.js" data-site-id="my_site"></script>
```

### How to use on your website:

You can track events in two ways:

**Using the JavaScript API**:
```javascript
// Track a custom event
minilytics.track('button_click', { buttonId: 'signup', plan: 'pro' });
```
**Using declarative the auto-tracking directly in your code**:
  ```html
  <button data-minilytics-event="signup_button" data-minilytics-plan="pro">
    Sign Up
  </button>
  ```
