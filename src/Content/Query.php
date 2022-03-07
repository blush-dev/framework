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

use Blush\Proxies\App;

class Query {

	/**
	 * File locator object.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    Locator
	 */
        protected $locator;

	/**
	 * Array of `Entry` objects.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $entries = [];

	/**
	 * Array of filenames.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $filenames = [];

	/**
	 * Stores the first entry object.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    Entry
	 */
	protected $first;

	/**
	 * Stores the last entry object.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    Entry
	 */
	protected $last;

	/**
	 * Count of found entries.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int
	 */
	protected $count = 0;

	/**
	 * Total number of entries.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int
	 */
	protected $total = 0;

	/**
	 * Whether to remove `index.md` files.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    bool
	 */
	protected $noindex = false;

	/**
	 * Number of entries to query.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    bool
	 */
	protected $number = 10;

	/**
	 * Number of entries to offset.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    bool
	 */
	protected $offset = 0;

	/**
	 * How to order entries (asc|desc).
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $order = 'asc';

	/**
	 * What to sort entries by (filename).
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $orderby = 'filename';

	/**
	 * Query entries with meta key.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $meta_key = '';

	/**
	 * Query entries with meta value. Meta key is required.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $meta_value = '';

	/**
	 * Query entries by slug.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $slug = [];

	/**
	 * Query entries by year.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int|null
	 */
	protected $year = null;

	/**
	 * Query entries by month.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int|null
	 */
	protected $month = null;

	/**
	 * Query entries by day.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int|null
	 */
	protected $day = null;

	/**
	 * Sets up object state. The path is relative to the user content
	 * folder. If no value is passed in, it will be the root.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $path
	 * @param  array   $options
	 * @return void
	 */
        public function __construct( string $path = '', array $options = [] ) {

        	foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
        		if ( isset( $options[ $key ] ) ) {
        			$this->$key = $options[ $key ];
        		}
        	}

		$this->order   = strtolower( $this->order );
                $this->slug    = (array) $this->slug;
                $this->locator = new Locator( $path );
        }

	/**
	 * Filters and returns all entries by the query options.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array   $entries
	 * @return array
	 */
        public function all() {

		if ( ! $this->entries ) {
                        $located = $this->locator->all();

                        // Filter entries based on query vars.
                        $located = $this->filterBySlug( $located );
                        $located = $this->filterByDate( $located );
                        $located = $this->filterByMeta( $located );

			// Sort entries based on query vars.
                        $located = $this->sortByOrder( $located );

                        // Reduce array of located files to filenames.
			$filenames = array_keys( $located );

                        // Remove index file if noindex query.
                        if ( $this->noindex && ! in_array( 'index', $this->slug ) ) {
                                $filenames = array_filter( $filenames, function( $filename ) {
                                        return 'index' !== basename( $filename, '.md' );
                                } );
                        }

                        // Get the total number of entries.
			$this->total = count( $filenames );

                        // Reduce to currently-queried number.
			$this->filenames = array_slice(
                                $filenames,
                                $this->offset(),
                                $this->number()
                        );

                        // Get the current number of entries.
			$this->count = count( $this->filenames );

                        // Create array of entry objects.
			foreach ( $this->filenames as $filename ) {
				$this->entries[] = new Entry( $filename );
			}
		}

		return $this->entries;
	}

	/**
	 * Returns the first entry.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return Entry
	 */
	public function first() {
		if ( ! $this->first ) {
			$all = $this->all();
			$this->first = array_shift( $all );
		}

		return $this->first;
	}

	/**
	 * Returns the last entry.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return Entry
	 */
	public function last() {
		if ( ! $this->last ) {
			$all = $this->all();
			$this->last = array_pop( $all );
		}

		return $this->last;
	}

	/**
	 * Returns the count for the current query.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return int
	 */
	public function count() {
		return abs( intval( $this->count ) );
	}

	/**
	 * Returns the total entries.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return int
	 */
	public function total() {
		return abs( intval( $this->total ) );
	}

	/**
	 * Returns the number query option.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return int
	 */
	public function number() {
		return abs( intval( $this->number ) );
	}

	/**
	 * Returns the offset query option.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return int
	 */
	public function offset() {
		return abs( intval( $this->offset ) );
	}

	/**
	 * Filter entries by slug.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array   $entries
	 * @return array
	 */
        private function filterBySlug( $entries ) {

                if ( ! $this->slug ) {
                        return $entries;
                }

                $located = [];

                foreach ( $entries as $file => $matter ) {

                	if ( isset( $matter['slug'] ) ) {

                		if ( in_array( $matter['slug'], (array) $this->slug ) ) {
                			$located[ $file ] = $matter;
                			continue;
                		} else {
                			continue; // If file has a defined slug, always use it.
                		}
        		}

                	$pathinfo = pathinfo( $file );

                	if ( in_array( $pathinfo['filename'], $this->slug ) ) {
                		$located[ $file ] = $matter;
                		continue;
                	}

                	// Strips everything from front of string to the first `.` char.
                	$parts = explode( '.', $pathinfo['filename'] );
                	if ( 1 < count( $parts ) ) {
                		array_shift( $parts );
                	}
                	$slug_from_file = join( '', $parts );

                	if ( in_array( $slug_from_file, (array) $this->slug ) ) {
                		$located[ $file ] = $matter;
                		continue;
                	}
                }

                return $located;
        }

	/**
	 * Filter entries by date.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array   $entries
	 * @return array
	 */
        private function filterByDate( $entries ) {

                if ( ! $this->year && ! $this->month && ! $this->day ) {
                        return $entries;
                }

		$located = [];

		foreach ( $entries as $file => $matter ) {

			if ( ! $matter['date'] ) {
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
	 * @since  1.0.0
	 * @access private
	 * @param  array   $entries
	 * @return array
	 */
	private function filterByMeta( $entries ) {

		if ( ! $this->meta_value && ! $this->meta_key ) {
			return $entries;
		}

		$located = [];
		$value = sanitize_slug( $this->meta_value );

		foreach ( $entries as $filename => $matter ) {

			if ( isset( $matter[ $this->meta_key ] ) ) {

				$_v = (array) $matter[ $this->meta_key ];

				$_v = array_map( function( $x ) {
					return sanitize_slug( $x );
				}, $_v );

				if ( in_array( $value, $_v ) ) {
					$located[ $filename ] = $matter;
				}
			}
		}

		return $located;
	}

	/**
	 * Sort entries by order query options.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array   $entries
	 * @return array
	 */
        private function sortByOrder( $entries ) {

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
}
