<?php

/**
 * Markdown field.
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

/**
 * Markdown source, such as a summary. It is stored as written and rendered
 * when used.
 */
final class MarkdownField extends Field
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
		return 'markdown';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (is_string($value)) {
			return $value;
		}

		if (is_int($value) || is_float($value)) {
			return (string) $value;
		}

		throw $this->invalid(sprintf('must be text, not %s.', self::describe($value)));
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
