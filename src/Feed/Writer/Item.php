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

namespace Blush\Feed\Writer;

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
	protected string $content = '';

	/**
	 * Feed item categories.
	 *
	 * @since 1.0.0
	 */
	protected array $categories = [];

	/**
	 * Feed item URL published date.
	 *
	 * @since 1.0.0
	 */
	protected ?int $published;

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
		$this->title       = $options['title']       ?? '';
		$this->url         = $options['url']         ?? '';
		$this->description = $options['description'] ?? '';
		$this->content     = $options['content']     ?? '';
		$this->author      = $options['author']      ?? null;
		$this->creator     = $options['creator']     ?? null;
		$this->published   = $options['published']   ?? null;
		$this->enclosure   = $options['enclosure']   ?? [];
		$this->categories  = $options['categories']  ?? [];
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
		return str_replace( ']]>', ']]]]><![CDATA[>', $this->description );
	}

	/**
	 * Returns the feed item content.
	 *
	 * @since 1.0.0
	 */
	public function content(): string
	{
		return str_replace( ']]>', ']]]]><![CDATA[>', $this->content );
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
	 * Returns the feed item published date.
	 *
	 * @since 1.0.0
	 */
	public function published(): ?string
	{
		return $this->published;
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

	// On the chopping block for 1.0.0.
	public function contentEncoded(): string { return $this->content(); }
	public function pubDate(): string { return $this->published(); }
	public function guid(): ?string { return $this->url(); }
	public function guidIsPermalink(): bool { return true; }
}
