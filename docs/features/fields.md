# Fields from a plugin

A plugin registers the fields it saves, so Blush knows about them: on
content types (`type:post`, or `type:*` for every type, including ones
added later), media files (`media:image`), or Settings screens
(`settings:general`).

## Registering fields

Write a class that implements `FieldSource`:

```php
<?php

declare(strict_types=1);

namespace Acme\Seo;

use Blush\Field\FieldSource;
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\TextField;
use Blush\Field\RegisteredField;

final class SeoFields implements FieldSource
{
	public function fields(): iterable
	{
		yield new RegisteredField('acme/seo', new TextField('meta_title'), ['type:post', 'type:page']);
		yield new RegisteredField('acme/seo', new BoolField('noindex'), ['type:*']);
	}
}
```

Tag it with `FieldSource::TAG` in the plugin's
[provider](../plugins/service-providers.md), and name your plugin
(`acme/seo`) as each field's owner:

```php
protected const array TAGS = [
	FieldSource::TAG => [SeoFields::class]
];
```

A registered field is data: its values are checked and kept, and
`$entry->field('meta_title')` reads them. It isn't shown in the admin;
a way for plugins to add panels and controls comes later.

## Keys that clash

Each key is registered once on a type. If yours is already one of the
type's fields, Blush's own (`title`, `date`, and the rest), or another
plugin's, your field is left off that type and the site says so (Site
Health and `bin/blush content:lint`), so prefer keys unlikely to clash,
or keep several under one object (`seo: { title, description }`).

A field from the site's [field library](../concepts/custom-fields.md)
that uses a key you registered is left off instead, for the site to
rename.

To add a new kind of field rather than a field, see
[Field types](field-types.md).
