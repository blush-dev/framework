<?php

/**
 * Trusted HTML.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Override;

/**
 * HTML a template marked as trusted with `raw()` (D-559). It prints as
 * is, `e()` leaves it alone, and a translation (`$template->t()`) puts
 * it in its message unescaped while escaping the rest.
 */
final readonly class TrustedHtml implements SafeHtml
{
	public function __construct(private string $html)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function __toString(): string
	{
		return $this->html;
	}
}
