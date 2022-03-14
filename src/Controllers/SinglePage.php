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

use Blush\App;
use Blush\Content\Query;
use Blush\Template\Tags\DocumentTitle;
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\Response;

class SinglePage extends Single
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since 1.0.0
	 */
	public function __invoke( array $params = [] ) : Response
	{
		$path = $params['path'] ?? '';
		$name = Str::afterLast( $path, '/' );

		// Look for an `path/index.md` file.
		$single = new Query( [
			'path' => $path,
			'slug' => 'index'
		] );

		// Look for a `path/{$name}.md` file if `path/index.md` not found.
		if ( ! $single->all() ) {
			$single = new Query( [
				'path' => Str::beforeLast( $path, '/' ),
				'slug' => $name
			] );
		}

		if ( $single->all() ) {
			$collection   = false;
			$collect_args = $single->first()->meta( 'collection' );

			if ( $collect_args ) {
				$collection = new Query( $collect_args );
			}

			$doctitle = new DocumentTitle( $single->first()->title() );

			return $this->response( $this->view( [
				"single-page-{$name}",
				'single-page',
				'single',
				'index'
			], [
				'doctitle'   => $doctitle,
				'pagination' => false,
				'single'     => $single->first(),
				'collection' => $collection ?: false
			] ) );
		}

		// If all else fails, return a 404.
		return $this->forward404( $params );
	}
}
