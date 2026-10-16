# Text in PHP code

Write text where it's used, with a key and the text beside it, and
Blush translates it into the page's or the admin's language. It's the
same `t()` in plugins, themes, and core. For a theme's catalogs, its
manifest's text, and the site's own wording, see
[Translations in a theme](../themes/translations.md); for text in
JavaScript, see [Scripts and styles](scripts-and-styles.md#text-in-scripts).

## Writing text

Blush works out whose text it is from where the code lives, so you
never name your plugin:

- **In a template** the plugin ships: `$messages->t('key', 'Text')`.
- **In a block or component class**, and **in your provider**:
  `$this->messages->t('key', 'Text')`. A label you register is
  translated where it's shown, so it reads in the page's or the
  admin's language:

  ```php
  $registry->register(new EntryTemplate('template-wide', label: $this->messages->t('templates.wide.label', 'Wide')));
  ```

- **In any other class**, ask for `Messages` in the constructor. You
  get your plugin's, by the namespace your class is in (your
  `autoload` or your provider's):

  ```php
  use Blush\Translation\Messages;
  use Blush\Translation\Text;

  final readonly class Exporter
  {
  	public function __construct(private Messages $messages) {}

  	public function label(): Text
  	{
  		return $this->messages->t('tools.export.label', 'Export {count, plural, one {# entry} other {# entries}}', count: 3);
  	}
  }
  ```

It's the same `t()` everywhere, and it gives back a message that's
translated when it's printed, in the current language: the page's on a
page, the site's elsewhere. Print it, or cast it with `(string)` where
you need a string now. In the admin, the current language is the
signed-in account's.

## Whose text it is

Text written in a plugin's code (`t()` in its templates and classes) is
the plugin's, looked up in its catalogs and then Blush's, never the
theme's. A plugin's block labels and descriptions go in its
`lang/en.json` too, under `blocks.{name}`, with `@@locale` and
`@@domain` (`"acme/hello"`) at the top.

## What to translate

Write every message people may read this way, an exception's message
included when the admin shows it (cast it as you throw it). Text only
a developer sees, in a PHP error or a log, stays in plain English.

## Keeping a message for later

To keep a message and show it later (a report, a job's result), store
`$text->toArray()` (its key, its text, and its values) and read it back
with `$this->messages->restore($stored)`: it's shown in the language of
whoever reads it, and with the text you wrote if a later version has no
such key. `restore()` returns `null` for anything else, such as plain
text you stored before.

## Catalogs for translators

`bin/blush lang:extract acme/hello` writes it all to `lang/en.json` for
translators when you want them. It's optional: your code's text shows
without it. `plugin:check` warns when that file reads otherwise than
your code, since its text would win.

Your `plugin.json` `label` and `description` are translated the same
way, under `manifest.label` and `manifest.description` in your
catalogs, and `lang:extract` writes them too (see
[Translations in a theme](../themes/translations.md)).
