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

// Interfaces.
use IteratorAggregate;
use Blush\Contracts\Makeable;
use Blush\Contracts\Content\{Entry, Locator};
use Blush\Contracts\Content\Query as QueryContract;

// Classes.
use ArrayIterator;
use Traversable;
use Blush\App;
use Blush\Tools\Str;

class Query implements Makeable, QueryContract, IteratorAggregate
{
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
	 * Whether to remove `index.{ext}` files.
	 *
	 * @since 1.0.0
	 */
	protected bool $noindex = true;

	/**
	 * Whether to return a query of posts without content. This can be useful
	 * for list-style output and other situations where content is not
	 * needed.  It is also faster because we do not have to parse the
	 * Markdown for the file.
	 *
	 * @since 1.0.0
	 */
	protected bool $nocontent = false;

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
	 * Query entries by authors.
	 *
	 * @since 1.0.0
	 */
	protected array $authors = [];

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
	 * Exclude entries by filename (w/o extension).
	 *
	 * @since 1.0.0
	 */
	protected array $names_exclude = [];

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
	 * Query entries by hour.
	 *
	 * @since 1.0.0
	 */
	protected ?int $hour = null;

	/**
	 * Query entries by minute.
	 *
	 * @since 1.0.0
	 */
	protected ?int $minute = null;

	/**
	 * Query entries by second.
	 *
	 * @since 1.0.0
	 */
	protected ?int $second = null;

	/**
	 * Sets up object state. The path is relative to the user content
	 * folder. If no value is passed in, it will be the root.
	 *
	 * @since 1.0.0
	 */
        public function __construct( protected Locator $locator ) {}

	/**
	 * Sets up the query options and makes the query.
	 *
	 * @since 1.0.0
	 */
	public function make( array $options = [] ): self
	{
        	foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
        		if ( isset( $options[ $key ] ) ) {
        			$this->$key = $options[ $key ];
        		}
        	}

		// Back-compat for `$names`.
		if ( isset( $options['slug'] ) && ! isset( $options['names'] ) ) {
			$this->names = (array) $options['slug'];
		}

		// Back-compat for `date` when used instead of `publish`.
		if ( 'date' === $this->orderby ) {
			$this->orderby = 'published';
		}

		if ( 'date' === $this->meta_key ) {
			$this->meta_key = 'published';
		}

		// Disable `noindex` if `index` is being specifically queried.
		if ( in_array( 'index', $this->names, true ) ) {
			$this->noindex = false;
		}

		// Convert single `author` to array of `authors`.
		if ( isset( $options['author'] ) ) {
			$this->authors = (array) $options['author'];
		}

		// If a content type is passed in, use its path.
		if ( ! $this->path && isset( $options['type'] ) ) {
			$types = App::resolve( 'content.types' );

			if ( $types->has( $options['type'] ) ) {
				$this->path = $types->get( $options['type'] )->path();
			}
		}

		// If query is set to a negative number or 0, we are querying
		// all posts, so set this high.
		if ( 0 >= $this->number ) {
			$this->number = PHP_INT_MAX;
		}

		// Lowercase order.
		$this->order = strtolower( $this->order );

		// Sets the path to the locator.
		$this->locator->setPath( $this->path );

		// Run the query.
		$this->build();

		// make() should always return self.
		return $this;
        }

	/**
	 * Filters, sorts, and reduces located entries according the query vars.
	 *
	 * @since 1.0.0
	 */
	protected function build(): void
	{
		$located = $this->locator->all();

		// Set the entry properties to arrays.
		$this->entries = [];
		$this->located_slugs = [];

		// Filter entries based on query vars.
		$located = $this->filterByVisibility( $located );
		$located = $this->filterByNames(   $located );
		$located = $this->filterByDate(    $located );
		$located = $this->filterByAuthor(  $located );
		$located = $this->filterByMeta(    $located );

		// Sort entries based on query vars.
		$located = $this->sortByOrder( $located );

		// Reduce array of located files to filenames.
		$filepaths = array_keys( $located );

		// Remove index file if noindex query.
		if ( $this->noindex && ! in_array( 'index', $this->names ) ) {
			$filepaths = array_filter(
				$filepaths,
				fn( $filepath ) => 'index' !== Str::beforeLast( basename( $filepath ), '.' )
			);
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
			$entry = App::resolve( 'content.entry', [
				'filepath' => $filepath,
				'options'  => [ 'nocontent' => $this->nocontent ]
			] );

			$pathinfo = pathinfo( $filepath );
			$filename = $pathinfo['filename'];
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
        public function all(): array
	{
		return $this->entries;
	}

	/**
	 * Checks if the query has any entries.
	 *
	 * @since 1.0.0
	 */
	public function hasEntries(): bool
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
	public function has( string $slug ): bool
	{
		return isset( $this->located_slugs[ $slug ] ) ||
		       in_array( $slug, $this->located_slugs, true );
	}

	/**
	 * Returns the first entry. Alias for `first()`.
	 *
	 * @since 1.0.0
	 */
	public function single(): ?Entry
	{
		return $this->first();
	}

	/**
	 * Returns the first entry.
	 *
	 * @since 1.0.0
	 */
	public function first(): ?Entry
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
	public function last(): ?Entry
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
	public function count(): int
	{
		return abs( intval( $this->count ) );
	}

	/**
	 * Returns the total entries.
	 *
	 * @since 1.0.0
	 */
	public function total(): int
	{
		return abs( intval( $this->total ) );
	}

	/**
	 * Returns the number query option.
	 *
	 * @since 1.0.0
	 */
	public function number(): int
	{
		return abs( intval( $this->number ) );
	}

	/**
	 * Returns the number of pages of entries. This is for use with
	 * pagination output.
	 *
	 * @since 1.0.0
	 */
	public function pages(): int
	{
		return abs( intval( ceil( $this->total() / $this->number() ) ) );
	}

	/**
	 * Returns the offset query option.
	 *
	 * @since 1.0.0
	 */
	public function offset(): int
	{
		return abs( intval( $this->offset ) );
	}

	/**
	 * Filter entries by visibility.
	 *
	 * @todo  Allow queries specifically for visibility.
	 * @since 1.0.0
	 */
	private function filterByVisibility( array $entries ): array
	{
		$located = [];

		foreach ( $entries as $file => $matter ) {

			if ( ! isset( $matter['visibility'] ) ) {
				$matter['visibility'] = 'public';
			}

			$slug = Str::afterFirst(
				pathinfo( $file, PATHINFO_FILENAME ),
				'.'
			);

			// If the entry is hidden and not specifically queried,
			// don't add it to the located array.
			if (
				! in_array( $slug, $this->names ) &&
			 	'hidden' === $matter['visibility']
			) {
				continue;
			}

	                $located[ $file ] = $matter;
		}

		return $located;
	}

	/**
	 * Filter entries by filename/slug.
	 *
	 * @since 1.0.0
	 */
        private function filterByNames( array $entries ): array
	{
                if ( ! $this->names && ! $this->names_exclude ) {
                        return $entries;
                }

                $located = [];

                foreach ( $entries as $file => $matter ) {

			// Get the filename without the extension.
			$name = pathinfo( $file, PATHINFO_FILENAME );

			// Strip anything before potential ordering dot, e.g.,
			// `01.{$name}`, `02.{$name}`, etc.
			if ( Str::contains( $name, '.' ) ) {
				$name =  Str::afterLast( $name, '.' );
			}

			// Check if the name is specifically included/excluded.
			// Included names take precedence over excluded.
                	if ( $this->names && in_array( $name, $this->names ) ) {
                		$located[ $file ] = $matter;
                		continue;
                	} elseif ( $this->names_exclude && ! in_array( $name, $this->names_exclude ) ) {
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
        private function filterByDate( array $entries ): array
	{
                if ( ! $this->year && ! $this->month && ! $this->day ) {
                        return $entries;
                }

		$located = [];

		foreach ( $entries as $file => $matter ) {

			$date = $matter['published'] ?? $matter['date'] ?? false;

			if ( ! $date ) {
				continue;
			}

			$timestamp = is_numeric( $date ) ? $date : strtotime( $date );

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
	 * Filter entries by author. Technically, authors are stored as metadata,
	 * but this offers a dedicated solution specifically for this use case.
	 *
	 * @since 1.0.0
	 */
	private function filterByAuthor( array $entries ): array
	{
		if ( ! $this->authors ) {
			return $entries;
		}

		// Sanitize author names.
		$authors = array_map(
			fn( $author ) => sanitize_slug( $author ),
			$this->authors
		);

		$located = [];

		foreach ( $entries as $basename => $matter ) {

			// Skip entry if no author set.
			if ( ! isset( $matter['author'] ) ) {
				continue;
			}

			// Loop through each of the queried authors. If a queried
			// author is not found in the entry's author list, skip
			// ahead to the next entry in the parent loop.
			foreach ( $authors as $author ) {
				if ( ! in_array( $author, (array) $matter['author'] ) ) {
					continue 2;
				}
			}

			// If we get to this point, all authors queried were
			// found in the entry's author list.
			$located[ $basename ] = $matter;
		}

		return $located;
	}

	/**
	 * Filter entries by meta.
	 *
	 * @since 1.0.0
	 */
	private function filterByMeta( array $entries ): array
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
        private function sortByOrder( array $entries ): array
	{
		$meta_keys = [
			'author',
			'published',
			'updated',
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
	public function getIterator(): Traversable
	{
		return new ArrayIterator( $this->all() );
	}
}
