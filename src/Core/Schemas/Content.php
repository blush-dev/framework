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
			'path'            => Expect::string()->default( '' ),
			'collect'         => Expect::type( 'string|bool|null' )->default( null ),
			'collection'      => Expect::array()->default( [] ),
			'date_archives'   => Expect::bool()->default( false ),
			'uri'             => Expect::string()->default( '' ),
			'uri_single'      => Expect::string()->default( '' ),
			'routing'         => Expect::bool()->default( true ),
			'routes'          => Expect::arrayOf( 'string', 'string' )->default( [] ),
			'taxonomy'        => Expect::bool()->default( false ),
			'term_collect'    => Expect::type( 'string|bool|null' )->default( null ),
			'term_collection' => Expect::array()->default( [] )
		] ), 'string' )->default( [] );
	}
}
