<?php

/**
 * Media field set.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Blush\Field\Field;

/**
 * Metadata fields for media files (D-287): for every kind (`kind` null),
 * or for one kind. They're defined with the same field types as content
 * types' schemas (D-042):
 *
 *     new MediaFieldSet([new TextField('photographer')], MediaKind::Image)
 */
final readonly class MediaFieldSet
{
	/**
	 * @var list<Field>
	 */
	public array $fields;

	/**
	 * @param iterable<Field> $fields
	 * @param ?MediaKind      $kind   The kind they're for, or `null` for every kind.
	 */
	public function __construct(iterable $fields, public ?MediaKind $kind = null)
	{
		$this->fields = array_values([...$fields]);
	}

	/**
	 * Returns whether the set applies to a kind of file.
	 */
	public function appliesTo(MediaKind $kind): bool
	{
		return $this->kind === null || $this->kind === $kind;
	}
}
