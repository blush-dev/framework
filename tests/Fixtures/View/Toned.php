<?php

/**
 * Toned component fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\View;

use Blush\Content\ContentRepository;
use Blush\View\Component\Component;
use Blush\View\Component\ComponentContent;

final class Toned extends Component
{
	public const ComponentContent CONTENT = ComponentContent::Blocks;

	public function __construct(
		private readonly ContentRepository $content,
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
}
