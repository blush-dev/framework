<?php

/**
 * Media kind targets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Override;
use Blush\Field\FieldSets;
use Blush\Field\FieldSlot;
use Blush\Field\FieldTargetSource;

/**
 * The kinds of media file as places field sets attach to (D-341),
 * `media:{kind}`.
 */
final readonly class MediaKindTargets implements FieldTargetSource
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function kind(): string
	{
		return MediaKindTarget::KIND;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Media files';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fieldTargets(): iterable
	{
		foreach (MediaKind::cases() as $kind) {
			yield new MediaKindTarget($kind);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function slots(): array
	{
		return [new FieldSlot('details', 'Details', 'Facts about each file, beside its built-in ones.')];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function conflicts(FieldSets $sets): array
	{
		return [];
	}
}
