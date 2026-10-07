<?php

/**
 * Reference field.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field\Fields;

use Override;
use Blush\Field\Control;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Support\Slug;

/**
 * Slugs of entries of another content type, such as taxonomy terms or
 * authors:
 *
 *     new ReferenceField('authors', 'author')->aliases('author')
 *
 * A single value counts as a list of one (D-078), and every value is
 * turned into a slug the way 1.x did, so `category: Book Reviews` refers
 * to `book-reviews`. With `multiple: false`, one slug is stored instead of
 * a list. Whether the entries exist is checked by `content:lint`; a
 * missing one is left out of the site (D-584).
 */
final class ReferenceField extends Field
{
	public function __construct(
		string $name = '',
		public readonly string $to = '',
		public readonly bool $multiple = true
	) {
		$this->name = $name;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function type(): string
	{
		return 'reference';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeLabel(): string
	{
		return 'Reference';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(): string
	{
		return 'Other entries, by slug.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function controls(): array
	{
		return [Control::Reference, Control::Mono];
	}

	/**
	 * @inheritDoc
	 *
	 * The entry picker needs the type it picks from (`to`); without one,
	 * slugs are typed.
	 */
	#[Override]
	public function canUse(Control $control): bool
	{
		return $control === Control::Mono || ($control === Control::Reference && $this->to !== '');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (! is_array($value)) {
			$value = [$value];
		} elseif (! array_is_list($value)) {
			throw $this->invalid('must be a slug or a list of slugs, not a map.');
		}

		$slugs = [];

		foreach ($value as $item) {
			if (! is_string($item) && ! is_int($item)) {
				throw $this->invalid(sprintf('must be a slug or a list of slugs; found %s.', self::describe($item)));
			}

			$slug = Slug::from((string) $item);

			if ($slug !== '') {
				$slugs[$slug] = $slug;
			}
		}

		$slugs = array_values($slugs);

		if ($this->multiple) {
			return $slugs;
		}

		if (count($slugs) > 1) {
			throw $this->invalid('takes one slug, not a list.');
		}

		return $slugs[0] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function definitionSchema(array $field): array
	{
		return [
			'to'       => ['type' => 'string', 'description' => 'The content type the entries are of.'],
			'multiple' => ['type' => 'boolean', 'description' => 'Whether the field holds several entries.', 'default' => true]
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function valueType(): array
	{
		$slug = ['type' => ['string', 'integer']];

		return $this->multiple ? ['anyOf' => [['type' => 'array', 'items' => $slug], $slug]] : $slug;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);

		return self::withShared(
			new static($definition->string('name'), $definition->string('to'), $definition->bool('multiple', true)),
			$definition
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function options(): array
	{
		return ['to' => $this->to, 'multiple' => $this->multiple ? null : false];
	}
}
