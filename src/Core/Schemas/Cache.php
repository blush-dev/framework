<?php
/**
 * Cache configuration schema.
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

class Cache
{
	/**
	 * Returns the schema structure.
	 *
	 * @since 1.0.0
	 */
	public static function schema(): Schema
	{
		return Expect::structure( [
			'purge_key'            => Expect::string()->default( '' ),
			'expires'              => Expect::int()->default( 0 ),
			'content_exclude_meta' => Expect::array()->default( [] ),
			'global'               => Expect::bool()->default( false ),
			'global_exclude'       => Expect::array()->default( [] ),
			'stores'               => Expect::arrayOf( 'array',  'string' )->default( [] ),
			'drivers'              => Expect::arrayOf( 'string', 'string' )->default( [] )
		] );
	}
}
