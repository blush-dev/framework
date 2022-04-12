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

// Contracts.
use Blush\Contracts\{Displayable, Renderable};
use Blush\Contracts\Template\View as ViewContract;

// Concretes.
use Blush\App;
use Blush\Tools\{Collection, Str};

class View implements ViewContract, Displayable, Renderable
{
	/**
	 * Names for the view. This is primarily used as the folder name. However,
	 * it can also be the filename as the final fallback if no folder exists.
	 *
	 * @since 1.0.0
	 */
	protected array $paths = [];

	/**
	 * An collection of data that is passed into the view template.
	 *
	 * @since  1.0.0
	 */
	protected Collection $data;

	/**
	 * The template filename.
	 *
	 * @since  1.0.0
	 */
	protected ?string $template = null;

	/**
	 * Sets up the view properties.
	 *
	 * @since  1.0.0
	 */
	public function __construct( array|string $paths, array|Collection $data = [] )
	{
		if ( ! $data instanceof Collection ) {
			$data = new Collection( (array) $data );
		}

		$this->paths = (array) $paths;
		$this->data = $data;
	}

	/**
	 * When attempting to use the object as a string, return the template
	 * output.
	 *
	 * @since 1.0.0
	 */
	public function __toString(): string
	{
		return $this->render();
	}

	/**
	 * Uses the array of template slugs to build a hierarchy of potential
	 * templates that can be used.
	 *
	 * @since 1.0.0
	 */
	protected function hierarchy(): array
	{
		return array_map( fn( $file ) => "{$file}.php", $this->paths );
	}

	/**
	 * Sets the view paths.
	 *
	 * @since 1.0.0
	 */
	public function setPaths( array $paths ): void
	{
		$this->paths = $paths;
	}

	/**
	 * Gets the view paths.
	 *
	 * @since 1.0.0
	 */
	public function getPaths(): array
	{
		return $this->paths;
	}

	/**
	 * Sets the view data.
	 *
	 * @since 1.0.0
	 */
	public function setData( Collection $data ): void
	{
		$this->data = $data;
	}

	/**
	 * Gets the view data.
	 *
	 * @since 1.0.0
	 */
	public function getData(): Collection
	{
		return $this->data;
	}

	/**
	 * Locates the template.
	 *
	 * @since 1.0.0
	 */
	protected function locate(): string
	{
		foreach ( $this->hierarchy() as $template ) {
			$filepath = view_path( $template );

			if ( file_exists( $filepath ) ) {
				return $filepath;
			}
		}

		return '';
	}

	/**
	 * Returns the located template.
	 *
	 * @since 1.0.0
	 */
	public function template(): string
	{
		if ( is_null( $this->template ) ) {
			$this->template = $this->locate();
		}

		return $this->template;
	}

	/**
	 * Displays the view.
	 *
	 * @since 1.0.0
	 */
	public function display(): void
	{
		echo $this->render();
	}

	/**
	 * Returns the view.
	 *
	 * @since 1.0.0
	 */
	public function render(): string
	{
		if ( ! $template = $this->template() ) {
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
