<?php
/**
 * Template engine.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Template;

// Abstracts.
use Blush\Contracts\Template\{
	TemplateEngine,
	TemplateTag,
	TemplateTags,
	TemplateView
};

// Concretes.
use Blush\Core\Proxies\{App, Message};
use Blush\Tools\Collection;

class Engine implements TemplateEngine
{
	/**
	 * Houses shared data to pass down to subviews.
	 *
	 * @since 1.0.0
	 */
	protected Collection $shared;

	/**
	 * Sets up the object properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct( protected TemplateTags $tags ) {
		$this->shared = new Collection();
	}

	/**
	 * Returns a template view. This should only be used for top-level views.
	 * Otherwise, an error message is dumped and the process is stalled.
	 * If including views within views, use `subview()` or one of its
	 * several descendent methods included in this class.
	 *
	 * @since 1.0.0
	 */
	public function view( array|string $views, array|Collection $data = [] ): TemplateView
	{
		// If an array is passed in, call `first()`.
		if ( is_array( $views ) ) {
			return $this->first( $views, $data );
		}

		// Assign the view name, which should be a string at this point.
		$name = $views;

		// @todo possible to assign this to $shared?
		$data = array_merge(
			$this->shared->all(),
			$data instanceof Collection ? $data->all() : $data
		);

		// Always pass the engine back to the view.
		$data['engine'] = $this;

		$this->shared = new Collection( $data );

		// Make a new template view.
		$view = App::make( 'template.view', [
			'name' => $views,
			'data' => $data
		] );

		// Return template view.
		return $view;
	}

	/**
	 * Checks if a view template exists.
	 *
	 * @since 1.0.0
	 */
	public function exists( string $name ): bool
	{
		$filename = str_replace( '.', '/', $name );

		return file_exists( view_path( "{$filename}.php" ) );
	}

	/**
	 * Returns the first found view.
	 *
	 * @since 1.0.0
	 */
	public function first( array $views, array|Collection $data = [] ): TemplateView
	{
		foreach ( $views as $view ) {
			if ( $this->exists( $view ) ) {
				return $this->view( $view, $data );
			}
		}

		Message::make( sprintf(
			'<p>Notice: View templates not found:</p> <ul>%s</ul>',
			implode( "\n", array_map(
				fn( $name ) => "<li><code>{$name}.php</code></li>",
				$views
			) )
		) )->dd();
	}

	/**
	 * Returns any found view.
	 *
	 * @since 1.0.0
	 */
	public function any( array $views, array|Collection $data = [] ): TemplateView|false
	{
		foreach ( (array) $views as $name ) {
			if ( $this->exists( $name ) ) {
				return $this->view( $name, $data );
			}
		}

		return false;
	}

	/**
	 * Includes a view.
	 *
	 * @since 1.0.0
	 */
	public function include( array|string $views, array|Collection $data = [] ): void
	{
		$this->first( (array) $views, $data )->display();
	}

	/**
	 * Includes a view only if it exists. No errors or warnings if no view
	 * template is found.
	 *
	 * @since  1.0.0
	 */
	public function includeIf( array|string $views, array|Collection $data = [] ): void
	{
		if ( $view = $this->any( (array) $views, $data ) ) {
			$view->display();
		}
	}

	/**
	 * Includes a view when `$when` is `true`.
	 *
	 * @since  1.0.0
	 */
	public function includeWhen(
		mixed $when,
		array|string $views,
		array|Collection $data = []
	): void
	{
		if ( $when ) {
			$this->include( $views, $data );
		}
	}

	/**
	 * Includes a view unless `$unless` is `true`.
	 *
	 * @since  1.0.0
	 */
	public function includeUnless(
		mixed $unless,
		array|string $views,
		array|Collection $data = []
	): void
	{
		if ( ! $unless ) {
			$this->include( $views, $data );
		}
	}

	/**
	 * Loops through an array of items and includes a view for each.  Use
	 * the `$var` variable to set a variable name for the item when passed
	 * to the view.  Pass a fallback view name via `$empty` to show if
	 * the items array is empty.
	 *
	 * @since  1.0.0
	 */
	public function each(
		array|string $views,
		iterable $items = [],
		string $var = '',
		array|string $empty = []
	): void
	{
		if ( ! $items && $empty ) {
			$this->include( $empty );
			return;
		}

		foreach ( $items as $item ) {
			$this->include(
				$views,
				$var ? [ $var => $item ] : []
			);
		}
	}

	/**
	 * Returns a template view. Use for getting views inside of other views.
	 * This makes sure shared data is passed down to the subview.
	 *
	 * @since  1.0.0
	 * @deprecated 1.0.0
	 */
	public function subview( array|string $views, array|Collection $data = [] ): TemplateView
	{
		return $this->view( $views, $data );
	}

	/**
	 * Returns a template tag object or null when it doesn't exist.
	 *
	 * @since  1.0.0
	 */
	public function tag( string $name, mixed ...$args ): ?TemplateTag
	{
		return $this->tags->callback( $name, $this->shared, $args );
	}

	/**
	 * Allows registered template tags to be used as methods.
	 *
	 * @since  1.0.0
	 */
	public function __call( string $name, array $arguments ): mixed
	{
		return $this->tag( $name, ...$arguments );
	}
}
