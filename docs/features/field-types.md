# Field types

A plugin can add a field type for every place fields are defined:
content types, media details, theme settings, and menu fields.

## Writing a field type

Extend `Blush\Field\Field` and describe the type for the admin with
`typeLabel()` and `typeDescription()`. A field is data only: it never
says how it's drawn. The control it's entered with by default is
registered beside it (below); without one, it's shown read-only.

```php
<?php

declare(strict_types=1);

namespace Acme\Colors;

use Override;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;

final class ColorField extends Field
{
	public function __construct(string $name = '')
	{
		$this->name = $name;
	}

	#[Override]
	public function type(): string
	{
		return 'color';
	}

	#[Override]
	public static function typeLabel(): string
	{
		return 'Color';
	}

	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (! is_string($value) || preg_match('/^#[0-9a-f]{6}$/i', $value) !== 1) {
			throw $this->invalid('must be a hex color, such as #ff6600.');
		}

		return strtolower($value);
	}

	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);

		return self::withShared(new static($definition->string('name')), $definition);
	}
}
```

## Registering it

Register it, and its default control, in the plugin's
[provider's](../plugins/service-providers.md) `boot()`:

```php
use Blush\Field\ControlDefaults;
use Blush\Field\Controls\Code;
use Blush\Field\FieldRegistry;

public function boot(): void
{
	$this->container->get(FieldRegistry::class)->register('color', Acme\Colors\ColorField::class);
	$this->container->get(ControlDefaults::class)->register('color', Code::class);
}
```

Then `type: color` works in `user/data/fields` and the rest, and the
admin's field editor offers it.

## Controls and options

Controls (`Blush\Field\Controls\*`) come only from Blush for now, so a
field type picks from those; it can't bring its own. A library field's
definition may choose its default control, or another whose `fits()`
takes the field (`"control": {"name": "code"}`).

Options of its own, described by `definitionSchema()`, are offered in
the field editor when they're text, numbers, true or false, a set of
values, or a list of text.
