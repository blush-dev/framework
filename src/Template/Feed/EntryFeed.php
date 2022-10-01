<?php
/**
 * Entry Feed Builder - Experimental
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template\Feed;

use Blush\Config;
use Blush\Contracts\Content\{Entry, Query};
use Blush\Tools\Collection;

class EntryFeed extends Feed
{
	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( protected Entry $single, protected Query $collection )
	{
		$is_home = $this->single->type()->isHomeAlias();

		$title = sprintf(
			'%s%s',
			Config::get( 'app.title' ),
			// If no path, we're viewing the homepage feed.
			$is_home ? '' : ' - ' . $this->single->title()
		);

		$desc = $is_home ? config( 'app.tagline' ) : $this->single->excerpt();

		$this->title       = $title;
		$this->description = $desc;
		$this->url         = $this->single->type()->url();
		$this->feed_url    = $this->single->type()->feedUrl();
		$this->language    = 'en-US';
		$this->ttl         = 60;

		// Make channel items.
		$this->makeItems();
	}

	/**
	 * Builds the feed items collection.
	 *
	 * @since 1.0.0
	 */
	protected function makeItems(): void
	{
		$this->items = new Collection();

		// Loop through the collection and create new feed items.
		// These will get passed to the parent channel object.
		foreach ( $this->collection as $entry ) {

			$args = [
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
				$args['categories'] = [];

				foreach ( $taxonomies as $taxonomy ) {
					if ( $terms = $entry->terms( $taxonomy ) ) {
						foreach ( $terms as $term ) {
							$args['categories'][] = [
								'label' => $term->title(),
								'term'  => $term->name()
							];
						}
					}
				}
			}

			if ( $author = $entry->metaSingle( 'author' ) ) {
				$args['author'] = $author;
			}

			if ( $date = $entry->metaSingle( 'date' ) ) {
				$datetime = is_numeric( $date )
				            ? $date
				            : strtotime( $date );

				$args['pub_date'] = $datetime;

				// Grab the feed datetime from the first post in
				// the feed with a date.
				if ( ! $this->pub_date || ! $this->last_build_date ) {
					$this->pub_date       = $datetime;
					$this->last_build_date = $datetime;
				}
			}

			$image = $image = $entry->metaSingle( 'image' );

			if ( $image && false === strpos( $image, 'http://' ) ) {
				$url  = url( $image );
				$path = path( $image );

				if ( file_exists( $path ) ) {
					$size = filesize( $path );
					$type = mime_content_type( $path );

					if ( $size && $type ) {
						$args['enclosure'] = [
							'url'    => $url,
							'length' => $size,
							'type'   => $type
						];
					}
				}
			}

			$this->items->add( $entry->name(), new Item( $args ) );
		}
	}
}
