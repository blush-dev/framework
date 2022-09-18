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
use Blush\Tools\Str;

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
	 * Entry path info.
	 *
	 * @since 1.0.0
	 */
	protected array $pathinfo = [];

	/**
	 * Sets up the object state. Child classes need to overwrite this and
	 * pull content and metadata from the file path.
	 *
	 * @since 1.0.0
	 */
	public function __construct( protected string $filepath )
	{
		$this->pathinfo = pathinfo( $filepath );
	}

	/**
	 * Returns the entry type.
	 *
	 * @since 1.0.0
	 */
	public function type(): Type
	{
		// Return type if it's already set.
		if ( $this->type ) {
			return $this->type;
		}

		$types    = App::get( 'content.types' );
		$has_type = false;

		// Strip the file basename and content path from the file path.
		// This should give us the content type path, which we can match
		// against registered content types.
		$path = Str::beforeLast( $this->filePath(), basename( $this->filePath() ) );
		$path = Str::afterLast( $path, App::get( 'path.content' ) );
		$path = Str::trimSlashes( $path );

		// Get the content type by path.
		if ( $path ) {
			$has_type = $types->getTypeFromPath( $path );
		}

		// Set type or fall back to the `page` type.
		$this->type = $has_type ?: $types->get( 'page' );

		return $this->type;
	}

	/**
	 * Returns the entry name (slug).
	 *
	 * @since 1.0.0
	 */
	public function name(): string
	{
		// Get the filename without the extension.
		$name = $this->filename();

		// Strip anything after potential ordering dot, e.g.,
		// `01.{$name}.md`, `02.{$name}.md`, etc.
		if ( Str::contains( $name, '.' ) ) {
			$name =  Str::afterLast( $name, '.' );
		}

		return $name;
	}

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

		return Str::startsWith( $this->filename(), '_' ) ? 'hidden' : 'public';
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
	public function url(): string
	{
		// If dealing with a page, build the URL from the filepath.
		if ( 'page' === $this->type()->name() ) {

			// Strip the content path from the directory name.
			$url_path = Str::afterLast( $this->dirname(), content_path() );

			// If this is not the `index` file, append the filename
			// to the URL path.
			if ( 'index' !== $this->filename() ) {
				$url_path = Str::appendPath(
					$url_path,
					$this->filename()
				);
			}

			return Url::to( $url_path );
		}

		// Adds the required name param.
		$params = [ 'name' => $this->name() ];

		// Adds date-based params if we have a date.
		if ( $date = $this->metaSingle( 'date' ) ) {
			$timestamp = is_numeric( $date ) ? $date : strtotime( $date );
			$date      = date( 'Y-m-d', $timestamp );
			$time      = date( 'H:i:s', $timestamp );

			$params['year']   = Str::beforeFirst( $date, '-' );
			$params['month']  = Str::between( $date, '-', '-' );
			$params['day']    = Str::afterLast( $date, '-' );
			$params['hour']   = Str::beforeFirst( $time, ':' );
			$params['minute'] = Str::between( $time, ':', ':' );
			$params['second'] = Str::afterLast( $time, ':' );
		}

		// Add author param if author exists.
		if ( $author = $this->metaSingle( 'author' ) ) {
			$params['author'] = sanitize_slug( $author );
		}

		// Add content type/taxonomy params if they exist.
		foreach ( App::get( 'content.types' ) as $type ) {
			if ( $slug = $this->metaSingle( $type->name() ) ) {
				$params[ $type->name() ] = $slug;
			}
		}

		// Build the URL based on the route.
		return $this->type()->singleUrl( $params );
	}

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
	 * Returns the entry file path.
	 *
	 * @since 1.0.0
	 */
	public function filePath(): string
	{
		return $this->filepath;
	}

	/**
	 * Returns the file's pathinfo or a specific value.
	 *
	 * @since  1.0.0
	 */
	public function pathinfo( string $key = '' ): array|string
	{
		if ( $key ) {
			return $this->pathinfo[ $key ] ?? '';
		}

		return $this->pathinfo;
	}

	/**
	 * Returns the file's directory name.
	 *
	 * @since 1.0.0
	 */
	public function dirname(): string
	{
		return $this->pathinfo( 'dirname' );
	}

	/**
	 * Returns the file's basename (includes extension).
	 *
	 * @since 1.0.0
	 */
	public function basename(): string
	{
		return $this->pathinfo( 'basename' );
	}

	/**
	 * Returns the file's extension.
	 *
	 * @since 1.0.0
	 */
	public function extension(): string
	{
		return $this->pathinfo( 'extension' );
	}

	/**
	 * Returns the filename without extension.
	 *
	 * @since 1.0.0
	 */
	public function filename(): string
	{
		return $this->pathinfo( 'filename' );
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
		if ( $name ) {
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
	 * Returns the entry date.
	 *
	 * @since 1.0.0
	 */
	public function date(): string
	{
		if ( ! $date = $this->metaSingle( 'date' ) ) {
			return '';
		}

		return date(
			Config::get( 'app.date_format' ) ?: 'F j, Y',
		 	is_numeric( $date ) ? $date : strtotime( $date )
		);
	}

	/**
	 * Returns the entry author.
	 *
	 * @since  1.0.0
	 */
	public function author(): string
	{
		return (string) $this->metaSingle( 'author' );
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
	 * Returns a Query of taxonomy entries or false.
	 *
	 * @since  1.0.0
	 */
	public function terms( string $taxonomy, array $args = [] ): QueryContract|false
	{
		$types = App::resolve( 'content.types' );

		if ( $types->has( $taxonomy ) && $types->get( $taxonomy )->isTaxonomy() ) {
			return $this->metaQuery(
				$types->get( $taxonomy )->type(),
				$args
			);
		}

		return false;
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
	public function excerpt( int $limit = 50, string $more = '&hellip;' ): string
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
		// Strip tags and get the word count from the content.
		$count = str_word_count( strip_tags( $this->content() ) );

		// Get the ceiling for minutes.
		$time_mins  = intval( ceil( $count / $words_per_min ) );
		$time_hours = 0;

		// If more than 60 mins, calculate hours and get leftover mins.
		if ( 60 <= $time_mins ) {
			$time_hours = intval( floor( $time_mins / 60 ) );
			$time_mins  = intval( $time_mins % 60 );
		}

		// Set up text for hours.
		$text_hours = sprintf(
			Str::nText( '%d Hour', '%d Hours', $time_hours ),
			number_format( $time_hours )
		);

		// Set up text for minutes.
		$text_mins = sprintf(
			Str::nText( '%d Minute', '%d Minutes', $time_mins ),
			number_format( $time_mins )
		);

		// If there are no hours, just return the minutes.
		// If there are no minutes, just return the hours.
		if ( 0 >= $time_hours ) {
			return $text_mins;
		} elseif ( 0 >= $time_mins ) {
			return $text_hours;
		}

		// Merge hours + minutes text.
		return sprintf( '%s, %s', $text_hours, $text_mins );
	}
}
