<?php
/**
 * Document title class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template\Tags;

use Blush\App;
use Blush\Tools\Str;

class DocumentTitle
{

	/**
	 * Stores the built document title.
	 *
	 * @since 1.0.0
	 */
	protected string $doctitle = '';

	/**
	 * View title.
	 *
	 * @since 1.0.0
	 */
	protected string $view_title = '';

	/**
	 * Page number for paged views.
	 *
	 * @since 1.0.0
	 */
	protected int $page = 1;

	/**
	 * Separator string between doctitle items
	 *
	 * @since  1.0.0
	 */
	protected string $sep = '&mdash;';

	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( string $title = '', array $options = [] )
	{
		$this->view_title = $title;

		if ( isset( $options['page'] ) ) {
			$this->page = abs( intval( $options['page'] ) );
		}
	}

	/**
	 * Returns the doctitle between `<title>` tags.
	 *
	 * @since 1.0.0
	 */
	public function toHtml() : string
	{
		return sprintf(
			'<title>%s</title>',
			$this->render()
		);
	}

	/**
	 * Returns the view title.
	 *
	 * @since 1.0.0
	 */
	public function viewTitle() : string
	{
		return $this->view_title;
	}

	/**
	 * Displays the doctitle.
	 *
	 * @since 1.0.0
	 */
	public function display() : void
	{
		echo $this->render();
	}

	/**
	 * Returns the doctitle.
	 *
	 * @since 1.0.0
	 */
	public function render() : string
	{
		if ( $this->doctitle ) {
			return $this->doctitle;
		}

		$app_title   = config( 'app.title' );
		$app_tagline = config( 'app.tagline' ) ?: config( 'app.description' );
		$paged       = 2 <= $this->page;
		$items       = [];

		$items['title'] = $this->view_title ? \e( $this->view_title ) : \e( $app_title );

		if ( $paged ) {
			$items['page'] = sprintf( 'Page %s', intval( $this->page ) );
		}

		if ( $this->view_title ) {
			$items['app_title'] = \e( $app_title );
		}

		if ( ! $this->view_title && ! $paged ) {
			$items['app_tagline'] = \e( $app_tagline );
		}

		$this->doctitle = implode( " {$this->sep} ", array_filter( $items ) );

		return $this->doctitle;
	}
}
