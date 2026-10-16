# Templates for single entries

An entry can name a template of your theme in its `template` front
matter, which comes before the [template hierarchy](template-hierarchy.md).
To offer one in the admin's **Template** row, declare it from your
theme's provider (`provider` in [`theme.json`](theme-json.md)), in
`boot()`:

```php
use Blush\View\EntryTemplate;
use Blush\View\EntryTemplateRegistry;

$this->container->get(EntryTemplateRegistry::class)->register(new EntryTemplate(
	'template-compact',
	label: $this->t('templates.compact.label', 'Compact List'),
	description: $this->t('templates.compact.description', 'Every post on a line of its own, its title and date.'),
	types: ['page']
));
```

- **The name** is the view's path under `views/` without `.php`, so
  `template-compact` is `views/template-compact.php`.
- **The label and description** are what the row's menu shows, in the
  site's language ([Translations](translations.md)). Plain strings work
  too, shown as written.
- **The types** are the content types it's offered on, by name. Leave
  them out to offer it on every type.

## Where it's offered

The row lists only the templates with a view in your theme (or a theme
it builds on), and only on single entries: never on a type's index
page, an archive's page, or an error page. Choosing one writes its name
to `template`; **Default** takes `template` out. `bin/blush theme:check`
warns about a declared template with no view.
