<?php

/**
 * Registered field.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * A field registered in code (D-790): data Blush knows about, on the
 * places it names, such as `type:post`, or `type:*` for every type. It's
 * registered by its owner: `core`, or an extension's `vendor/name`.
 * Registering a field doesn't show it anywhere; showing it is the
 * developer's, through the admin, later (D-791).
 *
 *     new RegisteredField('acme/seo', new TextField('meta_title'), ['type:post', 'type:page'])
 */
final readonly class RegisteredField
{
	/**
	 * The owner of core's fields.
	 */
	public const string CORE = 'core';

	/**
	 * What an extension's owner may be: a vendor, a slash, and a name.
	 */
	public const string OWNER_PATTERN = '/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/';

	/**
	 * @param  list<string> $targets The places it's on, all of one kind.
	 * @throws InvalidSchema When the owner or a target isn't valid, there are none, or they mix kinds.
	 */
	public function __construct(
		public string $owner,
		public Field $field,
		public array $targets
	) {
		if ($owner !== self::CORE && preg_match(self::OWNER_PATTERN, $owner) !== 1) {
			throw new InvalidSchema(sprintf('Field "%s" is registered by "%s"; name the extension registering it, such as "acme/seo".', $field->name, $owner));
		}

		if ($targets === []) {
			throw new InvalidSchema(sprintf('%s\'s field "%s" names no places, such as "type:post".', $owner, $field->name));
		}

		foreach ($targets as $target) {
			if (preg_match(FieldSet::TARGET_PATTERN, $target) !== 1) {
				throw new InvalidSchema(sprintf('%s\'s field "%s" target "%s" must be a kind and a name, such as "type:post".', $owner, $field->name, $target));
			}
		}

		if (count(array_unique(array_map(static fn (string $target): string => (string) strstr($target, ':', true), $targets))) > 1) {
			throw new InvalidSchema(sprintf('%s\'s field "%s" is on more than one kind of place; register it for each.', $owner, $field->name));
		}
	}

	/**
	 * Returns whether it's core's.
	 */
	public function isCore(): bool
	{
		return $this->owner === self::CORE;
	}

	/**
	 * Returns whether it's on a place, by its key: named, or as one of
	 * every place of its kind.
	 */
	public function attachesTo(string $target): bool
	{
		return FieldSet::targets($this->targets, $target);
	}

	/**
	 * Returns it as data for a compiled cache.
	 *
	 * @return array{owner: string, targets: list<string>, field: array<string, mixed>}
	 */
	public function toArray(): array
	{
		return ['owner' => $this->owner, 'targets' => $this->targets, 'field' => $this->field->toArray()];
	}

	/**
	 * Rebuilds it from `toArray()`'s output.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidSchema
	 */
	public static function fromArray(array $data, FieldFactory $factory): self
	{
		$definition = new Definition($data, 'A registered field');
		$field      = $data['field'] ?? null;

		if (! is_array($field)) {
			throw new InvalidSchema('A registered field needs its "field".');
		}

		return new self($definition->string('owner'), $factory->fromArray($field), $definition->strings('targets'));
	}
}
