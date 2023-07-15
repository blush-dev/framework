<?php
/**
 * Feed Writer.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Feed;

use Blush\Core\Proxies\Config;
use Blush\Contracts\Content\{ContentEntry, ContentQuery};
use Blush\Contracts\Feed\FeedWriter;
use Blush\Tools\Collection;

class Writer implements FeedWriter
{
	/**
	 * Feed title.
	 *
	 * @since 1.0.0
	 */
	protected string $title = '';

	/**
	 * Webpage URL.
	 *
	 * @since 1.0.0
	 */
	protected string $url = '';

	/**
	 * Feed feed URL.
	 *
	 * @since 1.0.0
	 */
	protected string $feed_url = '';

	/**
	 * Feed description.
	 *
	 * @since 1.0.0
	 */
	protected string $description = '';

	/**
	 * Feed language.
	 *
	 * @since 1.0.0
	 * @todo  Map this to config.
	 */
	protected string $language = 'en-US';

	/**
	 * Feed copyright.
	 *
	 * @since 1.0.0
	 */
	protected ?string $copyright = null;

	/**
	 * Feed published datetime.
	 *
	 * @since 1.0.0
	 */
	protected ?string $published = null;

	/**
	 * Feed updated datetime.
	 *
	 * @since 1.0.0
	 */
	protected ?string $updated = null;

	/**
	 * Feed image.
	 *
	 * @todo  Not yet implemented.
	 * @since 1.0.0
	 */
	protected ?string $image = null;

	/**
	 * Feed favicon.
	 *
	 * @todo  Not yet implemented.
	 * @since 1.0.0
	 */
	protected ?string $favicon = null;

	/**
	 * Feed Time To Live (TTL).
	 *
	 * @since 1.0.0
	 */
	protected int $ttl = 60;

	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		protected ContentEntry $single,
		protected ContentQuery $collection,
		protected string $type = 'rss2'
	)
	{
		$is_home = $this->single->type()->isHomeAlias();

		$this->title       = $is_home ? Config::get( 'app.title' ) : $single->title();
		$this->description = $is_home ? Config::get( 'app.tagline' ) : $single->excerpt();
		$this->url         = $single->type()->url();
		$this->feed_url    = $single->type()->feedUrl();
		$this->language    = 'en-US';
		$this->ttl         = 60;

		// Get the first entry in the collection to set the published
		// and updated datetimes for the channel.
		if ( $first = $collection->first() ) {
			$format = 'atom' === $this->type ? DATE_ATOM : DATE_RSS;

			$this->published = $first->published( $format ) ?: null;
			$this->updated   = $first->updated( $format )   ?: null;
		}
	}

	/**
	 * Returns the Feed title.
	 *
	 * @since 1.0.0
	 */
	public function title(): string
	{
		return $this->title;
	}

	/**
	 * Returns the Feed webpage URL.
	 *
	 * @since 1.0.0
	 */
	public function url(): string
	{
		return $this->url;
	}

	/**
	 * Returns the Feed feed URL.
	 *
	 * @since 1.0.0
	 */
	public function feedUrl(): string
	{
		return $this->feed_url;
	}

	/**
	 * Returns the Feed description.
	 *
	 * @since 1.0.0
	 */
	public function description(): string
	{
		return $this->description;
	}

	/**
	 * Returns the Feed language.
	 *
	 * @since 1.0.0
	 */
	public function language(): string
	{
		return $this->language;
	}

	/**
	 * Returns the Feed TTL.
	 *
	 * @since 1.0.0
	 */
	public function copyright(): ?string
	{
		return $this->copyright;
	}

	/**
	 * Returns the feed published datetime.
	 *
	 * @since 1.0.0
	 */
	public function published(): ?string
	{
		return $this->published;
	}

	/**
	 * Returns the feed updated datetime.
	 *
	 * @since 1.0.0
	 */
	public function updated(): ?string
	{
		return $this->updated;
	}

	/**
	 * Returns the Feed TTL.
	 *
	 * @since 1.0.0
	 */
	public function ttl(): int
	{
		return $this->ttl;
	}

	/**
	 * Returns the collection.
	 *
	 * @since 1.0.0
	 */
	public function collection(): ContentQuery
	{
		return $this->collection;
	}
}
