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

// Contracts.
use Blush\Contracts\{Displayable, Renderable};

// Concretes.
use Blush\Tools\Str;

class Pagination implements Displayable, Renderable
{
	/**
	 * An array of the pagination items.
	 *
	 * @since 1.0.0
	 */
	protected array $items = [];

	/**
	 * Helper for keeping track of whether to show dots instead of a number.
	 *
	 * @since 1.0.0
	 */
	protected bool $dots = false;

	/**
	 * The total number of pages.
	 *
	 * @since 1.0.0
	 */
	protected int $total = 1;

	/**
	 * The current page being viewed.
	 *
	 * @since 1.0.0
	 */
	protected int $current = 1;

	/**
	 * The basepath for the current URI (relative).
	 *
	 * @since 1.0.0
	 */
	protected string $basepath = '';

	/**
	 * The format of the paged part of the URI string. Format should always
	 * include `{page}`, which will be replaced with the real page number.
	 *
	 * @since 1.0.0
	 */
	protected string $format = 'page/{page}';

	/**
	 * HTML and display options.
	 *
	 * @since 1.0.0
	 */
	protected array $display = [];

	/**
	 * Create a new pagination object.
	 *
	 * @since 1.0.0
	 */
	public function __construct( array $options = [] )
	{
		$options = array_merge( [
			'total'    => 1,
			'current'  => 1,
			'basepath' => '',
			'format'   => 'page/{page}'
		], $options );

		// Assign the format property only if it contains `{page}`.
		if ( Str::contains( $options['format'], '{page}' ) ) {
			$this->format = $options['format'];
		}

		// Append the basepath to the full URL
		$this->basepath = url( $options['basepath'] );

		// Make sure that we have absolute integers.
		$this->total   = abs( intval( $options['total']   ) );
		$this->current = abs( intval( $options['current'] ) );
	}

	/**
	 * Set and overwrite display options.
	 *
	 * @since 1.0.0
	 */
	protected function setDisplayOptions( array $options = [] ): void
	{
		$this->display = array_merge( [
			// Customize the items that are shown.
			'show_all'           => false,
			'end_size'           => 1,
			'mid_size'           => 1,
			'prev_next'          => true,
			'leading_zeroes'     => false,

			// Custom text, content, and HTML.
			'prev_text'          => '&larr; Previous',
			'next_text'          => 'Next &rarr;',
			'screen_reader_text' => '',
			'title_text'         => '',
			'before_page_number' => '',
			'after_page_number'  => '',

			// HTML tags.
			'container_tag'      => 'div',
			'nav_tag'            => 'nav',
			'title_tag'          => 'h2',
			'list_tag'           => 'ul',
			'item_tag'           => 'li',

			// Attributes.
			'container_class'    => 'block-pagination',
			'nav_class'          => 'block-pagination__nav',
			'title_class'        => 'block-pagination__title',
			'list_class'         => 'block-pagination__items',
			'item_class'         => 'block-pagination__item block-pagination__item--%s',
			'anchor_class'       => 'block-pagination__anchor block-pagination__anchor--%s',
			'aria_current'       => 'page'
		], $options );
	}

	/**
	 * Returns the total number of pages.
	 *
	 * @since 1.0.0
	 */
	public function total(): int
	{
		return $this->total;
	}

	/**
	 * Returns the current page being viewed.
	 *
	 * @since 1.0.0
	 */
	public function current(): int
	{
		return $this->current;
	}

	/**
	 * Conditional check for whether there are pages.
	 *
	 * @since 1.0.0
	 */
	public function hasPages(): bool
	{
		return 1 > $this->total();
	}

	/**
	 * Conditional check for whether currently a paged view.
	 *
	 * @since 1.0.0
	 */
	public function isPaged(): bool
	{
		return 1 < $this->current();
	}

	/**
	 * Conditionally check a number against the current page.
	 *
	 * @since 1.0.0
	 */
	public function isPage( int $number ): bool
	{
		return $number === $this->current();
	}

	/**
	 * Conditional check for whether currently viewing first page.
	 *
	 * @since 1.0.0
	 */
	public function isFirstPage(): bool
	{
		return 1 === $this->current();
	}

	/**
	 * Conditional check for whether currently viewing the last page.
	 *
	 * @since 1.0.0
	 */
	public function isLastPage(): bool
	{
		return $this->total() === $this->current();
	}

	/**
	 * Outputs the pagination output.
	 *
	 * @since 1.0.0
	 */
	public function display( array $options = [] ): void
	{
		echo $this->render( $options );
	}

	/**
	 * Returns the pagination output.
	 *
	 * @since 1.0.0
	 */
	public function render( array $options = [] ): string
	{
		$this->setDisplayOptions( $options );

		$title = $list = $template = '';

		if ( $items = $this->items() ) {

		        // If there's title text, format it.
		        if ( $this->display['title_text'] ) {
		                $title = sprintf(
		                        '<%1$s class="%2$s">%3$s</%1$s>',
		                        escape_tag( $this->display['title_tag'] ),
		                        e( $this->display['title_class'] ),
		                        e( $this->display['title_text'] )
		                );
		        }

		        // Loop through each of the items and format each into
		        // an HTML string.
		        foreach ( $items as $item ) {
		                $list .= $this->formatItem( $item );
		        }

		        // Format the list.
		        $list = sprintf(
		                '<%1$s class="%2$s">%3$s</%1$s>',
		                escape_tag( $this->display['list_tag'] ),
		                e( $this->display['list_class'] ),
		                $list
		        );

		        // Format the nav wrapper.
		        $template = sprintf(
		                '<%1$s class="%2$s" role="navigation">%3$s%4$s</%1$s>',
		                escape_tag( $this->display['nav_tag'] ),
		                e( $this->display['nav_class'] ),
		                $title,
		                $list
		        );

			// Format the container wrapper.
			if ( $this->display['container_tag'] ) {
				$template = sprintf(
					'<%1$s class="%2$s">%3$s</%1$s>',
			                escape_tag( $this->display['container_tag'] ),
			                e( $this->display['container_class'] ),
			                $template
				);
			}
		}

		return $template;
	}

	/**
	 * Returns the array of paginated items.
	 *
	 * @since 1.0.0
	 */
	protected function items(): array
	{
		if ( ! $this->items ) {
			$this->buildItems();
		}

		return $this->items;
	}

	/**
	 * Build the array of paginated items.
	 *
	 * @since 1.0.0
	 */
	protected function buildItems(): void
	{
		if ( 2 <= $this->total ) {
			$this->prevItem();

			for ( $n = 1; $n <= $this->total; $n++ ) {
				$this->pageItem( $n );
			}

			$this->nextItem();
		}
	}

	/**
	 * Format an item's HTML output.
	 *
	 * @since 1.0.0
	 */
	private function formatItem( array $item = [] ): string
	{
		$is_link  = isset( $item['url'] );
		$attr     = [];
		$esc_attr = '';

		// Add the anchor/span class attribute.
		$attr['class'] = sprintf(
			$this->display['anchor_class'],
			$is_link ? 'link' : $item['type']
		);

		// If this is a link, add the URL.
		if ( $is_link ) {
			$attr['href'] = $item['url'];
		}

		// If this is the current item, add the `aria-current` attribute.
		if ( 'current' === $item['type'] ) {
			$attr['aria-current'] = $this->display['aria_current'];
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
			escape_tag( $this->display['item_tag'] ),
			e( sprintf( $this->display['item_class'], $item['type'] ) ),
			$is_link ? 'a' : 'span',
			trim( $esc_attr ),
			$item['content']
		);
	}

	/**
	 * Builds the previous item.
	 *
	 * @since 1.0.0
	 */
	protected function prevItem(): void
	{
		if ( $this->display['prev_next'] && $this->display['prev_text'] && $this->current && 1 < $this->current ) {

			$this->items[] = [
				'type'    => 'prev',
				'url'     => $this->buildUrl( 2 == $this->current ? '' : $this->format, $this->current - 1 ),
				'content' => $this->display['prev_text']
			];
		}
	}

	/**
	 * Builds the next item.
	 *
	 * @since 1.0.0
	 */
	protected function nextItem(): void
	{
		if ( $this->display['prev_next'] && $this->display['next_text'] && $this->current && $this->current < $this->total ) {
			$this->items[] = [
				'type'    => 'next',
				'url'     => $this->buildUrl( $this->format, $this->current + 1 ),
				'content' => $this->display['next_text']
			];
		}
	}

	/**
	 * Builds the numeric page link, current item, and dots item.
	 *
	 * @since 1.0.0
	 */
	protected function pageItem( int $n ): void
	{
		$length = 10 < $this->total() ? strlen( $this->total() ) : 2;

		$number = $this->display['leading_zeroes']
			  ? Str::padLeft( $n, $length, '0' )
			  : $n;

		// If the current item we're building is for the current page
		// being viewed.
		if ( $n === $this->current ) {

			$this->items[] = [
				'type'    => 'current',
				'content' => $this->display['before_page_number'] . e( $number ) . $this->display['after_page_number']
			];

			$this->dots = true;

		// If showing a linked number or dots.
		} else {
			if (
				$this->display['show_all']
				|| (
					$n <= $this->display['end_size']
					|| (
						$this->current
						&& $n >= $this->current - $this->display['mid_size']
						&& $n <= $this->current + $this->display['mid_size']
					)
					|| $n > $this->total - $this->display['end_size']
				)
			) {

				$this->items[] = [
					'type'    => 'number',
					'url'     => $this->buildUrl( 1 == $n ? '' : $this->format, $n ),
					'content' => sprintf(
						'%s%s%s',
						$this->display['before_page_number'],
						e( $number ),
						$this->display['after_page_number']
					)
				];

				$this->dots = true;

			} elseif ( $this->dots && ! $this->display['show_all'] ) {

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
	 * @since 1.0.0
	 */
	protected function buildUrl( string $format, int $number ): string
	{
		return str_replace(
			'{page}',
			$number,
			Str::appendUri( $this->basepath, $format )
		);
	}
}
