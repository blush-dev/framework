# Plugins and middleware

Which plugins, icon packs, and admin themes are on, and middleware run
on every request.

- `config/plugins.php` · `Blush\Plugin\PluginConfig`: `enabled`, the
  plugins in `extensions/` to turn on, by name
  (`new PluginConfig(enabled: ['acme/hello'])`). A local plugin is off
  until it's named; a Composer plugin is on. Turning plugins on and off
  in the admin saves its own `enabled` list in
  `user/data/settings/plugins.json`, in place of this one, naming every plugin
  that's on, Composer's included; a plugin it doesn't name is off. A
  plugin that's on
  still runs only when its [requirements](../../plugins/requirements.md) are
  met. Naming one that isn't installed is an error.
- `config/icons.php` · `Blush\Icon\IconConfig`: `enabled`, the
  [icon packs](../../icon-packs/README.md) in `extensions/` to turn on, by
  name (`new IconConfig(enabled: ['acme/brands'])`). A local pack is off
  until it's named; a Composer pack is on. The admin's own list, naming
  every pack that's on, replaces this one when it's saved.
- `config/admin-themes.php` · `Blush\Admin\Theme\AdminThemeConfig`:
  `enabled`, the [admin themes](../../admin-themes/README.md) in `extensions/` to turn
  on, by name, and `default`, the theme anyone who hasn't chosen one sees,
  and the sign-in screen (`'blush/neutral'` by default). Neutral is always
  on; the framework's and Composer's themes are on until a list says
  otherwise.
- None has a list of what's off: config only says what's on
  ([Installing extensions](../../concepts/installing-extensions.md)).
- `config/http.php` · `Blush\Http\HttpConfig`: `middleware`, a list of
  PSR-15 middleware classes run on every request.
