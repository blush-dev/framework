<?php
/**
 * Route class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Routing;

// Contracts.
use Blush\Contracts\Makeable;

// Classes.
use Blush\Controllers\Controller;
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\{Request, Response};

class Route implements Makeable
{
	/**
	 * Route name.
	 *
	 * @since 1.0.0
	 */
	protected string $name = '';

	/**
	 * Route controller.
	 *
	 * @since  1.0.0
	 * @var    string|Controller
	 */
	protected $controller;

	/**
	 * Route regex pattern.
	 *
	 * @since 1.0.0
	 */
	protected string $pattern;

	/**
	 * Route parameters pulled from the `{param}` definitions in the URI.
	 *
	 * @since 1.0.0
	 */
	protected array $parameters = [];

	/**
	 * Stores the regex patterns for matching against the `{param}`
	 * definitions in the URI.
	 *
	 * @since 1.0.0
	 */
	protected array $wheres = [];

	/**
	 * Route methods. Only `GET` is supported.
	 *
	 * @since 1.0.0
	 */
	protected array $methods = [];

	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( protected string $uri, array $args = [] )
	{
		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$this->$key = $args[ $key ];
			}
		}

		// Assign route methods.
		$this->methods = [ 'GET' ];

		// If no route name, use the URI.
		if ( ! $this->name ) {
			$this->name = $this->uri;
		}

		// Merge default regex mapping with user-defined wheres.
		$this->wheres = array_merge( [
			'year'   => '[0-9]{4}',
			'month'  => '[0-9]{2}',
			'day'    => '[0-9]{2}',
			'hour'   => '[0-1][0-9]|2[0-3]',
			'minute' => '[0-5][0-9]',
			'second' => '[0-5][0-9]',
			'number' => '[0-9]+',
			'page'   => '[0-9]+',
			'author' => '[a-zA-Z0-9_-]+',
			'type'   => '[a-zA-Z0-9_-]+',
			'name'   => '[a-zA-Z0-9_-]+',
			'path'   => '.+',
			'*'      => '.+'
		], $this->wheres );
	}

	/**
	 * Builds the route.
	 *
	 * @since 1.0.0
	 */
	public function make(): self
	{
		// Find matches for parameter names set with `{param}`.
		$params = Str::matchAll( '/\{(.*?)\}/', $this->uri() );

		// If matches are found, loop through each `{param}` and add it
		// to the parameters array. Also, if it has not been added to
		// the wheres array, register it with the generic slug pattern,
		// which matches alphanumeric, hyphen, and underscore characters.
		foreach ( $params as $param ) {
			$this->parameters[] = $param;

			// Assign where if not set.
			if ( ! $this->hasWhere( $param ) ) {
				$this->whereSlug( $param );
			}
		}

		// Trim and escape slashes for regex.
		$regex = Str::trimSlashes( $this->uri() );
		$regex = str_replace( '/', '\/', $regex );

		// Switches the params with patterns from wheres array.
		foreach ( $this->wheres() as $where => $pattern ) {
			$regex = str_replace(
				sprintf( '{%s}', $where   ),
				sprintf( '(%s)', $pattern ),
				$regex
			);
		}

		// Build final pattern for the full route URI.
		$this->pattern = "#^{$regex}\/?$#i";

		// Return route for chaining methods.
		return $this;
	}

	/**
	 * Returns the route URI.
	 *
	 * @since 1.0.0
	 */
	public function uri(): string
	{
		return $this->uri;
	}

	/**
	 * Assigns the route name and returns self for chaining.
	 *
	 * @since 1.0.0
	 */
	public function name( string $name ): self
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * Returns the route name.
	 *
	 * @since 1.0.0
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Returns the route controller.
	 *
	 * @since  1.0.0
	 */
	public function controller(): Controller|string
	{
		return $this->controller;
	}

	/**
	 * Invokes the route controller.
	 *
	 * @since 1.0.0
	 */
	public function callback( array $params, Request $request ): Response
	{
		// Get the route controller.
		$callback = $this->controller();

		// If controller is a string, create a new instance of the class.
		if ( is_string( $callback ) ) {
			$callback = new $callback;
		}

		// Call class as a function, triggering the __invoke() method.
		return $callback( $params, $request );
	}

	/**
	 * Returns the route regex pattern.
	 *
	 * @since 1.0.0
	 */
	public function pattern(): string
	{
		return $this->pattern;
	}

	/**
	 * Returns the route parameters.
	 *
	 * @since 1.0.0
	 */
	public function parameters(): array
	{
		return $this->parameters;
	}

	/**
	 * Returns the route wheres.
	 *
	 * @since 1.0.0
	 */
	public function wheres(): array
	{
		return $this->wheres;
	}

	/**
	 * Add custom param to regex mapping.
	 *
	 * @since  1.0.0
	 */
	public function where( string|array $name, ?string $regex = null ): void
	{
		$wheres = $this->parseWhere( $name, $regex );

		foreach ( $wheres as $name => $regex ) {
			$this->wheres[ $name ] = $regex;
		}
	}

	/**
	 * Checks if a where param has been added.
	 *
	 * @since  1.0.0
	 */
	public function hasWhere( string $name ): bool
	{
		return isset( $this->wheres[ $name ] );
	}

	/**
	 * Parses where mapping.
	 *
	 * @since  1.0.0
	 */
	protected function parseWhere( string|array $name, ?string $regex = null ): array
	{
		return is_array( $name ) ? $name : [ $name => $regex ];
	}

	/**
	 * Adds parameters to wheres with slug-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereSlug( string|array $parameters ): void
	{
		$this->addPatternToParameters( $parameters, '[a-zA-Z0-9_-]+' );
	}

	/**
	 * Adds parameters to wheres with alpha-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereAlpha( string|array $parameters ): void
	{
		$this->addPatternToParameters( $parameters, '[a-zA-Z]+' );
	}

	/**
	 * Adds parameters to wheres with alphanumeric-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereAlphaNumeric( string|array $parameters ): void
	{
		$this->addPatternToParameters( $parameters, '[a-zA-Z0-9]+' );
	}

	/**
	 * Adds parameters to wheres with number-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereNumber( string|array $parameters ): void
	{
		$this->addPatternToParameters( $parameters, '[0-9]+' );
	}

	/**
	 * Adds parameters to wheres with year-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereYear( string|array $parameters ): void
	{
		$this->addPatternToParameters( $parameters, '[0-9]{4}' );
	}

	/**
	 * Adds parameters to wheres with month-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereMonth( string|array $parameters ): void
	{
		$this->addPatternToParameters( $parameters, '[0-9]{2}' );
	}

	/**
	 * Adds parameters to wheres with day-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereDay( string|array $parameters ): void
	{
		$this->addPatternToParameters( $parameters, '[0-9]{2}' );
	}

	/**
	 * Adds parameters to wheres with regex pattern.
	 *
	 * @since  1.0.0
	 */
	private function addPatternToParameters( string|array $parameters, string $pattern ): void
	{
		$wheres = [];

		foreach ( (array) $parameters as $name ) {
			$wheres[ $name ] = $pattern;
		}

		$this->where( $wheres );
	}
}
