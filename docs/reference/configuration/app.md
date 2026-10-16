# App

The site's name, address, environment, language, and dates.

`config/app.php` · `Blush\Core\AppConfig`

| Option | Default | What it does |
|---|---|---|
| `name` | `'Blush'` | The site's name |
| `url` | `'http://localhost'` | The full site URL |
| `environment` | `Environment::Production` | `Development`, `Staging`, or `Production` |
| `debug` | `false` | Detailed error pages |
| `timezone` | `'UTC'` | The site's timezone |
| `locale` | `'en_US'` | The site's language and region |
| `providers` | `[]` | Service providers of the site's own (see below) |
| `description` | `''` | One line about the site: the summary in `llms.txt`, and the homepage's meta description and feeds' descriptions when nothing more specific describes them |
| `email` | `''` | The site's email address, where notices for its owner are sent; it isn't shown on the site. From `APP_EMAIL`; the admin requires one |
| `dateFormat` | `'long'` | How themes show dates: `full`, `long`, `medium`, or `short` follow the site's language (`October 4, 2026` in `en_US`); an [ICU pattern](https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax) such as `'d MMMM y'` keeps its order in any language |
| `timeFormat` | `'short'` | How themes show times, the same way: `short` is `2:30 PM` in `en_US`; `'HH:mm'` is always `14:30` |
| `languages` | `[]` | Other languages your content is written in, by code: `['fr' => 'fr_FR']`, or `['fr' => ['locale' => 'fr_FR', 'label' => 'Français']]`. See [Translations](../../concepts/translations.md) |
| `untranslated` | `'redirect'` | What another language does with an entry not translated into it: `'hide'` (not found), `'redirect'` (to the original), or `'include'` (redirect, and list the originals in that language's lists too). See [Translations](../../concepts/translations.md) |

Without `config/app.php`, these come from the `APP_*` variables (see
[`.env`](env.md)).

`providers` is for code kept in the site's own `src/` folder. Most code
belongs in a [plugin](../../plugins/README.md), whose provider its
`plugin.json` names, so it can be turned on and off and reused; see
[Your site's own code](../../plugins/site-code.md) for when `src/` fits.
