<?php

/**
 * Toned block fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Block;

use Override;
use Blush\Content\Entries;
use Blush\Block\Block;
use Blush\Block\BlockKind;
use Blush\Block\BlockContent;

final class Toned extends Block
{
	public const ?BlockKind KIND = BlockKind::Container;

	public const BlockContent CONTENT = BlockContent::Blocks;

	public function __construct(
		private readonly Entries $content,
		public readonly string $heading,
		public readonly Tone $tone = Tone::Quiet,
		public readonly int $level = 2,
		public readonly bool $open = false,
		private readonly string $internal = ''
	) {}

	public function count(): int
	{
		return $this->content->query()->limit(null)->get()->count();
	}

	public function internal(): string
	{
		return $this->internal;
	}

	/**
	 * No markup of its own: its template is a theme's.
	 */
	#[Override]
	public function render(): null
	{
		return null;
	}
}
