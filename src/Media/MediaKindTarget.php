<?php

/**
 * Media kind target.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Override;
use Blush\Field\Field;
use Blush\Field\FieldTarget;
use Blush\Field\Schema;
use Blush\Translation\Messages;

/**
 * A kind of media file as a place field sets attach to (D-341):
 * `media:image`, `media:video`, `media:audio`, or `media:file`. Its own
 * fields are the built-in ones (`MediaSchemas::builtIn()`). A metadata
 * file holds any value front matter can, so it takes every field.
 */
final readonly class MediaKindTarget implements FieldTarget
{
	/**
	 * The kind of target, before the colon.
	 */
	public const string KIND = 'media';

	public function __construct(
		private MediaKind $kind,
		private Messages $messages
	) {}

	/**
	 * Returns the target key for a kind of file.
	 */
	public static function keyFor(MediaKind $kind): string
	{
		return self::KIND . ':' . $kind->value;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function key(): string
	{
		return self::keyFor($this->kind);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return (string) $this->kind->label($this->messages);
	}

	/**
	 * @inheritDoc
	 *
	 * Not a field named (or aliased) for what a file's details keep that
	 * isn't a field: its id, owner, upload date, renditions, or artwork.
	 */
	#[Override]
	public function accepts(Field $field): bool
	{
		return array_intersect([$field->name, ...$field->aliases], [MediaMetadata::ID, MediaMetadata::OWNER, MediaMetadata::UPLOADED, MediaMetadata::RENDITIONS, MediaMetadata::ARTWORK]) === [];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function schema(): Schema
	{
		return MediaSchemas::builtIn($this->kind, $this->messages);
	}
}
