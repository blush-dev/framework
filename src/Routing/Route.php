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

use Blush\Controllers\Controller;
use Symfony\Component\HttpFoundation\{Request, Response};

class Route
{
	/**
	 * Route URI.
	 *
	 * @since 1.0.0
	 */
	protected string $uri;

	/**
	 * Route controller.
	 *
	 * @since  1.0.0
	 * @var    string|Controller
	 */
	protected $controller;

	/**
	 * Route regex.
	 *
	 * @todo  Rename to `$pattern`.
	 * @since 1.0.0
	 */
	protected string $regex;

	/**
	 * Route parameters.
	 *
	 * @since 1.0.0
	 */
	protected array $parameters = [];

	/**
	 * Route methods. Only `GET` is supported.
	 *
	 * @since 1.0.0
	 */
	protected array $methods = [];

	/**
	 * Parameter to regex mapping.
	 *
	 * @since 1.0.0
	 */
	protected array $wheres = [];

	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( string $uri, array $args = [] )
	{
		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$this->$key = $args[ $key ];
			}
		}

		$this->methods = [ 'get' ];
		$this->uri     = $uri;
	}

	/**
	 * Builds the route.
	 *
	 * @since 1.0.0
	 */
	public function make() : self
	{
		// Find matches for parameter names set with `{param}`.
		// Stores them in `$this->parameters`.
		preg_match_all( '/\{(.*?)\}/', $this->uri(), $matches );

		// @todo - $matches[0] should be param name `$params['path']`.
		if ( $matches && isset( $matches[1] ) ) {
			foreach ( $matches[1] as $match ) {
				$this->parameters[] = $match;
			}
		}

		// Trim and escape slashes.
		$regex = ltrim( $this->uri(), '/' );
		$regex = str_replace( '/', '\/', $regex );

		// Gets the regex map for specific vars.
		$map = $this->regexMap();

		// Switches the vars to placeholders temporarily to keep the
		// following `preg_replace()` from breaking it.
		foreach ( $map as $var => $exp ) {
			$regex = str_replace( $var, str_replace(
				[ '{', '}' ],
				[ '@blushopen@', '@blushclose@' ],
				$var
			), $regex );
		}

		// Use general selector for unknown variables.
		$regex = preg_replace( '/\{.*?\}/', '(.+)', $regex );

		// Replace placeholders with original vars.
		$regex = str_replace(
			[ '@blushopen@', '@blushclose@' ],
			[ '{', '}' ],
			$regex
		);

		// Map known vars to their regex patterns.
		foreach ( $map as $var => $exp ) {
			$regex = str_replace( $var, $exp, $regex );
		}

		// Build final regex pattern for the full route URI.
		$this->regex = "#{$regex}#i";

		// Return route for chaining methods.
		return $this;
	}

	/**
	 * Returns the route URI.
	 *
	 * @since 1.0.0
	 */
	public function uri() : string
	{
		return $this->uri;
	}

	/**
	 * Returns the route controller.
	 *
	 * @since  1.0.0
	 * @return string|Controller
	 */
	public function controller()
	{
		return $this->controller;
	}

	/**
	 * Invokes the route controller.
	 *
	 * @since 1.0.0
	 */
	public function callback( array $params = [], Request $request ) : Response
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
	 * Returns the route regex.
	 *
	 * @since 1.0.0
	 */
	public function regex() : string
	{
		return $this->regex;
	}

	/**
	 * Returns an array of parameter to regex mappings.
	 *
	 * @since 1.0.0
	 */
	protected function regexMap() : array
	{
		$_wheres = [];

		foreach ( $this->wheres() as $var => $regex ) {
			$_wheres[ '{' . $var . '}' ] = $regex;
		}

		return array_merge( [
			'{year}'   => '([0-9]{4})',
			'{month}'  => '([0-9]{2})',
			'{day}'    => '([0-9]{2})',
			'{number}' => '([0-9]+)',
			'{page}'   => '([0-9]+)',
			'{name}'   => '([a-zA-Z0-9_-]+)',
			'{path}'   => '(.+)'
		], $_wheres );
	}

	/**
	 * Returns the route parameters.
	 *
	 * @since 1.0.0
	 */
	public function parameters() : array
	{
		return $this->parameters;
	}

	/**
	 * Returns the route wheres.
	 *
	 * @since 1.0.0
	 */
	public function wheres() : array
	{
		return $this->wheres;
	}

	/**
	 * Add custom param to regex mapping.
	 *
	 * @since  1.0.0
	 * @param  string|array  $name
	 * @param  string|null   $regex
	 */
	public function where( $name, $regex = null ) : void
	{
		$wheres = $this->parseWhere( $name, $regex );

		foreach ( $wheres as $name => $regex ) {
			$this->wheres[ $name ] = $regex;
		}
	}

	/**
	 * Parses where mapping.
	 *
	 * @since  1.0.0
	 * @param  string|array  $name
	 * @param  string|null   $regex
	 */
	protected function parseWhere( $name, $regex ) : array
	{
		return is_array( $name ) ? $name : [ $name => $regex ];
	}

	public function whereAlpha( $parameters )
	{
		$this->addRegexToParameters( $parameters, '([a-zA-Z]+)' );
	}

	public function whereAlphaNumeric( $parameters )
	{
		$this->addRegexToParameters( $parameters, '([a-zA-Z0-9]+)' );
	}

	public function whereNumber( $parameters )
	{
		$this->addRegexToParameters( $parameters, '([0-9]+)' );
	}

	public function whereYear( $parameters )
	{
		$this->addRegexToParameters( $parameters, '([0-9]{4})' );
	}

	private function addRegexToParameters( $parameters, $regex )
	{
		$wheres = [];

		foreach ( (array) $parameters as $name ) {
			$wheres[ $name ] = $regex;
		}

		$this->where( $wheres );
	}
}
