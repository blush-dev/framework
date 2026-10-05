<?php

/**
 * Role origin.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * Where a role comes from (D-312), which decides whether the admin may
 * change it.
 */
enum RoleOrigin: string
{
	// A built-in role as the framework defines it.
	case BuiltIn = 'built-in';

	// A built-in role whose capabilities were changed in the admin.
	case Changed = 'changed';

	// A role made in the admin.
	case Custom = 'custom';

	// A role defined (or redefined) in `config/auth.php`.
	case Config = 'config';

	/**
	 * Whether the admin may change the role. The owner always keeps every
	 * capability (D-500), the member never has one (D-365), and config
	 * belongs to the site's code.
	 */
	public function editable(string $name): bool
	{
		return $this !== self::Config && BuiltInRole::tryFrom($name)?->isFixed() !== true;
	}
}
