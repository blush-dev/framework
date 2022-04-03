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
			'uri'         => Expect::string( '' ),
			'title'       => Expect::string()->default( 'Blush' ),
			'tagline'     => Expect::string()->default( '' ),
			'timezone'    => Expect::string()->default( 'America/Chicago' ),
			'date_format' => Expect::string()->default( 'F j, Y' ),
			'time_format' => Expect::string()->default( 'g:i a' ),
			'home_alias'  => Expect::string()->default( '' ),
			'providers'   => Expect::array()->default( [] ),
			'proxies'     => Expect::array()->default( [] )
		] );
	}
}
