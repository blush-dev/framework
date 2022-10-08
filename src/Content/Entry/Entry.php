<?php
/**
 * Base content entry class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content\Entry;

// Abstracts.
use Blush\Contracts\Content\Entry as EntryContract;
use Blush\Contracts\Content\Query as QueryContract;
use Blush\Contracts\Content\Type;

// Concretes.
use Blush\{App, Config, Query, Url};
use Blush\Tools\{Media, Str};

abstract class Entry implements EntryContract
{
	/**
	 * Entry content type.
	 *
	 * @since 1.0.0
	 */
	protected ?Type $type = null;

	/**
	 * Entry content.
	 *
	 * @since 1.0.0
	 */
	protected string $content = '';

	/**
	 * Stores the entry metadata (front matter).
	 *
	 * @since 1.0.0
	 */
	protected array $meta = [];

	/**
	 * Resolved metadata, which represent content type relationships, such
	 * as taxonomy terms.
	 *
	 * @since 1.0.0
	 */
	protected array $resolved_meta = [];

	/**
	 * Stores the taxonomies associated with the entry.
	 *
	 * @since 1.0.0
	 */
	protected array $taxonomies = [];

	/**
	 * Entry path info.
	 *
	 * @since 1.0.0
	 */
	protected array $pathinfo = [];

	/**
	 * Returns the entry type.
	 *
	 * @since 1.0.0
	 */
	abstract public function type(): Type;

	/**
	 * Returns the entry name (slug).
	 *
	 * @since 1.0.0
	 */
	abstract public function name(): string;

	/**
	 * Returns a post's visibility. Currently, the API allows for
	 * `public` and `hidden`.
	 *
	 * @since 1.0.0
	 */
	public function visibility(): string
	{
		if ( $visibility = $this->metaSingle( 'visibility' ) ) {
			return in_array( $visibility, [
				'public',
				'hidden'
			], true ) ? $visibility : 'public';
		}

		return 'public';
	}

	/**
	 * Checks if an entry is viewable to the public.
	 *
	 * @since 1.0.0
	 */
	public function isPublic(): bool
	{
		return 'public' === $this->visibility();
	}

	/**
	 * Checks if an entry is hidden from the public.
	 *
	 * @since 1.0.0
	 */
	public function isHidden(): bool
	{
		return 'hidden' === $this->visibility();
	}

	/**
	 * Returns the entry URL.
	 *
	 * @since  1.0.0
	 */
	abstract public function url(): string;

	/**
	 * Returns the entry URL.
	 *
	 * @deprecated 1.0.0  Soft deprecated.
	 * @since      1.0.0
	 */
	public function uri(): string
	{
		return $this->url();
	}

	/**
	 * Returns the entry content.
	 *
	 * @since 1.0.0
	 */
	public function content(): string
	{
		return $this->content;
	}

	/**
	 * Returns entry metadata.
	 *
	 * @since  1.0.0
	 */
	public function meta( string $name = '', mixed $default = false ): mixed
	{
		if ( 'date' === $name ) {
			return $this->meta[ $name ] ?? $this->meta['published'] ?? $default;
		} elseif ( $name ) {
			return $this->meta[ $name ] ?? $default;
		}

		return $this->meta;
	}

	/**
	 * Returns only a single meta value. Shifts and returns the first value
	 * if the metadata is an array.
	 *
	 * @since  1.0.0
	 */
	public function metaSingle( string $name, mixed $default = false ): mixed
	{
		$meta = $this->meta( $name, $default );
		return $meta && is_array( $meta ) ? array_shift( $meta ) : $meta;
	}

	/**
	 * Ensures that an array of meta values is returned.
	 *
	 * @since  1.0.0
	 */
	public function metaArr( string $name, array $default = [] ): array
	{
		if ( ! $meta = $this->meta( $name ) ) {
			return $default;
		}

		return is_array( $meta ) ? $meta : (array) $meta;
	}

	/**
	 * Returns a Query for content type entries stored in the current
	 * entry's metadata.
	 *
	 * @since  1.0.0
	 */
	public function metaQuery( string $name, array $args = [] ): QueryContract|false
	{
		if ( isset( $this->resolved_meta[ $name ] ) ) {
			return $this->resolved_meta[ $name ];
		}

		// Set the meta as resolved.
		$this->resolved_meta[ $name ] = false;

		// Set type and slugs args.
		$args['type'] ??= $name;
		$args['names'] = [];

		foreach ( $this->metaArr( $name ) as $value ) {
			$args['names'][] = sanitize_slug( $value );
		}

		if ( $args['names'] ) {
			$this->resolved_meta[ $name ] = Query::make( $args );
		}

		// Return the resolved meta.
		return $this->resolved_meta[ $name ];
	}

	/**
	 * Returns the entry title.
	 *
	 * @since 1.0.0
	 */
	public function title(): string
	{
		return (string) $this->metaSingle( 'title' );
	}

	/**
	 * Returns the entry subtitle.
	 *
	 * @since 1.0.0
	 */
	public function subtitle(): string
	{
		return (string) $this->metaSingle( 'subtitle' );
	}

	/**
	 * Returns the entry published datetime.
	 *
	 * @since 1.0.0
	 */
	public function published( string $format = '' ): string
	{
		return $this->date( 'published', $format );
	}

	/**
	 * Returns the entry updated datetime.
	 *
	 * @since 1.0.0
	 */
	public function updated( string $format = '' ): string
	{
		// Returned updated meta if found.
		if ( $updated = $this->date( 'updated', $format ) ) {
			return $updated;
		}

		// Fall back to published meta if it exists.
		return $this->published( $format );
	}

	/**
	 * Returns a formatted entry date by meta key name.
	 *
	 * @since 1.0.0
	 */
	public function date( string $name = 'published', string $format = '' ): string
	{
		$format = $format ?: Config::get( 'app.date_format' );

		// Get the date by meta key name.
		$date = $this->metaSingle( $name );

		// Back-compat check for older `date` meta key.
		if ( ! $date && 'published' === $name ) {
			$date = $this->metaSingle( 'date' );
		}

		return $date
		       ? date( $format, is_numeric( $date ) ? $date : strtotime( $date ) )
		       : '';
	}

	/**
	 * Returns the entry author.
	 *
	 * @since  1.0.0
	 */
	public function author(): EntryContract|false
	{
		if ( $meta = $this->metaSingle( 'author' ) ) {
			return new Virtual( [
				'meta' => [ 'title' => (string) $meta ]
			] );
		}

		return false;
	}

	/**
	 * Returns the entry authors.
	 *
	 * @since  1.0.0
	 */
	public function authors(): array
	{
		return (string) $this->metaArr( 'author' );
	}

	/**
	 * Returns a media object based on a media file path stored as metadata.
	 * Note: pass in the meta key for the `$name` and not the media type.
	 *
	 * @since  1.0.0
	 */
	public function media( string $name = 'image' ): Media|null
	{
		$meta  = $this->metaSingle( $name );
		$media = $meta ? new Media( $meta ) : false;

		return $media && $media->valid() ? $media : null;
	}

	/**
	 * Returns an array of view paths assigned as metadata.
	 *
	 * @since  1.0.0
	 */
	public function viewPaths(): array
	{
		return array_map(
			fn( $view ) => Str::beforeLast( $view, '.php' ),
			array_merge(
				$this->metaArr( 'template' ),
				$this->metaArr( 'view' )
			)
		);
	}

	/**
	 * Returns an array of Query arguments if assigned as metadata.
	 *
	 * @since  1.0.0
	 */
	public function collectionArgs(): array
	{
		$collection = $this->metaArr( 'collection' );

		// Make sure this is an associative array.
		$keys = array_keys( $collection );
		$is_associative = array_keys( $keys ) !== $keys;

		return $is_associative ? $collection : [];
	}

	/**
	 * Returns an array of the taxonomy (content type) objects associated
	 * with the entry.
	 *
	 * @since  1.0.0
	 */
	public function taxonomies(): array
	{
		if ( $this->taxonomies ) {
			return $this->taxonomies;
		}

		foreach ( App::get( 'content.types' ) as $type ) {
			if ( $this->metaSingle( $type->name() ) && $type->isTaxonomy() ) {
				$this->taxonomies[ $type->name() ] = $type;
			}
		}

		return $this->taxonomies;
	}

	/**
	 * Conditional check if the entry is associated with a taxonomy.
	 *
	 * @since  1.0.0
	 */
	public function hasTaxonomy( string $taxonomy ): bool
	{
		$taxonomies = $this->taxonomies();
		return isset( $taxonomies[ $taxonomy ] );
	}

	/**
	 * Returns a Query of taxonomy entries or false.
	 *
	 * @since  1.0.0
	 */
	public function terms( string $taxonomy, array $args = [] ): QueryContract|false
	{
		return $this->hasTaxonomy( $taxonomy )
		       ? $this->metaQuery( $taxonomy, $args )
		       : false;
	}

	/**
	 * Conditional check if the entry has a term from a specific taxonomy.
	 *
	 * @since  1.0.0
	 */
	public function hasTerm( string $taxonomy, string $term ): bool
	{
		$terms = $this->terms( $taxonomy );
		return $terms ? $terms->has( $term ) : false;
	}

	/**
	 * Returns the entry excerpt.
	 *
	 * @since  1.0.0
	 */
	public function excerpt( int $limit = 50, string $more = '&#8230;' ): string
	{
		if ( $content = $this->metaSingle( 'excerpt' ) ) {
			return App::resolve( 'markdown' )->convert(
				$content
			)->content();
		}

		// Remove `<figcaption>` so that its text isn't in the excerpt.
		$content = preg_replace(
			"/<figcaption.*?>(.*?)<\/figcaption>/i",
			"",
			$content ?: $this->content()
		);

		return sprintf( '<p>%s</p>', Str::words(
			strip_tags( $content ),
			$limit,
			$more
		) );
	}

	/**
	 * Returns an estimated reading time in hours (if an hour or longer) and
	 * minutes.
	 *
	 * @since  1.0.0
	 */
	public function readingTime( int $words_per_min = 200 ): string
	{
		return Str::readingTime( $this->content(), $words_per_min );
	}

	/**
	 * Returns the entry name when it's used as a string.
	 *
	 * @since 1.0.0
	 */
	public function __toString(): string
	{
		return $this->name();
	}
}
