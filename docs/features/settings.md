# Settings

A plugin's or theme's settings, such as a gallery's column count, go in
a **group of settings** of its own, named for it. For records you keep
many of, such as albums, use a [table](storing-data.md) instead.

Ask for `Blush\Settings\SettingGroups` in a constructor:

```php
use Blush\Settings\SettingGroups;

$settings = $groups->get('acme/gallery');            // ['columns' => 3], or [] when nothing's saved
$groups->save('acme/gallery', ['columns' => 4]);    // the whole group; [] removes it
$groups->update('acme/gallery', static fn (array $values): array => [...$values, 'columns' => 5]);
```

- A group is read the first time it's asked for on a request, not on
  every request.
- `update()` reads it fresh in a transaction, so two saves at once don't
  lose each other's changes.
- Check the values yourself: Blush keeps what you save. `id` and
  `content` can't be keys in a group.

On a flat-file site, a group is a file named for it, with `__` for its
`/`: `user/data/settings/acme__gallery.json`. A theme's settings from its
manifest are kept in its group the same way (see
[Themes](../themes/README.md)).

For API keys and tokens, use [secrets](secrets.md), never settings.
