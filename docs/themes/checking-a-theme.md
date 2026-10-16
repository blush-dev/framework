# Checking a theme

```sh
bin/blush theme:check
```

It checks the manifest and settings, the site's menus, and
makes sure the base layout has the landmarks and skip link screen reader
users rely on, and prints `$template->foot()`.

Name a theme to check one that isn't active
(`bin/blush theme:check acme/notebook`): its code runs as it would if it
were active, provider and all, when its requirements are met, so its
components with a class render in the check.

With `--strict`, it also notes a base layout whose
`<html>` has no `dir`: write `<html lang="<?= attr($site->lang) ?>"
dir="<?= attr($site->dir) ?>">`, so a page in a right-to-left language
(`$site->dir` is `rtl`) reads right to left.

Other pages note what else it checks: [the head and the foot](head-and-foot.md),
[translations](translations.md), [blocks and components](blocks-and-components.md),
[templates for single entries](entry-templates.md), and
[image variants](image-variants.md).
