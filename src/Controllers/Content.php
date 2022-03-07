<?php
/**
 * Content controller.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Controllers;

use Blush\Proxies\App;
use Blush\Content\Query;

class Content extends Controller {

	public function __invoke( array $params = [] ) {
		$this->params = $params;
		$types = App::resolve( 'content/types' );

		$path   = $this->getParameter( 'path' );
		$name   = $this->getParameter( 'name' );
		$number = $this->getParameter( 'number' );

		// Check if this is a content type archive.
		if ( $types->getTypeFromPath( $path ) ) {
			$controller = new ContentTypeArchive();
			return $controller( $params );
		}

		// Check if this is a paged content type archive.
		if ( $number && $types->getTypeFromPath( $name ) ) {
			$controller = new ContentTypeArchive();
			return $controller( $params );
		}

		// Check if this is a single request.
		if ( $name ) {
			$controller = new Single();
			return $controller( $params );
		}

		// If all else fails, return a 404.
		$controller = new Error404();
		return $controller( $params );
	}
}
