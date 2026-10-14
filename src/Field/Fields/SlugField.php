<?php

/**
 * Slug field.
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
 * A URL slug, such as a `slug:` override. The value must already be a
 * slug; the error suggests the slug form of anything else.
 */
final class SlugField extends Field
{
	public function __construct(string $name = '')
	{
		$this->name = $name;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function type(): string
	{
		return 'slug';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeLabel(Messages $messages): Text
	{
		return $messages->t('field.slug_field.type_label', 'Slug');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(Messages $messages): Text
	{
		return $messages->t('field.slug_field.type_description', 'A URL-safe name.');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (is_int($value)) {
			$value = (string) $value;
		}

		if (! is_string($value)) {
			throw $this->invalid($context->messages->t('field.slug_field.invalid', 'must be a slug, not {value}.', value: self::describe($value, $context->messages)));
		}

		if (! Slug::isSlug($value)) {
			throw $this->invalid($context->messages->t('field.slug_field.not_a_slug', '"{value}" is not a slug; try "{slug}".', value: $value, slug: Slug::from($value)));
		}

		return $value;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function valueType(): array
	{
		return ['type' => ['string', 'integer']];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);

		return self::withShared(new static($definition->string('name')), $definition);
	}
}
