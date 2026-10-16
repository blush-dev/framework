# Turning admin themes on

Accounts can choose only the admin themes that are on.

Neutral is always on. The others Blush comes with, and admin themes installed with
Composer, are on by default. An admin theme in your site's `extensions/`
folder is off until its switch on **Extend → Admin Themes** turns it on,
or `config/admin-themes.php` names it in `enabled`:

```php
return new Blush\Admin\Theme\AdminThemeConfig(
	enabled: ['acme/harbor']
);
```

This works the same way as [turning on](../concepts/installing-extensions.md)
plugins and icon packs: once a switch is used, the admin's saved list is
all of what's on.

## Installing one

**Install Admin Theme** on the same screen installs one from a `.zip`;
it arrives turned off. Since an admin theme is only data, administrators
may install and delete them, not just owners.

Accounts can only choose themes that are on and whose
[requirements](../plugins/requirements.md) are met.
