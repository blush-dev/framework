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

// Concretes.
use Blush\{App, Config, Query, Url};
use Blush\Content\Types\Type;
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
	 * Returns the entry type.
	 *
	 * @since 1.0.0
	 */
	public function type(): Type
	{
		return $this->type;
	}

	/**
	 * Returns the entry name (slug). By default, we try to use a sanitized
	 * version of the title if set. Otherwise, we add a unique ID. This
	 * should be handled in sub-classes for a proper solution.
	 *
	 * @since 1.0.0
	 */
	public function name(): string
	{
		$title = $this->title();
		return $title ? sanitize_slug( $tile ) : uniqid( 'entry-' );
	}

	/**
	 * Returns a post's visibility. Currently, the API allows for
	 * `public` and `hidden`.
	 *
	 * @since 1.0.0
	 */
	public function visibility(): string
	{
		$visibility = $this->metaSingle( 'visibility' );

		return $visibility && in_array( $visibility, [
			'public',
			'hidden'
		] ) ? $visibility : 'public';
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
	public function url():  string
	{
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
	public function uri():  string
	{
		return $this->url();
	}

	/**
	 * Returns the entry content.
	 *
	 * @since 1.0.0
	 */
	public function content():  string
	{
		return $this->content;
	}

	/**
	 * Returns entry metadata.
	 *
	 * @since  1.0.0
	 * @param  mixed  $default
	 * @return mixed
	 */
	public function meta( string $name = '', $default = false )
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
	 * @param  mixed  $default
	 * @return mixed
	 */
	public function metaSingle( string $name, $default = false )
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
	 * @return Query|false
	 */
	public function metaQuery( string $name, array $args = [] )
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
	public function title():  string
	{
		return (string) $this->metaSingle( 'title' );
	}

	/**
	 * Returns the entry subtitle.
	 *
	 * @since 1.0.0
	 */
	public function subtitle():  string
	{
		return (string) $this->metaSingle( 'subtitle' );
	}

	/**
	 * Returns the entry date.
	 *
	 * @since 1.0.0
	 */
	public function date():  string
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
	public function author():  string
	{
		return (string) $this->metaSingle( 'author' );
	}

	/**
	 * Returns the entry authors.
	 *
	 * @since  1.0.0
	 */
	public function authors():  array
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
	 * @return false|Query
	 */
	public function terms( string $taxonomy, array $args = [] )
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
	public function hasTerm( string $term, string $taxonomy = 'tag' ): bool
	{
		$terms = $this->terms( $taxonomy );

		return $terms ? $terms->has( $term ) : false;
	}

	/**
	 * Returns the entry excerpt.
	 *
	 * @since  1.0.0
	 */
	public function excerpt( int $limit = 50, string $more = '&hellip;' ):  string
	{
		if ( $content = $this->metaSingle( 'excerpt' ) ) {
			return App::resolve( 'markdown' )->convert(
				$content
			)->content();
		}

		return sprintf( '<p>%s</p>', Str::words(
			strip_tags( $content ?: $this->content() ),
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
		$words_per_hour = $words_per_min * 60;

		// Strip tags and get the word count from the content.
		$count = str_word_count( strip_tags( $this->content() ) );

		// Get the floor for hours.  Otherwise, it will round up to
		// `1.0`. But, get the ceiling for minutes.
		$time_hours = intval( floor( $count / $words_per_hour ) );
		$time_mins  = intval( ceil( $count / $words_per_min ) );

		// If there are no hours, just return the minutes.
		if ( 0 >= $time_hours ) {
			return sprintf(
				Str::nText( '%d Minute', '%d Minutes', $time_mins ),
				number_format( $time_mins )
			);
		}

		// Subtract the hours by minute from the total minutes.
		$time_mins = $time_mins - ( $time_hours * 60 );

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

		// If no minutes left after subtraction of hours, return hours.
		if ( 0 >= $time_mins ) {
			return $text_hours;
		}

		// Merge hours + minutes text.
		return sprintf( '%s, %s', $text_hours, $text_mins );
	}
}
