# Choosing the site's default admin theme

The site's default admin theme is what every account sees until it
chooses its own, and what the sign-in screen shows.

On **Extend → Admin Themes**, **Make Default** on a theme that's on makes
it the site's default (see [Admin themes](../admin/admin-themes.md) in the
admin guide). Owners and administrators can, and any role with
`extensions.admin-themes.activate`. Or set `default` in
`config/admin-themes.php` to a theme's name:

```php
<?php

declare(strict_types=1);

return new Blush\Admin\Theme\AdminThemeConfig(
	default: 'blush/library'
);
```

## Who sees it

An account that hasn't chosen a theme, or that chose the site's default,
sees whatever the default is, so changing the default changes their
admin too. An account that chose another theme keeps it. If an account's
theme is turned off or removed, it sees the default, and if the default
can't be shown, Neutral.
