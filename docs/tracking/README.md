# Tracking

Create a website in the dashboard and copy the generated snippet. It includes the site's ID and write key.

```html
<script
  defer
  src="https://analytics.example.com/minilytics.js"
  data-site-id="my_site"
  data-site-key="your-private-key"
  data-privacy-mode="strict"
></script>
```

Add it to the `<head>` of every page to measure. Minilytics records a pageview automatically and detects browser history changes.

## Custom events

Minilytics allows you to send custom events with a name and optional properties. Use the `minilytics.track` function:

Send a named event with useful properties:

```js
minilytics.track("button_click", { buttonId: "signup", plan: "pro" });
```

You can also use declarative tracking, that automatically sends events when an element is clicked. Add `data-minilytics-event` and additional `data-minilytics-*` attributes to the element:

```html
<button data-minilytics-event="signup_button" data-minilytics-plan="pro">
  Sign up
</button>
```

## Troubleshooting

The tracker stays silent in the browser console by default, so it does not pollute the console of the sites that embed it (for example a development host that is not an allowed domain). If events are not arriving, enable debug mode to see accepted events and configuration, network, or endpoint errors. Add `data-debug="true"` to the script tag:

```html
<script
  defer
  src="https://analytics.example.com/minilytics.js"
  data-site-id="my_site"
  data-site-key="your-private-key"
  data-privacy-mode="strict"
  data-debug="true"
></script>
```

Without debug mode, rejected events still explain what to fix in the response to the `track.php` request, in the browser's network panel. For example, a page served from a domain that is not allowed answers `403` with:

```json
{"error": "blog.example.com is not an allowed domain for this website. Add it in Settings → Tracking → Allowed domains."}
```

To test the snippet on your computer, enable **Accept events from localhost** in **Settings → Tracking** instead of adding `localhost` to the allowed domains, and turn it off once the website is live: anyone can run a local page with your script key.

See also: [privacy](../privacy/README.md), [operations](../operations/README.md), [documentation index](../README.md).
