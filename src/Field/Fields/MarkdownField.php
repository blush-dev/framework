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

namespace Blush\Field\Fields;

use Override;
use Blush\Field\Control;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;

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
	public static function typeLabel(): string
	{
		return 'Formatted text';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(): string
	{
		return 'Formatted text.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function controls(): array
	{
		return [Control::Textarea];
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
	protected function valueType(): array
	{
		return ['type' => ['string', 'number']];
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
