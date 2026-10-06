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

If for some reason events are not arriving, check the browser console for errors. You can also enable debug mode to see accepted events and configuration, network, or endpoint errors. Add `data-debug="true"` to the script tag:

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

See also: [privacy](../privacy/README.md), [operations](../operations/README.md), [documentation index](../README.md).
