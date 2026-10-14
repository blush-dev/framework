<?php

/**
 * Install clash.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension\Install;

use RuntimeException;
use Blush\Core\BlushException;
use Blush\Translation\Messages;

/**
 * An archive holds an extension that's already installed, and replacing
 * it wasn't asked for (D-392). Nothing was written; asking again with
 * `replace` replaces it.
 */
final class InstallClash extends RuntimeException implements BlushException
{
	public function __construct(
		public readonly ExtensionPackage $installed,
		public readonly ExtensionPackage $incoming,
		Messages $messages
	) {
		parent::__construct((string) $messages->t('extension.install_clash.installed', '{label} is already installed.', label: $installed->label));
	}
}
