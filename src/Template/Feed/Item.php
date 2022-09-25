<?php
/**
 * Feed Item --- Experimental
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template\Feed;

class Item
{
	/**
	 * Feed item title.
	 *
	 * @since 1.0.0
	 */
	protected string $title = '';

	/**
	 * Feed item URL.
	 *
	 * @since 1.0.0
	 */
	protected string $url = '';

	/**
	 * Feed item description.
	 *
	 * @since 1.0.0
	 */
	protected string $description = '';

	/**
	 * Feed item content (encoded).
	 *
	 * @since 1.0.0
	 */
	protected string $content_encoded = '';

	/**
	 * Feed item categories.
	 *
	 * @since 1.0.0
	 */
	protected array $categories = [];

	/**
	 * Feed item GUID.
	 *
	 * @since 1.0.0
	 */
	protected ?string $guid;

	/**
	 * Whether the GUID is the item permalink.
	 *
	 * @since 1.0.0
	 */
	protected bool $guid_is_permalink = false;

	/**
	 * Feed item URL published date.
	 *
	 * @since 1.0.0
	 */
	protected ?int $pub_date;

	/**
	 * Feed item enclosure.
	 *
	 * @since 1.0.0
	 */
	protected array $enclosure = [];

	/**
	 * Feed item author.
	 *
	 * @since 1.0.0
	 */
	protected ?string $author;

	/**
	 * Feed item creator.
	 *
	 * @since 1.0.0
	 */
	protected ?string $creator;

	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( array $options = [] )
	{
		// Set up the object properties based on parameters.
		$this->title             = $options['title']             ?? '';
		$this->url               = $options['url']               ?? '';
		$this->description       = $options['description']       ?? '';
		$this->content_encoded   = $options['content_encoded']   ?? '';
		$this->author            = $options['author']            ?? null;
		$this->creator           = $options['creator']           ?? null;
		$this->pub_date          = $options['pub_date']          ?? null;
		$this->guid              = $options['guid']              ?? null;
		$this->guid_is_permalink = $options['guid_is_permalink'] ?? false;
		$this->enclosure         = $options['enclosure']         ?? [];
		$this->categories        = $options['categories']        ?? [];
	}

	/**
	 * Returns the feed item title.
	 *
	 * @since 1.0.0
	 */
	public function title(): string
	{
		return $this->title;
	}
	/**
	 * Returns the feed item URL.
	 *
	 * @since 1.0.0
	 */
	public function url(): string
	{
		return $this->url;
	}

	/**
	 * Returns the feed item description.
	 *
	 * @since 1.0.0
	 */
	public function description(): string
	{
		return $this->description;
	}

	/**
	 * Returns the feed item content encoded.
	 *
	 * @since 1.0.0
	 */
	public function contentEncoded(): string
	{
		return $this->content_encoded;
	}

	/**
	 * Returns the feed item categories.
	 *
	 * @since 1.0.0
	 */
	public function categories(): array
	{
		return $this->categories;
	}

	/**
	 * Returns the feed item GUID.
	 *
	 * @since 1.0.0
	 */
	public function guid(): ?string
	{
		return $this->guid;
	}

	/**
	 * Conditional for whether the GUID is the feed item permalink.
	 *
	 * @since 1.0.0
	 */
	public function guidIsPermalink(): bool
	{
		return $this->guid_is_permalink;
	}

	/**
	 * Returns the feed item published date.
	 *
	 * @since 1.0.0
	 */
	public function pubDate(): ?string
	{
		return $this->pub_date;
	}

	/**
	 * Returns the feed item enclosure.
	 *
	 * @since 1.0.0
	 */
	public function enclosure(): array
	{
		return $this->enclosure;
	}

	/**
	 * Returns the feed item author.
	 *
	 * @since 1.0.0
	 */
	public function author(): ?string
	{
		return $this->author;
	}

	/**
	 * Returns the feed item creator.
	 *
	 * @since 1.0.0
	 */
	public function creator(): ?string
	{
		return $this->creator;
	}
}
