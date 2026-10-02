<?php

/**
 * Fixture: a class-backed component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Component;

use Override;
use Blush\Component\Component;

final class Card extends Component
{
	public readonly string $heading;

	public function __construct(
		public readonly string $title = '',
		public readonly int $columns = 1,
		public readonly bool $wide = false,
		private readonly string $secret = 'hidden'
	) {
		$this->heading = strtoupper($title);
	}

	#[Override]
	public function shouldRender(): bool
	{
		return $this->title !== 'skip';
	}

	#[Override]
	public function template(): ?string
	{
		return $this->wide ? 'components/card-wide' : null;
	}

	public function secret(): string
	{
		return $this->secret;
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
