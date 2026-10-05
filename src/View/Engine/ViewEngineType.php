<?php

/**
 * View engine types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Engine;

/**
 * The built-in view engines, keyed by the file extension they render (the
 * "Type enum" of the enum + registry pattern, D-019; D-502).
 */
enum ViewEngineType: string
{
	case Php = 'php';

	/**
	 * Returns the engine's class.
	 *
	 * @return class-string<ViewEngine>
	 */
	public function engine(): string
	{
		return match ($this) {
			self::Php => PhpEngine::class
		};
	}
}
