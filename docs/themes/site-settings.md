# Site settings in templates

`$site->setting()` reads a setting of the site's own, kept in
`user/data/settings/site.json`:

```php
<p class="tagline"><?= e((string) $site->setting('tagline')) ?></p>
```

A plugin declares such a setting by
[registering a field](../features/fields.md) on a Settings screen
(`settings:general`, `settings:reading`, `settings:writing`,
`settings:media`, `settings:search`, `settings:ai`, or `settings:api`).

It returns the saved value, read through its field (a date is a date),
or the field's `default`, or the second argument when there's neither:
`$site->setting('accent', 'red')`. Settings share one name space across
the screens. The Settings screens don't show these fields yet.

For your theme's own options, see [Theme settings](settings.md).
