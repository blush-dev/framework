<?php
/**
 * App configuration schema.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core\Schemas;

use Nette\Schema\Expect;
use Nette\Schema\Schema;

class App
{
	/**
	 * Returns the schema structure.
	 *
	 * @since 1.0.0
	 */
	public static function schema(): Schema
	{
		return Expect::structure( [
			'url'         => Expect::string( 'http://localhost' ),
			'title'       => Expect::string( 'Blush' ),
			'tagline'     => Expect::string( '' ),
			'timezone'    => Expect::string( 'America/Chicago' ),
			'date_format' => Expect::string( 'F j, Y' ),
			'time_format' => Expect::string( 'g:i a' ),
			'home_alias'  => Expect::string( '' ),
			'providers'   => Expect::array( [] ),
			'proxies'     => Expect::array( [] ),

			// @deprecated 1.0.0 Soft deprecation in favor of `url`.
			'uri'         => Expect::string( '' )
		] );
	}
}
