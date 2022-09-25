<?php
/**
 * Content configuration schema.
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

class Content
{
	/**
	 * Returns the schema structure.
	 *
	 * @since 1.0.0
	 */
	public static function schema(): Schema
	{
		return Expect::arrayOf( Expect::structure( [
			'path'            => Expect::string(),
			'collect'         => Expect::type( 'string|bool' )->nullable(),
			'collection'      => Expect::array(),
			'feed'            => Expect::type( 'bool|array' )->nullable(),
			'date_archives'   => Expect::bool( false ),
			'time_archives'   => Expect::bool( false ),
			'url_paths'       => Expect::arrayOf( 'string', 'string' ),
			'uri'             => Expect::string(),
			'uri_single'      => Expect::string(),
			'routing'         => Expect::bool( true ),
			'routes'          => Expect::arrayOf( 'string', 'string' ),
			'taxonomy'        => Expect::bool( false ),
			'term_collect'    => Expect::string()->nullable(),
			'term_collection' => Expect::array()
		] ), 'string' );
	}
}
