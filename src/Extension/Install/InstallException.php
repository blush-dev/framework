<?php

/**
 * Install exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension\Install;

use RuntimeException;
use Throwable;
use Blush\Core\BlushException;
use Blush\Extension\ExtensionKind;

/**
 * Why an extension wasn't installed (D-392). Nothing was written. An
 * archive holding another kind of extension says which (`$kind`), so the
 * admin can offer that kind's screen.
 */
final class InstallException extends RuntimeException implements BlushException
{
	public function __construct(
		string $message,
		public readonly ?ExtensionKind $kind = null,
		?Throwable $previous = null
	) {
		parent::__construct($message, 0, $previous);
	}
}
