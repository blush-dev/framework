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
use Blush\Field\Control;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Support\Slug;

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
	public static function typeLabel(): string
	{
		return 'Slug';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(): string
	{
		return 'A URL-safe name.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function controls(): array
	{
		return [Control::Mono];
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
			throw $this->invalid(sprintf('must be a slug, not %s.', self::describe($value)));
		}

		if (! Slug::isSlug($value)) {
			throw $this->invalid(sprintf('"%s" is not a slug; try "%s".', $value, Slug::from($value)));
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
