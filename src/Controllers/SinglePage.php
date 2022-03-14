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
		$single = ( new Query( [
			'path' => $path,
			'slug' => 'index'
		] ) )->single();

		// Look for a `path/{$name}.md` file if `path/index.md` not found.
		if ( ! $single ) {
			$single = ( new Query( [
				'path' => Str::beforeLast( $path, '/' ),
				'slug' => $name
			] ) )->single();
		}

		if ( $single ) {
			$collection = false;

			if ( $args = $single->metaArr( 'collection' ) ) {
				$collection = new Query( $args );
			}

			$doctitle = new DocumentTitle( $single->title() );

			return $this->response( $this->view( [
				"single-page-{$name}",
				'single-page',
				'single',
				'index'
			], [
				'doctitle'   => $doctitle,
				'pagination' => false,
				'single'     => $single,
				'collection' => $collection
			] ) );
		}

		// If all else fails, return a 404.
		return $this->forward404( $params );
	}
}
