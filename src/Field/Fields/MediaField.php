<?php

/**
 * Media field.
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
use Blush\Field\Controls\Audio;
use Blush\Field\Controls\File;
use Blush\Field\Controls\Image;
use Blush\Field\Controls\Video;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Field\InvalidSchema;
use Blush\Media\MediaKind;
use Blush\Support\Uuid;

/**
 * A media file, by the id of its original (D-487, D-773), kept in lower
 * case. A value written before ids, a path such as
 * `/media/2019/01/artemis.jpg` (or an address elsewhere), is still taken
 * and read (`MediaValues`), and `content:lint` and Site Health report it
 * until `content:media-ids` rewrites it.
 *
 * A `kind` (`image`, `video`, `audio`, or `file`) says which kind of file
 * it takes (D-314): the admin's picker then offers only those. It isn't
 * checked on save, since a URL's kind can't be known.
 */
final class MediaField extends Field
{
	public function __construct(string $name = '', public readonly ?MediaKind $kind = null)
	{
		$this->name = $name;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function type(): string
	{
		return 'media';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeLabel(): string
	{
		return 'Media file';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function typeDescription(): string
	{
		return 'A media file.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function defaultControl(): Control
	{
		return match ($this->kind) {
			MediaKind::Image => new Image(),
			MediaKind::Video => new Video(),
			MediaKind::Audio => new Audio(),
			default          => new File()
		};
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (! is_string($value) || trim($value) === '') {
			throw $this->invalid(sprintf('must be a media file\'s id, not %s.', self::describe($value)));
		}

		return Uuid::isValid(trim($value)) ? strtolower(trim($value)) : trim($value);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function valueType(): array
	{
		return ['type' => 'string'];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data, FieldFactory $factory): static
	{
		$definition = self::definition($data);
		$kind       = $definition->nullableString('kind');
		$known      = $kind === null ? null : MediaKind::tryFrom($kind);

		if ($kind !== null && $known === null) {
			throw new InvalidSchema(sprintf('Field "%s" has an unknown media kind "%s"; use image, video, audio, or file.', $definition->string('name'), $kind));
		}

		return self::withShared(new static($definition->string('name'), $known), $definition);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function definitionSchema(array $field): array
	{
		return [
			'kind' => ['enum' => array_column(MediaKind::cases(), 'value'), 'description' => 'The kind of file it takes, so the admin\'s picker offers only those.']
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function options(): array
	{
		return ['kind' => $this->kind?->value];
	}
}
