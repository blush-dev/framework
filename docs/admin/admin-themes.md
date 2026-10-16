# Admin themes

With `extensions.admin-themes.view`, **Extend → Admin Themes** shows
every installed [admin theme](../admin-themes/README.md): which ones
people can choose, and the site's default.

## The list

Neutral comes first, then the rest by
label, each as a sketch of the admin in its own colors, with its
version, where it's installed, and a switch. A theme that's on can be
chosen by anyone on [Your Account](users/your-account.md); one that's off can't, and anyone who
had chosen it sees the site's default. Neutral is always on.

**Make Default** makes a theme the site's default: what anyone who
hasn't chosen a theme sees, and the sign-in screen. The default is
marked **Site default**, and can't be turned off or deleted until
another theme is the default. A theme whose
[requirements](../plugins/requirements.md) aren't met can't be turned on,
and says what it needs. One whose text is hard to read says so; it
still works. A theme that can't be used is listed by where it was
found, with the reason. The filters and the compact list work as on
[Themes](themes.md#filters), each row marked with the theme's accent.

## On and off

A theme in `extensions/` is off until it's turned on here or named in
`config/admin-themes.php`'s `enabled` list; the framework's and
Composer's are on. Once a switch is used, the saved list names every
theme that's on; the switches and the default are saved in
`user/data/settings/admin-themes.json`, over that file, with **Use
`config/admin-themes.php`'s list** (and **its default**) to go back.

## A theme's details

A theme's name, or **Admin theme details** in its menu, opens its details:
the admin in its colors, light and dark, what it changes (the rest is
Neutral's), the fonts it brings, whether its text is easy to read, then
**Details** and **Dependencies**, as a plugin's are. **Delete Admin
Theme** removes a theme's folder (or a broken one's) from `extensions/`.
**Install Admin Theme** installs one from a `.zip`, as **Install Theme**
does (see [Installing extensions](installing-extensions.md)); it arrives
turned off. Since an admin theme has no code, an administrator may
install, replace, and delete them, as an owner may.
