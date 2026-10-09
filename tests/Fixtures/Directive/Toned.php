<?php

/**
 * Toned directive fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Directive;

use Override;
use Blush\Content\Entries;
use Blush\Directive\Directive;
use Blush\Directive\DirectiveKind;
use Blush\Directive\DirectiveContent;

final class Toned extends Directive
{
	public const ?DirectiveKind KIND = DirectiveKind::Container;

	public const DirectiveContent CONTENT = DirectiveContent::Blocks;

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
