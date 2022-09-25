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
use Blush\Template\Feed\{Channel, Item};
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

			// Set up default channel variables.
			$channel_datetime = '';

			// Make a collection for feed items.
			$items = new Collection();

			// Loop through the collection and create new feed items.
			// These will get passed to the parent channel object.
			foreach ( $collection as $entry ) {

				$item_args = [
					'title'           => $entry->title(),
					'description'     => $entry->excerpt(),
					'content_encoded' => $entry->content(),
					'url'             => $entry->url()
				];

				$taxonomies = [];

				if ( $tax = $entry->type()->feedTaxonomy() ) {
					$taxonomies = [ $tax ];
				} elseif ( $tax = $entry->taxonomies() ) {
					$taxonomies = $tax;
				}

				if ( $taxonomies ) {
					$item_args['categories'] = [];

					foreach ( $taxonomies as $taxonomy ) {

						if ( $terms = $entry->terms( $taxonomy ) ) {

							foreach ( $terms as $term ) {
								$item_args['categories'][] = [
									'label' => e( $term->title() )
								];
							}
						}
					}
				}

				if ( $author = $entry->metaSingle( 'author' ) ) {
					$item_args['author'] = e( $author );
				}

				if ( $date = $entry->metaSingle( 'date' ) ) {
					$datetime = is_numeric( $date )
					            ? $date
					            : strtotime( $date );

					$item_args['pub_date'] = $datetime;

					// Grab the channel datetime from the
					// first post in the feed with a date.
					if ( '' === $channel_datetime ) {
						$channel_datetime = $datetime;
					}
				}

				$items->add( $entry->name(), new Item( $item_args ) );
			}

			$channel_args = [
				'title' => e( sprintf(
					'%s%s',
					Config::get( 'app.title' ),
					// If no path, we're viewing the homepage feed.
					$path ? ' - ' . $single->title() : ''
				) ),
				'description' => $path ? $single->excerpt() : config( 'app.tagline' ),
				'url'         => $type->url(),
				'feed_url'    => $type->feedUrl(),
				'language'    => 'en-US',
				'ttl'         => 60
			];

			// @todo Need a method for figuring out the last time
			// the content itself changed, regardless of the last
			// published date, for a proper `<lastBuildDate>`.
			if ( '' !== $channel_datetime ) {
				$channel_args['pub_date']        = $channel_datetime;
				$channel_args['last_build_date'] = $channel_datetime;
			}

			$view_data = [
				'doctitle'   => new DocumentTitle(),
				'pagination' => false,
				'single'     => $single,
				'collection' => $collection,
				'channel'    => new Channel( $channel_args ),
				'items'      => $items
			];

			// Get the feed view.
			return $this->response(
				$this->view( [
					'feed'
				], $view_data ),
				Response::HTTP_OK,
				[ 'content-type' => 'text/xml' ]
			);
		}

		// If all else fails, return a 404.
		return $this->forward404( $params, $request );
	}
}
