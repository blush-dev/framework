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

use Blush\Core\Proxies\{App, Query};
use Blush\Template\Hierarchy;
use Blush\Template\Tag\DocumentTitle;
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\{Request, Response};

class SinglePage extends Single
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since 1.0.0
	 */
	public function __invoke( array $params, Request $request ): Response
	{
		$path = $params['path'] ?? '';
		$name = Str::afterLast( $path, '/' );

		// If the page name begins with `_`, it is private.
		if ( Str::startsWith( $name, '_' ) ) {
			return $this->forward404( $params, $request );
		}

		// Look for an `path/index.md` file.
		$single = Query::make( [
			'path' => $path,
			'slug' => 'index'
		] )->single();

		// Look for a `path/{$name}.md` file if `path/index.md` not found.
		if ( ! $single ) {
			$single = Query::make( [
				'path' => Str::beforeLast( $path, '/' ),
				'slug' => $name
			] )->single();
		}

		if ( $single && $single->isPublic() ) {
			$collection = false;

			if ( $args = $single->collectionArgs() ) {
				$collection = Query::make( $args );
			}

			$doctitle = new DocumentTitle( $single->title() );

			return $this->response( $this->view(
				Hierarchy::single( $single ),
				[
					'doctitle'   => $doctitle,
					'pagination' => false,
					'single'     => $single,
					'collection' => $collection
				]
			) );
		}

		// If all else fails, return a 404.
		return $this->forward404( $params, $request );
	}
}
