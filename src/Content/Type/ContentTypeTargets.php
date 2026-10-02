<?php

/**
 * Content type targets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Override;
use Blush\Field\FieldSets;
use Blush\Field\FieldSlot;
use Blush\Field\FieldTargetSource;

/**
 * The site's content types as places field sets attach to (D-337),
 * `type:{name}`, by plural name.
 */
final readonly class ContentTypeTargets implements FieldTargetSource
{
	public function __construct(private ContentTypes $types)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function kind(): string
	{
		return ContentTypeTarget::KIND;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Content types';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fieldTargets(): iterable
	{
		$types = array_values($this->types->all());

		usort($types, static fn (ContentType $a, ContentType $b): int => strnatcasecmp($a->labels->plural, $b->labels->plural));

		foreach ($types as $type) {
			yield new ContentTypeTarget($this->types, $type);
		}
	}

	/**
	 * @inheritDoc
	 *
	 * One, for now: the entry editor keeps fields in its document panel,
	 * out of the writing area (D-348).
	 */
	#[Override]
	public function slots(): array
	{
		return [new FieldSlot('details', 'Details', 'Facts about each entry, such as notes or search engine settings.')];
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
