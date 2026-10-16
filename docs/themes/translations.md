# Translations in a theme

A theme writes its text where it's used, with a key that names it, and
keeps translations in catalogs in its `lang/` folder. This page covers a
theme's text; [Translations](../features/translations.md) covers the
`t()` API that core, plugins, and themes share.

## Writing text

Write your text where it's used, with a key that names it:

```php
<?= e($messages->t('entry.by', 'By {author}', author: $name)) ?>
```

The second argument is the text, in whatever language you write your
theme in. It's shown wherever no catalog has the key, so your theme
reads well before anyone translates it. Values are named (`author:`),
and every value is named, never positional. Leave the text out
(`$messages->t('read_more', title: $title)`) to read a key from your
catalogs alone; a key no catalog has then shows as itself.

Text is an [ICU message](https://unicode-org.github.io/icu/userguide/format_parse/messages/),
so one message can cover what would otherwise be several:

```php
// Plurals: # is the number; other is required.
$messages->t('comments', '{count, plural, =0 {No comments} one {# comment} other {# comments}}', count: $n);

// Choosing by a value, instead of building keys from it.
$messages->t('status', '{status, select, draft {Draft} scheduled {Scheduled} other {Published}}', status: $status);

// Numbers and dates, in the page's language.
$messages->t('since', '{views, number} views since {date, date, long}', views: $views, date: $since);
```

Keep a sentence as one message, so a translator can reorder it. A note
for translators goes in `context:`, which is never a value:

```php
$messages->t('actions.post', 'Post', context: 'A button that publishes, not a blog post');
```

## Text in classes

Classes write text the same way, with the same `t()`. A
[component or block class](blocks-and-components.md) and your
theme's provider have `$this->messages->t()`; any other class asks for
`Messages` in its constructor and calls `$this->messages->t()`. Core and
plugins write theirs the same way.

## When text is translated

`t()` gives back the message, not yet translated: it's translated when
it's printed, in the page's language. So a component can make all the
lines it might show and print only one, and only that one is looked
up. A message you hand to something else to show later, such as a
[template's label](entry-templates.md), is translated where
it's shown, in that page's or the admin's language. Where you need the
text as a string right away, cast it: `(string) $text`.

Text is looked up in your theme's catalogs (a child theme's first),
then Blush's. A plugin's text is the plugin's, and Blush's is its own,
so a key your theme happens to share with them never rewords them; to
reword them, use [your own wording](#your-own-wording).

## Catalogs

Translations live in your theme's `lang/` folder, one catalog per
language (`en.json`, `fr.json`, `fr_CA.json`). Start each catalog with
`@@locale` and `@@domain`, which say what it translates: the language,
and your theme's name.

```json
{
	"@@locale": "fr",
	"@@domain": "acme/notebook",
	"read_more": "Lire la suite de {title}"
}
```

A page looks for each message in its own language (`fr_CA`), then
without the region (`fr`), then your site's language, and last English.
So `en.json` is the one catalog every theme should have: a site in a
language you haven't translated shows your English text.

`bin/blush lang:extract acme/notebook` writes the text in your code to
`lang/en.json` for translators to work from: it adds new keys, updates
text you've changed (translators should recheck those), keeps
everything else, and writes each `context:` beside its message as
`@{key}`. Give `--locale=fr` when your code's text is French, and
`--dry-run` to see what would change. You don't need the file for your
text to show; it's for translators. `theme:check` warns when
`lang/en.json` reads otherwise than your code (its text would win),
when one key has two texts, when text isn't a valid ICU message, and
when a key or text isn't plain text it can collect (such as
`"status.{$status}"`; use a `select`), and when a call names a parameter
`key:` or `text:` beside its key and text, which PHP refuses (name it
something else). Templates in other languages,
such as Blade, aren't read.

## Your manifest's text

The text in `theme.json` is translated too: your theme's `label` and
`description`, your [menu locations'](../features/menus.md) labels, and your
[settings'](settings.md) `label` and `description`. Write them in
`theme.json` as usual; a catalog translates each under a `manifest.`
key:

```json
{
	"@@locale": "fr",
	"@@domain": "acme/notebook",
	"manifest": {
		"label": "Carnet",
		"description": "Un thème sobre pour écrire.",
		"menus": {
			"primary": "Principal"
		},
		"settings": {
			"wide": { "label": "Mise en page large", "description": "Occupe toute la fenêtre." }
		}
	}
}
```

The admin, the CLI, and the API show them in their language, and a
menu block names its menu by its location's label in the page's. Each
is read from your theme's own catalogs, even while another theme is
active, and without a translation, `theme.json`'s text shows.
`lang:extract` writes them to `lang/en.json` with your code's text, and
`theme:check` compares them. Keys under `manifest` are kept for this, so
don't use them for your own text.

## Keys that start with `@`

Keys that start with `@` aren't messages, so they never show on a page.
They let a catalog be recognized on its own, away from its folder, such
as when it's sent to a translator and back. Every catalog Blush ships
has them, and plugins and icon packs use them the same way, with their
own names. `theme:check` warns
about a catalog whose `@@locale` or `@@domain` doesn't match its file or
your theme (and, with `--strict`, notes one without them).

## HTML in values

Values in a message are text, and escaped with it. When one is HTML,
such as a list of linked names, mark it with `raw()`: it goes in as it
is, and the message's own words and every other value are still
escaped.

```php
<?= e($messages->t('posted_by', names: raw($links), date: $date)) ?>
```

`attr()` escapes it all, so the same message can go in a `title` too.

## Child themes

A child theme's catalogs come before its parent's, message by message,
so a child rewords only what it needs to. A message given as a value
prints in the page's language too: `<?= e($label) ?>` prints a label a
provider registered.

## Your own wording

To reword or translate any text without touching the theme or plugin
it comes from, put your own catalog in `user/lang/`, one folder per
language:

```
user/lang/
  en/
    core.json                           Blush's own text
    extensions/acme/notebook.json       a theme's, by its name
    extensions/acme/hello.json          a plugin's or icon pack's
  fr/
    extensions/acme/notebook.json
```

Your catalog wins over the package's, message by message, so it only
needs what you change, and the rest still comes from the package. The
language is checked first, so the package's own `fr.json` still beats
your `en` folder on a French page.

Text in scripts is in [named catalogs](../features/scripts-and-styles.md),
and so is yours: in a folder of the catalog's name under the language,
such as `user/lang/fr/site/extensions/acme/notebook.json` for a theme's
`site` catalog, or `user/lang/fr/admin/core.json` for the admin's.

```json
{
	"@@locale": "en",
	"@@domain": "blush/default",
	"skip_to_content": "Jump to the post",
	"powered_by": "Powered by tea."
}
```

## Page numbers in titles

On later pages of a listing, the title gets the page number: "Blog:
Page 2", or "Page 2" on the front page. To word it differently, add
`document_title.paged` (`"{title}: Page {page}"`) and
`document_title.page` (`"Page {page}"`) to your theme's `lang/` files.
