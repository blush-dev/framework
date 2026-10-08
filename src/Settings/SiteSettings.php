<?php

/**
 * Site settings.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Settings;

use Blush\Field\FieldContext;
use Blush\Field\FieldSets;
use Blush\Field\InvalidSchema;
use Blush\Field\Schema;
use Blush\Field\SchemaResult;

/**
 * The settings field sets add to the Settings screens (D-343), for
 * themes (`$template->site('tagline')`) and extensions: each value saved
 * in `user/data/settings.json`'s `site` section, read through its field
 * (normalized, then hydrated, as an entry's fields are), or the field's
 * default. A saved value that no longer fits its field is left out, as
 * if it weren't saved; one whose set is gone is ignored.
 */
final class SiteSettings
{
	private ?Schema $schema = null;

	/**
	 * @var ?array<string, mixed>
	 */
	private ?array $values = null;

	public function __construct(
		private readonly FieldSets $sets,
		private readonly SettingsStore $store,
		private readonly FieldContext $context
	) {}

	/**
	 * Returns a setting's value, or `$default` when it has none and its
	 * field has no default.
	 */
	public function get(string $name, mixed $default = null): mixed
	{
		return $this->all()[$name] ?? $default;
	}

	/**
	 * Returns every setting with a value, by field name.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array
	{
		if ($this->values !== null) {
			return $this->values;
		}

		try {
			$saved = $this->store->read()->site();
		} catch (InvalidSetting) {
			$saved = [];
		}

		$result = $this->resolve($saved);

		return $this->values = $this->schema()->hydrate($result->values, $this->context);
	}

	/**
	 * Resolves saved values against the settings' fields: what fits, what
	 * doesn't, and keys no field has.
	 *
	 * @param array<array-key, mixed> $values
	 */
	public function resolve(array $values): SchemaResult
	{
		return $this->schema()->resolve($values, $this->context);
	}

	/**
	 * Returns the settings' fields, as one schema.
	 */
	public function schema(): Schema
	{
		try {
			return $this->schema ??= new Schema(SettingsTargets::fields($this->sets));
		} catch (InvalidSchema) {
			// Two sets alias one name: `content:lint` and the admin say so.
			return $this->schema = new Schema();
		}
	}
}
