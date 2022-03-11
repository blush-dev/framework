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
use Blush\Tools\Str;

class SinglePage extends Controller {

	/**
	 * Callback method when route matches request.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array  $params
	 * @return Response
	 */
	public function __invoke( array $params = [] ) {

		$path = $params['path'] ?? '';
		$name = Str::afterLast( $path, '/' );

		$views = [
			"single-page-{$name}",
			'single-page',
			'single'
		];

		// Look for an `path/index.md` file.
		$single = new Query( $path, [ 'slug' => 'index' ] );

		// Look for a `path/{$name}.md` file if `path/index.md` not found.
		if ( ! $single->all() ) {
			$single = new Query(
				Str::beforeLast( $path, '/' ),
				[ 'slug' => $name ]
			);
		}

		if ( $single->all() ) {
			return $this->response(
				$this->view( $views, [
					'title'      => $single->first()->title(),
					'single'     => $single->first(),
					'collection' => $single,
					'query'      => $single->first(),
					'entries'    => $single,
					'page'       => 1
				] )
			);
		}

		// If all else fails, return a 404.
		return $this->forward( Error404::class, $params );
	}
}
