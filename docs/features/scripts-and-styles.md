# Scripts and styles

Register the scripts and styles your plugin or theme needs by a name, a
handle in the `vendor/name` form. Pages then ask for them by handle,
and each prints once, after the ones it `requires`.

## Registering an asset

Register it in your [provider's](../plugins/service-providers.md)
`boot()`:

```php
use Blush\Asset\Asset;
use Blush\Asset\AssetRegistry;
use Blush\Asset\Script;
use Blush\Asset\Style;

public function boot(): void
{
	$this->container->make(AssetRegistry::class)->register(new Asset(
		'acme/gallery',
		styles: [new Style('css/gallery.css', from: 'acme/gallery')],
		scripts: [new Script('js/gallery.js', from: 'acme/gallery', attributes: ['type' => 'module'], footer: true)],
		requires: ['core/player']
	));
}
```

A theme without a provider lists its assets in `theme.json` instead
(see [Themes](../themes/README.md)).

`from` says where a file is: your plugin's or theme's name for a file
in its folder (`/extensions/acme/gallery/js/gallery.js` for a plugin,
`/themes/acme/nova/...` for a theme), or leave it out for a URL as
given. Files are served with `?v=` and a hash of their contents, so a
browser fetches one again only when it changes. Only stylesheets,
scripts, fonts, and images are served, never files in `src/`,
`views/`, `lang/`, `resources/`, `vendor/`, or `node_modules/`, and a
plugin's only while it's on.

A script's `attributes` control how it loads: it's deferred unless they
say otherwise (`['defer' => false]`, `['async' => true]`, or a `type`
such as `module`). `footer: true` prints it at the end of the page,
where the theme's layout prints `$template->foot()`, instead of in the
`<head>`. A `Style` takes `attributes` too, such as
`['media' => 'print']`.

## Replacing a handle

Registering a handle that's already taken replaces it. Core's are
registered first, then plugins', then themes' (those in `theme.json`'s
`assets`, then their providers'), then the site's own providers' (in
[`src/`](../plugins/site-code.md)), so a theme can replace core's
`core/player` with its own, or with an empty `new Asset('core/player')`
to load nothing.

## Loading an asset

There are three ways to load an asset:

- **Where it's drawn.** A [block](blocks/README.md) or
  [component](components/README.md) lists the handles it needs in `ASSETS`,
  and they load on every page it's drawn on, an entry's text included.
- **From a template**: `$template->enqueue('acme/gallery')`.
- **On the pages that need it**, from a listener for
  `Blush\View\Events\PageRendering`, which every themed page, content or
  error, sends just before its templates render:

```php
use Blush\Event\Listener\ListenerRegistry;
use Blush\View\Events\PageRendering;

$this->container->get(ListenerRegistry::class)->listen(PageRendering::class, function (PageRendering $event): void {
	if ($event->page?->type?->name === 'gallery') {
		$event->enqueue('acme/gallery');
	}
});
```

The event has the `page` (`null` on an error page), its `entry`, the
error page's `status` (`isError()`), the `request`, the theme `chain`,
`path()`, and `locale()`. Besides `enqueue()` and `dequeue()`, it can
add `<body>` classes with `addClass()`, anything else to the `head`,
which Blush has already filled in, and scripts to the end of the page
through `foot` (`$event->foot->script($url)`); a template's value for
the same tag still wins. See [Events](events.md).

## Inline code and data

A script often needs a few lines to start it, or settings from the
page. Tie them to its handle, and they print beside its files, wherever
those print:

```php
$template->foot()->data('acme-gallery', ['columns' => 3, 'label' => $label], for: 'acme/gallery');
$template->foot()->inlineScript('acme-gallery-start', 'AcmeGallery.start();', after: 'acme/gallery');
```

```html
<script type="application/json" id="acme-gallery">{"columns":3,"label":"Photos"}</script>
<script src="/extensions/acme/gallery/js/gallery.js?v=…" defer></script>
<script type="module" id="acme-gallery-start">AcmeGallery.start();</script>
```

- **`data($id, $value, for:)`** prints the value as JSON in a
  `<script type="application/json">`, which never runs, just before the
  asset's first script. Read it in your script with
  `JSON.parse(document.getElementById('acme-gallery').textContent)`.
  It's safe for any text, `</script>` included.
- **`inlineScript($id, $js, after:)`** prints the code just after the
  asset's last script. When that script is deferred (the default) or a
  module, the code prints as a module, so it runs after the script
  rather than before it; inline code otherwise runs the moment the
  browser reaches it. Module code runs in strict mode, and its
  top-level variables stay its own.

Either one asks for the asset, so you don't need `enqueue()` as well.
If the asset has no script on the page (its plugin is off, or the
handle doesn't exist), they don't print, and in development the log
says so. Without `for:` or `after:`, data prints before the head's or
foot's scripts and inline code after them. Both are on the head and the
foot, and on `$event->head` and `$event->foot` in a `PageRendering`
listener.

## Text in scripts

A script that shows text writes it the way PHP does, with `t()` (see
[Text in PHP code](translations.md)), and names the catalog it reads:

```php
new Script('js/gallery.js', from: 'acme/gallery', attributes: ['type' => 'module'], catalog: 'site')
```

```js
import { messages } from 'blush/messages';

const t = messages('acme/gallery');

button.textContent = t('gallery.next', 'Next');
count.textContent = t('gallery.count', '{count, plural, one {# photo} other {# photos}}', { count: photos.length });
```

`t(key, text, params)` works as it does in PHP: the catalog's message
when it has the key, else the text beside it, formatted with its
values, which are named. Messages are ICU, as PHP's are (`plural`,
`select`, `selectordinal`, `number`, `date`, and `time`), formatted in
the page's language. Name your own plugin's or theme's domain in
`messages()`; `plugin:check` and `theme:check` warn about another.

A script's text goes in a **named catalog**,
`lang/{catalog}/{locale}.json` (`lang/site/fr.json`), apart from your
PHP's `lang/fr.json`, so a page only gets the text its scripts show.
Start each with `@@locale`, `@@domain`, and `@@catalog`. When a page
loads a script with a `catalog`, the catalog prints before it, once, in
the page's language, with the site's own wording over it
(`user/lang/fr/site/extensions/acme/gallery.json`; see
[Translations in a theme](../themes/translations.md)):

```html
<script type="importmap">{"imports":{"blush/messages":"https://example.com/blush/js/messages.js?v=…"}}</script>
<script type="application/json" data-blush-messages="acme/gallery" data-blush-catalog="site">{"gallery.next":"Suivant"}</script>
<script src="/extensions/acme/gallery/js/gallery.js?v=…" type="module"></script>
```

`blush/messages` is Blush's, mapped in the page's import map, so leave
it out of your build (in Vite, `build.rolldownOptions.external:
['blush/messages']`). Only module scripts can import it.

`lang:extract` reads your `.ts`, `.js`, and `.vue` files for `t()` calls
and writes each folder's to its catalog: by default, each folder under
`resources/` is a catalog of its name (`resources/site/**` writes to
`lang/site/en.json`). Name them yourself with `catalogs` in your
manifest, and a folder under two catalogs writes to both:

```json
"catalogs": {"site": ["resources/site", "resources/player"]}
```

A script from a URL has no catalog of its own.
