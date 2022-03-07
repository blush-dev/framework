<?php
/**
 * View template.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template;

use Blush\Proxies\App;
use Blush\Tools\Collection;

class View {

	/**
	 * Name of the view. This is primarily used as the folder name. However,
	 * it can also be the filename as the final fallback if no folder exists.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $name = '';

	/**
	 * Array of slugs to look for. This creates the hierarchy based on the
	 * `$name` property (e.g., `{$name}/{$slug}.php`). Slugs are used in
	 * the order that they are set.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $slugs = [];

	/**
	 * An array of data that is passed into the view template.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $data = [];

	/**
	 * The template filename.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $template = null;

	/**
	 * Sets up the view properties.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $name
	 * @param  array   $slugs
	 * @param  object  $data
	 * @return object
	 */
	public function __construct( string $name, array $slugs = [], Collection $data = null ) {
		$this->name  = $name;
		$this->slugs = $slugs;
		$this->data  = $data;
	}

	/**
	 * When attempting to use the object as a string, return the template output.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function __toString() {
		return $this->render();
	}

	/**
	 * Returns the array of slugs.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return array
	 */
	public function slugs() {
		return $this->slugs;
	}

	/**
	 * Uses the array of template slugs to build a hierarchy of potential
	 * templates that can be used.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return array
	 */
	protected function hierarchy() {

		// Uses the slugs to build a hierarchy.
		foreach ( $this->slugs as $slug ) {
			$templates[] = "{$this->name}/{$slug}.php";
		}

		// Add in a `default.php` template.
		if ( ! in_array( 'default', $this->slugs ) ) {
			$templates[] = "{$this->name}/default.php";
		}

		// Fallback to `{$name}.php` as a last resort.
		$templates[] = "{$this->name}.php";

		return $templates;
	}

	/**
	 * Locates the template.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return string
	 */
	protected function locate() {
		$path = App::resolve( 'path/public' ) . '/views';

		$templates = $this->hierarchy();

		foreach ( $templates as $template ) {
			if ( file_exists( "{$path}/{$template}" ) ) {
				return "{$path}/{$template}";
			}
		}

		return '';
	}

	/**
	 * Returns the located template.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function template() {

		if ( is_null( $this->template ) ) {
			$this->template = $this->locate();
		}

		return $this->template;
	}

	public function display() {
		echo $this->render();
	}

	public function render() {
		$template = $this->template();

		if ( ! $template ) {
			return '';
		}

		// Extract the data into individual variables. Each of
		// these variables will be available in the template.
		if ( $this->data instanceof Collection ) {
			extract( $this->data->all() );
		}

		// Make `$data` and `$view` variables available to templates.
		$data = $this->data;
		$view = $this;

		ob_start();
		include $template;
		return ob_get_clean();
	}
}
