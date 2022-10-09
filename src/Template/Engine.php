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
use Blush\{App, Message};
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
	 * Whether the top-level page view has been booted.
	 *
	 * @since 1.0.0
	 */
	protected bool $view_booted = false;

	/**
	 * Sets up the object properties.
	 *
	 * @since  1.0.0
	 */
	public function __construct( protected TemplateTags $tags ) {}

	/**
	 * Returns a template view. This should only be used for top-level views.
	 * Otherwise, an error message is dumped and the process is stalled.
	 * If including views within views, use `subview()` or one of its
	 * several descendent methods included in this class.
	 *
	 * @since  1.0.0
	 */
	public function view( array|string $paths, array|Collection $data = [] ): TemplateView
	{
		// If `view()` is called for a second time on a single page load
		// dump and die.
		if ( $this->view_booted ) {
			Message::make(
				'Cannot call <code>Engine::view()</code> twice. If this is a sub-view, try the <code>Engine::subview()</code> or <code>Engine::include()</code> method.'
			)->dd();
		}

		// Always pass the engine back to the view.
		$data['engine'] = $this;

		// Make a new template view.
		$view = App::make( 'template.view', compact( 'paths', 'data' ) );

		// Set object properties.
		$this->shared      = $view->getData();
		$this->view_booted = true;

		// Return template view.
		return $view;
	}

	/**
	 * Returns a template view. Use for getting views inside of other views.
	 * This makes sure shared data is passed down to the subview.
	 *
	 * @since  1.0.0
	 */
	public function subview( array|string $paths, array|Collection $data = [] ): TemplateView
	{
		if ( $this->shared ) {
			$data = array_merge(
				$this->shared->all(),
				$data instanceof Collection ? $data->all() : $data
			);
		}

		$data['engine'] = $this;

		return App::make( 'template.view', compact( 'paths', 'data' ) );
	}

	/**
	 * Includes a subview.
	 *
	 * @since  1.0.0
	 */
	public function include( array|string $paths, array|Collection $data = [] ): void
	{
		$subview = $this->subview( $paths, $data );

		if ( ! $subview->template() ) {
			$templates = array_map(
				fn( $name ) => "<li><code>{$name}.php</code></li>",
				(array) $paths
			);

			Message::make( sprintf(
				'<p>Notice: View templates not found:</p> <ul>%s</ul>',
				implode( "\n", $templates )
			) )->dump();
		}

		$subview->display();
	}

	/**
	 * Includes a subview only if it exists. No errors or warnings if no
	 * view template is found.
	 *
	 * @since  1.0.0
	 */
	public function includeIf( array|string $paths, array|Collection $data = [] ): void
	{
		$this->subview( $paths, $data )->display();
	}

	/**
	 * Includes a subview when `$when` is `true`.
	 *
	 * @since  1.0.0
	 */
	public function includeWhen(
		mixed $when,
		array|string $paths,
		array|Collection $data = []
	): void
	{
		if ( $when ) {
			$this->include( $paths, $data );
		}
	}

	/**
	 * Includes a subview unless `$unless` is `true`.
	 *
	 * @since  1.0.0
	 */
	public function includeUnless(
		mixed $unless,
		array|string $paths,
		array|Collection $data = []
	): void
	{
		if ( ! $unless ) {
			$this->include( $paths, $data );
		}
	}

	/**
	 * Loops through an array of items and includes a subview for each.  Use
	 * the `$var` variable to set a variable name for the item when passed
	 * to the subview.  Pass a fallback view name via `$empty` to show if
	 * the items array is empty.
	 *
	 * @since  1.0.0
	 */
	public function each(
		array|string $paths,
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
				$paths,
				$var ? [ $var => $item ] : []
			);
		}
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
