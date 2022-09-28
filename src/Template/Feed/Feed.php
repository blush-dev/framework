<?php
/**
 * Feed Builder - Experimental
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template\Feed;

use Blush\Config;
use Blush\Contracts\Makeable;
use Blush\Contracts\Content\{Entry, Query};
use Blush\Tools\Collection;

class Feed implements Makeable
{
	/**
	 * Stores the feed channel.
	 *
	 * @since 1.0.0
	 */
	protected ?Channel $channel = null;

	/**
	 * Stores the feed items as a collection.
	 *
	 * @since 1.0.0
	 */
	protected ?Collection $items = null;

	/**
	 * Stores the channel datetime, which we grab from the most recent
	 * channel item.
	 *
	 * @since 1.0.0
	 */
	protected ?string $channel_datetime = null;

	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( protected Entry $single, protected Query $collection ) {}

	/**
	 * Makes the feed items and channel and returns an instance of the
	 * feed object for method chaining.
	 *
	 * @since 1.0.0
	 */
	public function make(): self
	{
		$this->makeItems();
		$this->makeChannel();

		return $this;
	}

	/**
	 * Returns the feed channel.
	 *
	 * @since 1.0.0
	 */
	public function channel(): Channel
	{
		return $this->channel;
	}

	/**
	 * Returns the items collection.
	 *
	 * @since 1.0.0
	 */
	public function items(): Collection
	{
		return $this->items;
	}

	/**
	 * Builds the feed channel.
	 *
	 * @since 1.0.0
	 */
	protected function makeChannel(): void
	{
		$is_home = $this->single->type()->isHomeAlias();

		$title = sprintf(
			'%s%s',
			Config::get( 'app.title' ),
			// If no path, we're viewing the homepage feed.
			$is_home ? '' : ' - ' . $this->single->title()
		);

		$desc = $is_home ? config( 'app.tagline' ) : $this->single->excerpt();

		$args = [
			'title'       => $title,
			'description' => $desc,
			'url'         => $this->single->type()->url(),
			'feed_url'    => $this->single->type()->feedUrl(),
			'language'    => 'en-US',
			'ttl'         => 60
		];

		// @todo Need a method for figuring out the last time
		// the content itself changed, regardless of the last
		// published date, for a proper `<lastBuildDate>`.
		if ( $this->channel_datetime ) {
			$args['pub_date']        = $this->channel_datetime;
			$args['last_build_date'] = $this->channel_datetime;
		}

		$this->channel = new Channel( $args );
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
								'label' => $term->title()
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

				// Grab the channel datetime from the
				// first post in the feed with a date.
				if ( ! $this->channel_datetime ) {
					$this->channel_datetime = $datetime;
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
