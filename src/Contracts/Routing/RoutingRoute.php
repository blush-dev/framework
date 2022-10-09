<?php
/**
 * Route interface.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Routing;

use Blush\Controllers\Controller;
use Symfony\Component\HttpFoundation\{Request, Response};

interface RoutingRoute
{
	/**
	 * Returns the route URI.
	 *
	 * @since 1.0.0
	 */
	public function uri(): string;

	/**
	 * Assigns the route name and returns self for chaining.
	 *
	 * @since 1.0.0
	 */
	public function name( string $name ): self;

	/**
	 * Returns the route name.
	 *
	 * @since 1.0.0
	 */
	public function getName(): string;

	/**
	 * Returns the route controller.
	 *
	 * @since  1.0.0
	 */
	public function controller(): Controller|string;

	/**
	 * Invokes the route controller.
	 *
	 * @since 1.0.0
	 */
	public function callback( array $params, Request $request ): Response;

	/**
	 * Returns the route regex pattern.
	 *
	 * @since 1.0.0
	 */
	public function pattern(): string;

	/**
	 * Returns the route parameters.
	 *
	 * @since 1.0.0
	 */
	public function parameters(): array;

	/**
	 * Returns the route wheres.
	 *
	 * @since 1.0.0
	 */
	public function wheres(): array;

	/**
	 * Add custom param to regex mapping.
	 *
	 * @since  1.0.0
	 */
	public function where( string|array $name, ?string $regex = null ): void;

	/**
	 * Checks if a where param has been added.
	 *
	 * @since  1.0.0
	 */
	public function hasWhere( string $name ): bool;

	/**
	 * Adds parameters to wheres with slug-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereSlug( string|array $parameters ): void;

	/**
	 * Adds parameters to wheres with alpha-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereAlpha( string|array $parameters ): void;

	/**
	 * Adds parameters to wheres with alphanumeric-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereAlphaNumeric( string|array $parameters ): void;

	/**
	 * Adds parameters to wheres with number-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereNumber( string|array $parameters ): void;

	/**
	 * Adds parameters to wheres with year-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereYear( string|array $parameters ): void;

	/**
	 * Adds parameters to wheres with month-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereMonth( string|array $parameters ): void;

	/**
	 * Adds parameters to wheres with day-based regex pattern.
	 *
	 * @since  1.0.0
	 */
	public function whereDay( string|array $parameters ): void;
}
