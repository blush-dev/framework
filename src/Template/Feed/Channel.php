<?php
/**
 * Feed Channel --- Experimental
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template\Feed;

use Blush\Tools\Collection;

class Channel
{
	/**
	 * Feed channel title.
	 *
	 * @since 1.0.0
	 */
	protected string $title = '';

	/**
	 * Feed channel webpage URL.
	 *
	 * @since 1.0.0
	 */
	protected string $url = '';

	/**
	 * Feed channel feed URL.
	 *
	 * @since 1.0.0
	 */
	protected string $feed_url = '';

	/**
	 * Feed channel description.
	 *
	 * @since 1.0.0
	 */
	protected string $description = '';

	/**
	 * Feed channel language.
	 *
	 * @since 1.0.0
	 * @todo  Map this to config.
	 */
	protected string $language = 'en-US';

	/**
	 * Feed channel copyright.
	 *
	 * @since 1.0.0
	 */
	protected ?string $copyright;

	/**
	 * Feed channel URL published date.
	 *
	 * @since 1.0.0
	 */
	protected ?int $pub_date;

	/**
	 * Feed channel last build date.
	 *
	 * @since 1.0.0
	 */
	protected ?string $last_build_date;

	/**
	 * Feed channel Time To Live (TTL).
	 *
	 * @since 1.0.0
	 */
	protected ?int $ttl;

	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( array $options = [] )
	{
		// Set up the object properties based on parameters.
		$this->title           = $options['title']           ?? '';
		$this->url             = $options['url']             ?? '';
		$this->feed_url        = $options['feed_url']        ?? '';
		$this->description     = $options['description']     ?? '';
		$this->language        = $options['language']        ?? 'en-US';
		$this->copyright       = $options['copyright']       ?? null;
		$this->pub_date        = $options['pub_date']        ?? null;
		$this->last_build_date = $options['last_build_date'] ?? null;
		$this->ttl             = $options['ttl']             ?? null;
	}

	/**
	 * Returns the feed channel title.
	 *
	 * @since 1.0.0
	 */
	public function title(): string
	{
		return $this->title;
	}

	/**
	 * Returns the feed channel webpage URL.
	 *
	 * @since 1.0.0
	 */
	public function url(): string
	{
		return $this->url;
	}

	/**
	 * Returns the feed channel feed URL.
	 *
	 * @since 1.0.0
	 */
	public function feedUrl(): string
	{
		return $this->feed_url;
	}

	/**
	 * Returns the feed channel description.
	 *
	 * @since 1.0.0
	 */
	public function description(): string
	{
		return $this->description;
	}

	/**
	 * Returns the feed channel language.
	 *
	 * @since 1.0.0
	 */
	public function language(): string
	{
		return $this->language;
	}

	/**
	 * Returns the feed channel TTL.
	 *
	 * @since 1.0.0
	 */
	public function copyright(): ?string
	{
		return $this->copyright;
	}

	/**
	 * Returns the feed channel published date.
	 *
	 * @since 1.0.0
	 */
	public function pubDate(): ?string
	{
		return $this->pub_date;
	}

	/**
	 * Returns the feed channel last build date.
	 *
	 * @since 1.0.0
	 */
	public function lastBuildDate(): ?string
	{
		return $this->last_build_date;
	}

	/**
	 * Returns the feed channel TTL.
	 *
	 * @since 1.0.0
	 */
	public function ttl(): ?int
	{
		return $this->ttl;
	}
}
