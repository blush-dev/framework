<?php

/**
 * View not found.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

/**
 * Thrown when no directory in the view chain has a view.
 */
final class ViewNotFound extends ViewException
{
	/**
	 * Builds the exception for the names that were tried.
	 *
	 * @param list<string> $names
	 */
	public static function forNames(array $names): self
	{
		return new self(sprintf('No view found for: %s.', implode(', ', $names)));
	}
}
