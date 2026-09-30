# Core icons

`blush/` holds Blush's core icons (D-187): a front-end subset of
[Lucide](https://lucide.dev) 1.48.0 (ISC license, in `blush/LICENSE`),
named as in Lucide. `blush/tags.json` has Lucide's search tags for each,
and `blush/categories.json` the one category the admin's icon picker
shows it in (`IconCategory`, D-265).

To update them or add icons, download the `lucide-static` package
(`npm pack lucide-static`), and for each icon wanted, copy
`icons/{name}.svg` here with its license comment and `class` attribute
removed, then add its tags from `tags.json`, its category to
`categories.json`, and an English label to
`resources/lang/en.json` (`icons.{name}.label`).
