<?php
/**
 * Pagination class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template\Tags;

use Blush\Tools\Str;

class Pagination {

	/**
	 * An array of the pagination items.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $items = [];

	/**
	 * The total number of pages.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int
	 */
	protected $total = 0;

	/**
	 * The current page being viewed.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int
	 */
	protected $current = 0;

	/**
	 * The number of items to show on the ends.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int
	 */
	protected $end_size = 0;

	/**
	 * The number of items to show in the middle.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int
	 */
	protected $mid_size = 0;

	/**
	 * Helper for keeping track of whether to show dots instead of a number.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    bool
	 */
	protected $dots = false;

	/**
	 * Create a new pagination object.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array   $args
	 * @return void
	 */
	public function __construct( array $args = [] ) {

		$defaults = [
			// Base arguments.
			'base'               => '',
			'format'             => 'page/{page}',
			'total'              => 0,
			'current'            => 0,

			// Customize the items that are shown.
			'show_all'           => false,
			'end_size'           => 1,
			'mid_size'           => 1,
			'prev_next'          => true,

			// Custom text, content, and HTML.
			'prev_text'          => '',
			'next_text'          => '',
			'screen_reader_text' => '',
			'title_text'         => '',
			'before_page_number' => '',
			'after_page_number'  => '',

			// HTML tags.
			'container_tag'      => 'nav',
			'title_tag'          => 'h2',
			'list_tag'           => 'ul',
			'item_tag'           => 'li',

			// Attributes.
			'container_class'    => 'pagination',
			'title_class'        => 'pagination__title screen-reader-text',
			'list_class'         => 'pagination__items',
			'item_class'         => 'pagination__item pagination__item--%s',
			'anchor_class'       => 'pagination__anchor pagination__anchor--%s',
			'aria_current'       => 'page'
		];

		// Parse the args with the defaults.
		$this->args = array_merge( $defaults, $args );

		// Append the base to the full URI.
		$this->args['base'] = Str::appendUri( uri( $this->args['base'] ), '%_%' );

		// Make sure that we have absolute integers.
		$this->total    = abs( intval( $this->args['total']    ) );
		$this->current  = abs( intval( $this->args['current']  ) );
		$this->end_size = abs( intval( $this->args['end_size'] ) );
		$this->mid_size = abs( intval( $this->args['mid_size'] ) );

		// The end size must be at least 1.
		if ( $this->end_size < 1 ) {
			$this->end_size = 1;
		}
	}

	protected function setArgs( array $args = [] ) {
		foreach ( $args as $key => $value ) {
			if ( isset( $this->args[ $key ] ) ) {
				$this->args[ $key ] = $value;
			}
		}
	}

	/**
	 * Outputs the pagination output.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function display() {
		echo $this->render();
	}

	/**
	 * Returns the pagination output.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function render() {
		$title = $list = $template = '';

		if ( $this->items ) {

			// If there's title text, format it.
			if ( $this->args['title_text'] ) {
				$title = sprintf(
					'<%1$s class="%2$s">%3$s</%1$s>',
					escape_tag( $this->args['title_tag'] ),
					e( $this->args['title_class'] ),
					e( $this->args['title_text'] )
				);
			}

			// Loop through each of the items and format each into
			// an HTML string.
			foreach ( $this->items as $item ) {
				$list .= $this->formatItem( $item );
			}

			// Format the list.
			$list = sprintf(
				'<%1$s class="%2$s">%3$s</%1$s>',
				escape_tag( $this->args['list_tag'] ),
				e( $this->args['list_class'] ),
				$list
			);

			// Format the nav wrapper.
			$template = sprintf(
				'<%1$s class="%2$s" role="navigation">%3$s%4$s</%1$s>',
				escape_tag( $this->args['container_tag'] ),
				e( $this->args['container_class'] ),
				$title,
				$list
			);
		}

		return $template;
	}

	/**
	 * Builds the pagination `$items` array.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return PaginationContract
	 */
	public function make( array $args = [] ) {
		$this->setArgs( $args );

		if ( 2 <= $this->total ) {

			$this->prevItem();

			for ( $n = 1; $n <= $this->total; $n++ ) {
				$this->pageItem( $n );
			}

			$this->nextItem();
		}

		return $this;
	}

	/**
	 * Format an item's HTML output.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array   $item
	 * @return string
	 */
	private function formatItem( $item ) {

		$is_link  = isset( $item['url'] );
		$attr     = [];
		$esc_attr = '';

		// Add the anchor/span class attribute.
		$attr['class'] = sprintf(
			$this->args['anchor_class'],
			$is_link ? 'link' : $item['type']
		);

		// If this is a link, add the URL.
		if ( $is_link ) {
			$attr['href'] = $item['url'];
		}

		// If this is the current item, add the `aria-current` attribute.
		if ( 'current' === $item['type'] ) {
			$attr['aria-current'] = $this->args['aria_current'];
		}

		// Loop through the attributes and format them into a string.
		foreach ( $attr as $name => $value ) {

			$esc_attr .= sprintf(
				' %s="%s"',
				e( $name ),
				'href' === $name ? e( $value ) : e( $value )
			);
		}

		// Builds and formats the list item.
		return sprintf(
			'<%1$s class="%2$s"><%3$s %4$s>%5$s</%3$s></%1$s>',
			escape_tag( $this->args['item_tag'] ),
			e( sprintf( $this->args['item_class'], $item['type'] ) ),
			$is_link ? 'a' : 'span',
			trim( $esc_attr ),
			$item['content']
		);
	}

	/**
	 * Builds the previous item.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return void
	 */
	protected function prevItem() {

		if ( $this->args['prev_next'] && $this->current && 1 < $this->current ) {

			$this->items[] = [
				'type'    => 'prev',
				'url'     => $this->buildUrl( 2 == $this->current ? '' : $this->args['format'], $this->current - 1 ),
				'content' => $this->args['prev_text']
			];
		}
	}

	/**
	 * Builds the next item.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return void
	 */
	protected function nextItem() {

		if ( $this->args['prev_next'] && $this->current && $this->current < $this->total ) {

			$this->items[] = [
				'type'    => 'next',
				'url'     => $this->buildUrl( $this->args['format'], $this->current + 1 ),
				'content' => $this->args['next_text']
			];
		}
	}

	/**
	 * Builds the numeric page link, current item, and dots item.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return void
	 */
	protected function pageItem( $n ) {

		// If the current item we're building is for the current page
		// being viewed.
		if ( $n === $this->current ) {

			$this->items[] = [
				'type'    => 'current',
				'content' => $this->args['before_page_number'] . e( $n ) . $this->args['after_page_number']
			];

			$this->dots = true;

		// If showing a linked number or dots.
		} else {

			if ( $this->args['show_all'] || ( $n <= $this->end_size || ( $this->current && $n >= $this->current - $this->mid_size && $n <= $this->current + $this->mid_size ) || $n > $this->total - $this->end_size ) ) {

				$this->items[] = [
					'type'    => 'number',
					'url'     => $this->buildUrl( 1 == $n ? '' : $this->args['format'], $n ),
					'content' => $this->args['before_page_number'] . e( $n ) . $this->args['after_page_number']
				];

				$this->dots = true;

			} elseif ( $this->dots && ! $this->args['show_all'] ) {

				$this->items[] = [
					'type'    => 'dots',
					'content' => '&hellip;'
				];

				$this->dots = false;
			}
		}
	}

	/**
	 * Builds and formats a page link URL.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string    $format
	 * @param  int       $number
	 * @return string
	 */
	protected function buildUrl( $format, $number ) {

		$uri = str_replace( '%_%', $format, $this->args['base'] );
		$uri = str_replace( '{page}', $number, $uri );

		return $uri;
	}
}
