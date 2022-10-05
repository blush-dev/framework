<?php
/**
 * Gets content entry from Markdown file.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content\Entry;

use Blush\{App, Cache, Config};
use Blush\Tools\Str;
use Symfony\Component\Yaml\Yaml;

class MarkdownFile extends Entry
{
	/**
	 * Whether Markdown has been parsed.
	 *
	 * @since 1.0.0
	 */
	protected bool $markdown_parsed = false;

	/**
	 * Whether YAML has been parsed.
	 *
	 * @since 1.0.0
	 */
	protected bool $yaml_parsed = false;

	/**
	 * Whether this is a "no content" request.
	 *
	 * @since 1.0.0
	 */
	protected bool $nocontent = false;

	/**
	 * Cache key build from the filename and relative path to content folder.
	 *
	 * @since 1.0.0
	 */
	protected string $cache_key = '';

	/**
	 * Stores the result of `filemtime()` on the Markdown file.
	 *
	 * @since 1.0.0
	 */
	protected ?int $markdown_time = null;

	/**
	 * Stores the result of `filemetime()` on the cache file.
	 *
	 * @since 1.0.0
	 */
	protected ?int $cache_time = null;

	/**
	 * Sets up the object state. Child classes need to overwrite this and
	 * pull content and metadata from the file path.
	 *
	 * @since 1.0.0
	 */
	public function __construct( protected string $filepath, array $options = [] )
	{
		$this->nocontent = $options['nocontent'] ?? false;

		parent::__construct( $filepath );

		// Build the cache key using the filepath relative to the
		// content folder.
		$this->cache_key = str_replace( '/' , '.', trim(
			Str::afterLast(
				Str::beforeLast( $this->filePath(), $this->extension() ),
				content_path()
			),
		'/.' ) );
	}

	/**
	 * Just-in-time Markdown parsing. This should not be called unless
	 * Markdown has yet to be parsed.
	 *
	 * @since 1.0.0
	 */
	protected function parseMarkdown(): void
	{
		// Get cached entries if any exist. If not, locate entries from
		// the filesystem.
		if ( Config::get( 'cache.markdown' ) && $md = $this->getCachedMarkdown() ) {
			$this->markdown_parsed = true;
			$this->yaml_parsed     = true;
			$this->content         = $md['content'];
			$this->meta            = $md['meta'];
			return;
		}

		$markdown = App::make( 'markdown' )->convert(
			file_get_contents( $this->filePath() )
		);

		$this->markdown_parsed = true;
		$this->yaml_parsed     = true;
		$this->content         = $markdown->content();
		$this->meta            = $markdown->frontMatter();

		// Cache the Markdown if caching is enabled.
		if ( Config::get( 'cache.markdown' ) ) {
			Cache::put( "markdown.{$this->cache_key}", [
				'content' => $this->content,
				'meta'    => $this->meta
			], Config::get( 'cache.expires' ) );
		}
	}

	/**
	 * Just-in-time YAML parsing. This should not be called unless
	 * Markdown or YAML has yet to be parsed.
	 *
	 * @since 1.0.0
	 */
	protected function parseYaml(): void
	{
		$content = file_get_contents(
			$this->filePath(), false, null, 0, 4 * 1024
		);

		$this->yaml_parsed = true;
		$this->meta = $content ? Str::frontMatter( $content ) : [];
	}

	/**
	 * Returns the entry content.
	 *
	 * @since 1.0.0
	 */
	public function content(): string
	{
		if ( ! $this->markdown_parsed ) {
			$this->parseMarkdown();
		}

		return parent::content();
	}

	/**
	 * Returns entry metadata.
	 *
	 * @since  1.0.0
	 */
	public function meta( string $name = '', mixed $default = false ): mixed
	{
		if ( $this->nocontent && ! $this->yaml_parsed ) {
			$this->parseYaml();
		} elseif ( ! $this->markdown_parsed ) {
			$this->parseMarkdown();
		}

		return parent::meta( $name, $default );
	}

	/**
	 * Returns the cached Markdown.
	 *
	 * @since 1.0.0
	 */
	private function getCachedMarkdown(): array
	{
		$store = Cache::store( 'markdown' );

		// Check cache for data. If none found, return empty array.
		if ( ! $cache = $store->get( $this->cache_key ) ) {
			return [];
		}

		// If we've already checked file modified times, return the cache.
		if ( ! is_null( $this->markdown_time ) && ! is_null( $this->cache_time ) ) {
			return $cache;
		}

		// If the persistent cache exists, let's see if it needs
		// refreshing based on file modified times. Note that the
		// `$store->created()` method should always be called after the
		// data is set/get from the cache store.
		$this->markdown_time = filemtime( $this->filePath() );
		$this->cache_time    = $store->created( $this->cache_key );

		// If there are no modified file times or the content folder is
		// newer than the cache, forget the current cache.
		if (
			false === $this->cache_time ||
			false === $this->markdown_time ||
			$this->markdown_time > $this->cache_time
		) {
			$store->forget( $this->cache_key );
			$cache = [];
		}

		// Return the cache, empty or otherwise.
		return $cache;
	}
}
