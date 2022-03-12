<?php
/**
 * Single controller.
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
use Blush\Template\Tags\DocumentTitle;
use Blush\Tools\Str;

class Single extends Controller {

	/**
	 * Callback method when route matches request.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array  $params
	 * @return Response
	 */
	public function __invoke( array $params = [] ) {
		$types = App::resolve( 'content/types' );

		// Get the post name and path.
		$name = $params['name'] ?? '';
		$path = Str::beforeLast( $params['path'] ?? '', "/{$name}" );

		// Get the content type by path.
		$type = $types->getTypeFromPath( $path );

		// Look for a `path/{$name}.md` file.
		$single = new Query( [
			'path' => $path,
			'slug' => $name
		] );

		if ( $single->all() ) {
			$type_name    = sanitize_with_dashes( $type->type() );
			$collection   = false;
			$collect_args = $single->first()->meta( 'collection' );

			if ( $collect_args ) {
				$collection = new Query( $collect_args );
			}

			$doctitle = new DocumentTitle( $single->first()->title() );

			return $this->response(
				$this->view( [
					"single-{$type_name}-{$name}",
					"single-{$type_name}",
					'single',
					'index'
				], [
					'doctitle'   => $doctitle,
					'single'     => $single->first(),
					'collection' => $single,
					'page'       => 1
				] )
			);
		}

		// If all else fails, return a 404.
		return $this->forward( Error404::class, $params );
	}
}
