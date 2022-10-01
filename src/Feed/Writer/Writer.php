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

namespace Blush\Feed\Writer;

use Blush\Template\Feed\Types\Type;
use Blush\Tools\Collection;

class Writer
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
	protected string $webpage_url = '';

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

	protected ?string $published = null;
	protected ?string $updated = null;
	protected ?string $image = null;
	protected ?string $favicon = null;

	/**
	 * Feed Time To Live (TTL).
	 *
	 * @since 1.0.0
	 */
	protected ?int $ttl = 60;

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
	public function __construct( protected string $type, array $options = [] )
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
	public function webpageUrl(): string
	{
		return $this->webpage_url;
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
		return str_replace( ']]>', ']]&gt;', $this->description );
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
	 * Returns the Feed TTL.
	 *
	 * @since 1.0.0
	 */
	public function published(): ?string
	{
		return $this->published;
	}

	public function updated(): ?string
	{
		return $this->updated;
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
	public function items(): ?Collection
	{
		return $this->items;
	}

	// Slated for the 1.0.0 chopping block.
	public function url(): string { return $this->webpageUrl(); }
	public function pubDate(): ?string { return $this->published(); }
	public function lastBuildDate(): ?string { return $this->updated(); }
}
