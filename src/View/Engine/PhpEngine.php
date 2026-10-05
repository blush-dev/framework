<?php

/**
 * PHP engine.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Engine;

use Closure;
use Error;
use Override;
use Throwable;
use Blush\View\Template;
use Blush\View\ViewException;

/**
 * Renders plain PHP templates (D-009), the built-in engine (D-502).
 *
 * A template runs in an isolated scope: its data become variables, and
 * `$template` is its `Template`, which exposes only its public API
 * (D-158); `$this` isn't available. Output buffers the template opens
 * (such as a section it never stopped) are closed whatever happens.
 */
final class PhpEngine implements ViewEngine
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(string $file, array $data, Template $template): string
	{
		$level = ob_get_level();

		ob_start();

		try {
			self::includer($template)($file, $data);
		} catch (Throwable $exception) {
			while (ob_get_level() > $level) {
				ob_end_clean();
			}

			if ($exception instanceof Error && str_contains($exception->getMessage(), 'Using $this')) {
				throw new ViewException(sprintf('Views use $template, not $this, in view %s', $file), 0, $exception);
			}

			throw $exception;
		}

		while (ob_get_level() > $level + 1) {
			ob_end_clean();
		}

		return (string) ob_get_clean();
	}

	/**
	 * Returns a function that includes a template file with the template
	 * as `$template` and no object or class scope, so the file sees its
	 * data and `Template`'s public API only (D-158). A data key named
	 * `template` is ignored; so are `__data` and `__file`, and `$__file`
	 * stays in scope.
	 *
	 * @return Closure(string, array<string, mixed>): void
	 */
	private static function includer(Template $template): Closure
	{
		return static function (string $__file, array $__data) use ($template): void {
			extract($__data, EXTR_SKIP);
			unset($__data);

			include func_get_arg(0);
		};
	}
}
