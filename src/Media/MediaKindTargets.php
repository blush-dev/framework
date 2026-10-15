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
use Blush\Field\FieldSlot;
use Blush\Field\FieldTargetSource;
use Blush\Translation\Messages;

/**
 * The kinds of media file as places fields go on (D-341),
 * `media:{kind}`.
 */
final readonly class MediaKindTargets implements FieldTargetSource
{
	public function __construct(private Messages $messages)
	{}

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
		return (string) $this->messages->t('media.media_kind_targets.label', 'Media files');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fieldTargets(): iterable
	{
		foreach (MediaKind::cases() as $kind) {
			yield new MediaKindTarget($kind, $this->messages);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function slots(): array
	{
		return [new FieldSlot('details', (string) $this->messages->t('media.media_kind_targets.details', 'Details'), (string) $this->messages->t('media.media_kind_targets.details_description', 'Facts about each file, beside its built-in ones.'))];
	}
}
