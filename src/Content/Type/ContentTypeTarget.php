<?php

/**
 * Content type target.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Override;
use Blush\Field\Field;
use Blush\Field\FieldTarget;
use Blush\Field\InvalidSchema;
use Blush\Field\Schema;

/**
 * A content type as a place field sets attach to (D-337): `type:{name}`.
 * Entries store any value front matter can hold and the index reads
 * references, so a type takes every field.
 */
final readonly class ContentTypeTarget implements FieldTarget
{
	/**
	 * The kind of target, before the colon.
	 */
	public const string KIND = 'type';

	public function __construct(
		private ContentTypes $types,
		private ContentType $type
	) {}

	/**
	 * Returns the target key for a type's name.
	 */
	public static function keyFor(string $name): string
	{
		return self::KIND . ':' . $name;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function key(): string
	{
		return self::keyFor($this->type->name);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return $this->type->labels->plural;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function accepts(Field $field): bool
	{
		return true;
	}

	/**
	 * @inheritDoc
	 *
	 * A type's clash is thrown on as the cause, so it reads as the type's.
	 */
	#[Override]
	public function schema(): Schema
	{
		try {
			return $this->types->ownSchema($this->type->name);
		} catch (InvalidContentType $e) {
			throw new InvalidSchema($e->getMessage(), previous: $e);
		}
	}
}
