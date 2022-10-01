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

use Blush\Tools\Collection;

abstract class Feed
{
	/**
	 * Feed title.
	 *
	 * @since 1.0.0
	 */
	protected string $title = '';

	/**
	 * Feed webpage URL.
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
	 * Feed URL published date.
	 *
	 * @since 1.0.0
	 */
	protected ?string $pub_date = null;

	/**
	 * Feed last build date.
	 *
	 * @since 1.0.0
	 */
	protected ?string $last_build_date = null;

	/**
	 * Feed Time To Live (TTL).
	 *
	 * @since 1.0.0
	 */
	protected ?int $ttl;

	/**
	 * Stores the feed items as a collection.
	 *
	 * @since 1.0.0
	 */
	protected ?Collection $items = null;

	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( array $options = [] )
	{
		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
			if ( isset( $options[ $key ] ) ) {
				$this->$key = $options[ $key ];
			}
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
	 * Returns the Feed published date.
	 *
	 * @since 1.0.0
	 */
	public function pubDate(): ?string
	{
		return $this->pub_date;
	}

	/**
	 * Returns the Feed last build date.
	 *
	 * @since 1.0.0
	 */
	public function lastBuildDate(): ?string
	{
		return $this->last_build_date;
	}

	/**
	 * Returns the Feed TTL.
	 *
	 * @since 1.0.0
	 */
	public function ttl(): ?int
	{
		return $this->ttl;
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
}
