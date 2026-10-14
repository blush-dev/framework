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
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Support\Slug;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Slugs of entries of another content type, such as taxonomy terms or
 * authors:
 *
 *     new ReferenceField('authors', 'author')->aliases('author')
 *
 * A single value counts as a list of one (D-078), and every value is
 * turned into a slug the way 1.x did, so `category: Book Reviews` refers
 * to `book-reviews`. A tree's page is named by its path, each part a
 * slug (`about/team`, D-590). With `multiple: false`, one slug is stored instead of
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
	public static function typeLabel(Messages $messages): Text
	{
		return $messages->t('field.reference_field.type_label', 'Reference');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(Messages $messages): Text
	{
		return $messages->t('field.reference_field.type_description', 'Other entries, by slug.');
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
			throw $this->invalid($context->messages->t('field.reference_field.map', 'must be a slug or a list of slugs, not a map.'));
		}

		$slugs = [];

		foreach ($value as $item) {
			if (! is_string($item) && ! is_int($item)) {
				throw $this->invalid($context->messages->t('field.reference_field.invalid', 'must be a slug or a list of slugs; found {value}.', value: self::describe($item, $context->messages)));
			}

			$slug = implode('/', array_filter(array_map(Slug::from(...), explode('/', (string) $item)), static fn (string $part): bool => $part !== ''));

			if ($slug !== '') {
				$slugs[$slug] = $slug;
			}
		}

		$slugs = array_values($slugs);

		if ($this->multiple) {
			return $slugs;
		}

		if (count($slugs) > 1) {
			throw $this->invalid($context->messages->t('field.reference_field.one', 'takes one slug, not a list.'));
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
