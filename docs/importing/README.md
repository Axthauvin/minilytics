# Importing from another analytics service

As Minilytics is a new analytics service, it provides importers for other services to migrate historical analytics.

Supported services :

- [x] Umami (ZIP export)
- [ ] Google Analytics (Planned but not yet implemented)
- [ ] Plausible (Planned but not yet implemented)
- [ ] Matomo (Planned but not yet implemented)
- [ ] Simple Analytics (Planned but not yet implemented)

> If you use an analytics service not listed or not implemented yet, it would be great if you could contribute an importer for it !
> That would help other users to migrate their historical data to Minilytics.

## Dashboard

Open the data import flow, select the service you want to import from, then upload the exported data.

## Command line

If you prefer to use the command line, you can use the `import.php` script to import data from a ZIP file or an extracted folder.

```bash
php import.php --zip umami-export.zip --site-id example
```

To import an extracted directory and provide a display name:

```bash
php import.php --folder umami-import --site-id example --site-name "Example"
```

Run `php import.php --help` for every option, including `--domain`.

See also: [operations](../operations/README.md), [tracking](../tracking/README.md), [documentation index](../README.md).
