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

namespace Blush\Content\Schema\Fields;

use Override;
use Blush\Content\Schema\Field;
use Blush\Content\Schema\FieldContext;
use Blush\Content\Schema\FieldFactory;
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
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);

		return self::withShared(new static($definition->string('name')), $definition);
	}
}
