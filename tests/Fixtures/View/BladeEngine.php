<?php

/**
 * Fixture: a view engine for `.blade.php` files.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\View;

use Override;
use Blush\View\Engine\ViewEngine;
use Blush\View\Template;

final class BladeEngine implements ViewEngine
{
	#[Override]
	public function render(string $file, array $data, Template $template): string
	{
		return 'blade:' . file_get_contents($file);
	}
}
