<?php
/**
 * Feed Writer interface.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Feed;

use Blush\Contracts\Content\ContentQuery;

interface FeedWriter
{
	/**
	 * Returns the Feed title.
	 *
	 * @since 1.0.0
	 */
	public function title(): string;

	/**
	 * Returns the Feed webpage URL.
	 *
	 * @since 1.0.0
	 */
	public function url(): string;

	/**
	 * Returns the Feed feed URL.
	 *
	 * @since 1.0.0
	 */
	public function feedUrl(): string;

	/**
	 * Returns the Feed description.
	 *
	 * @since 1.0.0
	 */
	public function description(): string;

	/**
	 * Returns the Feed language.
	 *
	 * @since 1.0.0
	 */
	public function language(): string;

	/**
	 * Returns the Feed TTL.
	 *
	 * @since 1.0.0
	 */
	public function copyright(): ?string;

	/**
	 * Returns the feed published datetime.
	 *
	 * @since 1.0.0
	 */
	public function published(): ?string;

	/**
	 * Returns the feed updated datetime.
	 *
	 * @since 1.0.0
	 */
	public function updated(): ?string;

	/**
	 * Returns the Feed TTL.
	 *
	 * @since 1.0.0
	 */
	public function ttl(): int;

	/**
	 * Returns the collection.
	 *
	 * @since 1.0.0
	 */
	public function collection(): ContentQuery;
}
