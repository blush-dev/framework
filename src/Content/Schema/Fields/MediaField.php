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

namespace Blush\Content\Schema\Fields;

use Override;
use Blush\Content\Schema\Field;
use Blush\Content\Schema\FieldContext;
use Blush\Content\Schema\FieldFactory;
use Blush\Content\Schema\InvalidSchema;
use Blush\Media\MediaKind;

/**
 * A reference to a media file: a site path such as
 * `/user/media/2019/01/artemis.jpg`, or an absolute URL. It's stored as written; media resolution comes with the
 * media layer (M4c).
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
	public function normalize(mixed $value, FieldContext $context): mixed
	{
		if (! is_string($value) || trim($value) === '') {
			throw $this->invalid(sprintf('must be a media path or URL, not %s.', self::describe($value)));
		}

		return trim($value);
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
