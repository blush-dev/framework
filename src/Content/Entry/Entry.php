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

use Blush\Proxies\App;
use Blush\Content\Query;
use Blush\Content\Types\Type;
use Blush\Tools\Str;

abstract class Entry
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
	public function type() : Type
	{
		return $this->type;
	}

	/**
	 * Returns the entry URI.
	 *
	 * @since 1.0.0
	 */
	public function uri() : string
	{
		return $uri ?: '';
	}

	/**
	 * Returns the entry content.
	 *
	 * @since 1.0.0
	 */
	public function content() : string
	{
		return $this->content;
	}

	/**
	 * Returns entry metadata.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	public function meta( string $name = '' )
	{
		if ( $name ) {
			return $this->meta[ $name ] ?? false;
		}

		return $this->meta;
	}

	/**
	 * Returns only a single meta value. Shifts and returns the first value
	 * if the metadata is an array.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	public function metaSingle( string $name = '' )
	{
		$meta = $this->meta( $name );
		return $meta && is_array( $meta ) ? array_shift( $meta ) : $meta;
	}

	/**
	 * Ensures that an array of meta values is returned.
	 *
	 * @since  1.0.0
	 */
	public function metaArr( string $name = '' ) : array
	{
		if ( ! $meta = $this->meta( $name ) ) {
			return [];
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
			$this->resolved_meta[ $name ] = new Query( $args );
		}

		// Return the resolved meta.
		return $this->resolved_meta[ $name ];
	}

	/**
	 * Returns the entry title.
	 *
	 * @since 1.0.0
	 */
	public function title() : string
	{
		return (string) $this->metaSingle( 'title' );
	}

	/**
	 * Returns the entry subtitle.
	 *
	 * @since 1.0.0
	 */
	public function subtitle() : string
	{
		return (string) $this->metaSingle( 'subtitle' );
	}

	/**
	 * Returns the entry date.
	 *
	 * @since 1.0.0
	 */
	public function date() : string
	{
		if ( ! $date = $this->metaSingle( 'date' ) ) {
			return '';
		}

		// @todo - config/site.yaml
		return date(
			'F j, Y',
		 	is_numeric( $date ) ? $date : strtotime( $date )
		);
	}

	/**
	 * Returns the entry author.
	 *
	 * @since  1.0.0
	 */
	public function author() : string
	{
		return (string) $this->metaSingle( 'author' );
	}

	/**
	 * Returns the entry authors.
	 *
	 * @since  1.0.0
	 */
	public function authors() : array
	{
		return (string) $this->metaArr( 'author' );
	}

	/**
	 * Returns a Query of taxonomy entries or false.
	 *
	 * @since  1.0.0
	 * @return false|Query
	 */
	public function terms( string $taxonomy, array $args = [] )
	{
		$types = App::resolve( 'content/types' );

		if ( $types->has( $taxonomy ) && $types->get( $taxonomy )->isTaxonomy() ) {
			return $this->metaQuery(
				$types->get( $taxonomy )->type(),
				$args
			);
		}

		return false;
	}

	/**
	 * Returns the entry excerpt.
	 *
	 * @since  1.0.0
	 */
	public function excerpt( int $limit = 50, string $more = '&hellip;' ) : string
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
}
