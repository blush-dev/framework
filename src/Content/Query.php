<?php
/**
 * Queries content.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content;

use ArrayIterator;
use IteratorAggregate;
use Traversable;
use Blush\App;
use Blush\Content\Entry\{Entry, MarkdownFile};
use Blush\Tools\Str;

class Query implements IteratorAggregate
{
	/**
	 * File locator object.
	 *
	 * @since 1.0.0
	 */
        protected Locator $locator;

	/**
	 * Path to the entries relative to the content folder.
	 *
	 * @since 1.0.0
	 */
	protected string $path = '';

	/**
	 * Array of `Entry` objects.
	 *
	 * @since 1.0.0
	 */
	protected array $entries;

	/**
	 * Array of filenames.
	 *
	 * @since 1.0.0
	 */
	protected array $filepaths;

	/**
	 * Array of located entry slugs.
	 *
	 * @since 1.0.0
	 */
	protected array $located_slugs;

	/**
	 * Stores the first entry object.
	 *
	 * @since 1.0.0
	 */
	protected ?Entry $first = null;

	/**
	 * Stores the last entry object.
	 *
	 * @since 1.0.0
	 */
	protected ?Entry $last = null;

	/**
	 * Count of found entries.
	 *
	 * @since 1.0.0
	 */
	protected int $count = 0;

	/**
	 * Total number of entries.
	 *
	 * @since 1.0.0
	 */
	protected int $total = 0;

	/**
	 * Whether to remove `index.md` files.
	 *
	 * @since 1.0.0
	 */
	protected bool $noindex = true;

	/**
	 * Number of entries to query.
	 *
	 * @since 1.0.0
	 */
	protected int $number = 10;

	/**
	 * Number of entries to offset.
	 *
	 * @since 1.0.0
	 */
	protected int $offset = 0;

	/**
	 * How to order entries (asc|desc).
	 *
	 * @since 1.0.0
	 */
	protected string $order = 'asc';

	/**
	 * What to sort entries by (filename).
	 *
	 * @since 1.0.0
	 */
	protected string $orderby = 'filename';

	/**
	 * Query entries with meta key.
	 *
	 * @since 1.0.0
	 */
	protected string $meta_key = '';

	/**
	 * Query entries with meta value. Meta key is required.
	 *
	 * @since 1.0.0
	 */
	protected string $meta_value = '';

	/**
	 * Query entries by filename (w/o extension).
	 *
	 * @since 1.0.0
	 */
	protected array $names = [];

	/**
	 * Query entries by year.
	 *
	 * @since 1.0.0
	 */
	protected ?int $year = null;

	/**
	 * Query entries by month.
	 *
	 * @since 1.0.0
	 */
	protected ?int $month = null;

	/**
	 * Query entries by day.
	 *
	 * @since 1.0.0
	 */
	protected ?int $day = null;

	/**
	 * Sets up object state. The path is relative to the user content
	 * folder. If no value is passed in, it will be the root.
	 *
	 * @since 1.0.0
	 */
        public function __construct( array $options = [] )
	{
        	foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
        		if ( isset( $options[ $key ] ) ) {
        			$this->$key = $options[ $key ];
        		}
        	}

		// Back-compat for `$names`.
		if ( isset( $options['slug'] ) && ! isset( $options['name'] ) ) {
			$this->names = (array) $options['slug'];
		}

		// Disable `noindex` if `index` is being specifically queried.
		if ( in_array( 'index', $this->names, true ) ) {
			$this->noindex = false;
		}

		// If a content type is passed in, use its path.
		if ( ! $this->path && isset( $options['type'] ) ) {
			$types = App::resolve( 'content/types' );

			if ( $types->has( $options['type'] ) ) {
				$this->path = $types->get( $options['type'] )->path();
			}
		}

		// If query is set to a negative number or 0, we are querying
		// all posts, so set this high.
		if ( 0 >= $this->number ) {
			$this->number = PHP_INT_MAX;
		}

		$this->order   = strtolower( $this->order );
                $this->locator = new Locator( $this->path );

		// Run the query.
		$this->build();
        }

	/**
	 * Filters, sorts, and reduces located entries according the query vars.
	 *
	 * @since 1.0.0
	 */
	protected function build() : void
	{
		$located = $this->locator->all();

		// Set the entry properties to arrays.
		$this->entries = [];
		$this->located_slugs = [];

		// Filter entries based on query vars.
		$located = $this->filterByNames( $located );
		$located = $this->filterByDate( $located );
		$located = $this->filterByMeta( $located );

		// Sort entries based on query vars.
		$located = $this->sortByOrder( $located );

		// Reduce array of located files to filenames.
		$filepaths = array_keys( $located );

		// Remove index file if noindex query.
		if ( $this->noindex && ! in_array( 'index', $this->names ) ) {
			$filepaths = array_filter( $filepaths, function( $filepath ) {
				return 'index' !== basename( $filepath, '.md' );
			} );
		}

		// Get the total number of entries.
		$this->total = count( $filepaths );

		// Reduce to currently-queried number.
		$this->filepaths = array_slice(
			$filepaths,
			$this->offset(),
			$this->number()
		);

		// Get the current number of entries.
		$this->count = count( $this->filepaths );

		// Create array of entry objects.
		foreach ( $this->filepaths as $filepath ) {
			$entry = new MarkdownFile( $filepath );

			$filename = $entry->pathinfo( 'filename' );
			$slug     = Str::afterLast( $filename, '.' );

			$this->entries[] = $entry;
			$this->located_slugs[ $slug ] = $filename;
		}
	}

	/**
	 * Returns the located entries as an array.
	 *
	 * @since 1.0.0
	 */
        public function all() : array
	{
		return $this->entries;
	}

	/**
	 * Checks if the query has any entries.
	 *
	 * @since 1.0.0
	 */
	public function hasEntries() : bool
	{
		return 0 < $this->count();
	}

	/**
	 * Checks if an entry was located by slug (basename w/o extension). It
	 * checks for both the slug with (e.g., `01.{$slug}`) and without (e.g.,
	 * `{$slug}`) the order prefix.
	 *
	 * @since 1.0.0
	 */
	public function has( string $slug ) : bool
	{
		return isset( $this->located_slugs[ $slug ] ) ||
		       in_array( $slug, $this->located_slugs, true );
	}

	/**
	 * Returns the first entry.
	 *
	 * @since 1.0.0
	 */
	public function first() : ?Entry
	{
		if ( ! $this->first && $all = $this->all() ) {
			$this->first = array_shift( $all );
		}

		return $this->first;
	}

	/**
	 * Returns the last entry.
	 *
	 * @since 1.0.0
	 */
	public function last() : ?Entry
	{
		if ( ! $this->last && $all = $this->all() ) {
			$this->last = array_pop( $all );
		}

		return $this->last;
	}

	/**
	 * Returns the count for the current query.
	 *
	 * @since 1.0.0
	 */
	public function count() : int
	{
		return abs( intval( $this->count ) );
	}

	/**
	 * Returns the total entries.
	 *
	 * @since 1.0.0
	 */
	public function total() : int
	{
		return abs( intval( $this->total ) );
	}

	/**
	 * Returns the number query option.
	 *
	 * @since 1.0.0
	 */
	public function number() : int
	{
		return abs( intval( $this->number ) );
	}

	/**
	 * Returns the offset query option.
	 *
	 * @since 1.0.0
	 */
	public function offset() : int
	{
		return abs( intval( $this->offset ) );
	}

	/**
	 * Filter entries by filename/slug.
	 *
	 * @since 1.0.0
	 */
        private function filterByNames( array $entries ) : array
	{
                if ( ! $this->names ) {
                        return $entries;
                }

                $located = [];

                foreach ( $entries as $file => $matter ) {

                	if ( isset( $matter['slug'] ) ) {

                		if ( in_array( $matter['slug'], $this->names ) ) {
                			$located[ $file ] = $matter;
                			continue;
                		} else {
					// If file has a defined slug, always use it.
                			continue;
                		}
        		}

                	$pathinfo = pathinfo( $file );

                	if ( in_array( $pathinfo['filename'], $this->names ) ) {
                		$located[ $file ] = $matter;
                		continue;
                	}

                	// Strips everything from front of string to the first `.` char.
                	$parts = explode( '.', $pathinfo['filename'] );
                	if ( 1 < count( $parts ) ) {
                		array_shift( $parts );
                	}
                	$slug_from_file = join( '', $parts );

                	if ( in_array( $slug_from_file, $this->names ) ) {
                		$located[ $file ] = $matter;
                		continue;
                	}
                }

                return $located;
        }

	/**
	 * Filter entries by date.
	 *
	 * @since 1.0.0
	 */
        private function filterByDate( array $entries ) : array
	{
                if ( ! $this->year && ! $this->month && ! $this->day ) {
                        return $entries;
                }

		$located = [];

		foreach ( $entries as $file => $matter ) {

			if ( ! isset( $matter['date'] ) ) {
				continue;
			}

			$timestamp = is_numeric( $matter['date'] )
				     ? $matter['date']
				     : strtotime( $matter['date'] );

			if ( $this->year && intval( $this->year ) !== intval( date( 'Y', $timestamp ) ) ) {
				continue;
			}

			if ( $this->month && intval( $this->month ) !== intval( date( 'm', $timestamp ) ) ) {
				continue;
			}

			if ( $this->day && intval( $this->day ) !== intval( date( 'd', $timestamp ) ) ) {
				continue;
			}

			$located[ $file ] = $matter;
		}

		return $located;
        }

	/**
	 * Filter entries by meta.
	 *
	 * @since 1.0.0
	 */
	private function filterByMeta( array $entries ) : array
	{
		if ( ! $this->meta_value && ! $this->meta_key ) {
			return $entries;
		}

		$located = [];
		$value = sanitize_slug( $this->meta_value );

		foreach ( $entries as $basename => $matter ) {

			if ( isset( $matter[ $this->meta_key ] ) ) {

				$_v = (array) $matter[ $this->meta_key ];

				$_v = array_map( function( $x ) {
					return sanitize_slug( $x );
				}, $_v );

				if ( in_array( $value, $_v ) ) {
					$located[ $basename ] = $matter;
				}
			}
		}

		return $located;
	}

	/**
	 * Sort entries by order query options.
	 *
	 * @since 1.0.0
	 */
        private function sortByOrder( array $entries ) : array
	{
		$meta_keys = [
			'author',
			'date',
			'title',
			$this->meta_key
		];

		if ( in_array( $this->orderby, $meta_keys ) ) {
			uasort( $entries, function( $a, $b ) {

				// If neither has the array key, return 0.
				if ( ! isset( $a[ $this->orderby ] ) && ! isset( $b[ $this->orderby ] ) ) {
					return 0;
				}

				// Ascending order.
				if ( 'asc' === $this->order ) {
					if ( isset( $a[ $this->orderby ] ) && ! isset( $b[ $this->orderby ] ) ) {
						return 1;
					} elseif ( ! isset( $a[ $this->orderby ] ) && isset( $b[ $this->orderby ] ) ) {
						return -1;
					}

					return $a[ $this->orderby ] <=> $b[ $this->orderby ];
				}

				// Fall back to descending order.
				if ( isset( $b[ $this->orderby ] ) && ! isset( $a[ $this->orderby ] ) ) {
					return 1;
				} elseif ( ! isset( $b[ $this->orderby ] ) && isset( $a[ $this->orderby ] ) ) {
					return -1;
				}

				return $b[ $this->orderby ] <=> $a[ $this->orderby ];
			} );

			return $entries;
		}

		// Order by filename.
        	if ( 'asc' === $this->order ) {
        		ksort( $entries );
        	} elseif ( 'desc' === $this->order ) {
        		krsort( $entries );
        	}

        	return $entries;
        }

	/**
	 * Needed for implementing the `IteratorAggregate` interface. This
	 * allows developers to use the object as an array in `foreach()` loops.
	 * What we do is create an `ArrayIterator` and pass along the entries.
	 *
	 * @since 1.0.0
	 */
	public function getIterator() : Traversable
	{
		return new ArrayIterator( $this->all() );
	}
}
