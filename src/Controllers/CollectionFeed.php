<?php
/**
 * Content type feed controller.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Controllers;

use Blush\{App, Config, Query};
use Blush\Feed\Writer\ContentFeed;
use Blush\Template\Tags\{DocumentTitle, Pagination};
use Blush\Tools\{Collection, Str};
use Symfony\Component\HttpFoundation\{Request, Response};

class CollectionFeed extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since  1.0.0
	 */
	public function __invoke( array $params, Request $request ): Response
	{
		// Get all content types.
		$types = App::get( 'content.types' );
		$type  = false;

		// Get needed URI params from the router.
		$path = Str::trimSlashes( Str::beforeLast( $params['path'], 'feed' ) );

		// If there is no path, we're looking at the homepage feed.
		// Get the alias type if there is one.
		if ( ! $path && $alias = Config::get( 'app.home_alias' ) ) {
			$type = $types->has( $alias ) ? $types->get( $alias ) : false;
		}

		// Get the content type from the path or URI.
		if ( ! $type && $path ) {
			$type = $types->getTypeFromPath( $path ) ?: $types->getTypeFromUri( $path );
		}

		// Bail if there is no type.
		if ( ! $type ) {
			return $this->forward404( $params, $request );
		}

		// Query the content type's index file.
		$single = Query::make( [
			'path' => $type->path(),
			'slug' => 'index'
		] )->single();

		// Query the content type collection.
		$collection = Query::make( $type->feedArgs() );

		if ( $single && $collection->hasEntries() ) {

			$type_name  = sanitize_slug( $type->type() );
			$model_name = $type->isTaxonomy() ? 'taxonomy' : 'content';

			// Get the feed view.
			return $this->response( $this->view(
				[
					"feed-{$type_name}",
					"feed-{$model_name}",
					'feed-rss',
					'feed'
				], [
					'doctitle'   => new DocumentTitle(),
					'pagination' => false,
					'single'     => $single,
					'collection' => $collection,
					'feed'       => new ContentFeed( $single, $collection )
				]
			), Response::HTTP_OK, [ 'content-type' => 'text/xml' ] );
		}

		// If all else fails, return a 404.
		return $this->forward404( $params, $request );
	}
}
