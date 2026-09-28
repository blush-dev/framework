<?php

/**
 * URL menu link.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu\Link;

use Override;
use Blush\View\Escaper;

/**
 * Links to a URL as written: `url: https://github.com/…` or `url: /about`.
 * It brings no label, so the item names itself. A URL with an unsafe
 * scheme (`javascript:`) is refused.
 */
final class UrlLink extends MenuLink
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function validate(mixed $value, array $item): ?string
	{
		$problem = parent::validate($value, $item);

		if ($problem !== null || ! is_string($value)) {
			return $problem;
		}

		return Escaper::url($value) === '' ? 'isn\'t a safe URL.' : null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function resolve(string $value, array $item, string $locale): LinkTarget
	{
		return new LinkTarget(trim($value));
	}
}
