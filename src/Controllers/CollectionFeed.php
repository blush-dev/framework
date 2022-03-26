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
use Blush\Tools\Str;
use Suin\RSSWriter\{Feed, Channel, Item};
use Symfony\Component\HttpFoundation\{Request, Response};

class CollectionFeed extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since 1.0.0
	 */
	public function __invoke( array $params = [], Request $request ): Response
	{
		// Get all content types.
		$types = App::get( 'content.types' );
		$type  = false;

		// Get needed URI params from the router.
		$path = trim( Str::beforeLast( $params['path'], 'feed' ), '/' );

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
			$feed    = new Feed();
			$channel = new Channel();

			$channel->title( e( sprintf(
				'%s - %s',
				Config::get( 'app.title' ),
				$single->title()
			) ) );

			$channel->url( $type->url() );
			$channel->feedUrl( $type->feedUrl() );
			$channel->language( 'en-US' );
			$channel->ttl( 60 );
			$channel->appendTo( $feed );

			foreach ( $collection as $entry ) {
				$item = new Item();

				$item->title( e( $entry->title() ) );
				$item->description( $entry->excerpt() );
				$item->contentEncoded( $entry->content() );
				$item->url( $entry->url() );
				$item->preferCdata( true );
				$item->appendTo( $channel );

				if ( $author = $entry->metaSingle( 'author' ) ) {
					$item->author( e( $author ) );
				}

				if ( $date = $entry->metaSingle( 'date' ) ) {
					$datetime = is_numeric( $date )
					            ? $date
					            : strtotime( $date );

					$item->pubDate( $datetime );
				}
			}

			if ( ! empty( $datetime ) ) {
				$channel->pubDate( $datetime );
				$channel->lastBuildDate( $datetime );
			}

			return new Response(
				$feed,
				Response::HTTP_OK,
				[ 'content-type' => 'application/rss+xml' ]
			);
		}

		// If all else fails, return a 404.
		return $this->forward404( $params, $request );
	}
}
