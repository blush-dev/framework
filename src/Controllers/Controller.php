<?php
/**
 * Base controller class.
 *
 * Controllers are the bridge between the the HTTP request and what users see in
 * the browser.  This is the base class that all other controllers should use.
 * The `__invoke()` method is the only method required and should return a
 * `Response` back.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Controllers;

use Blush\App;
use Blush\Template\{Engine, View};
use Symfony\Component\HttpFoundation\Response;

abstract class Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since 1.0.0
	 */
	public function __invoke( array $params = [] ) : Response
	{
		return $this->response( $this->view( 'index' ) );
	}

	/**
	 * Wrapper for the template engine view class.
	 *
	 * @since  1.0.0
	 * @param  string|array     $names
	 * @param  array|Collection $data
	 */
	protected function view( $names, $data = [] ) : View
	{
		return App::resolve( Engine::class )->view( $names, $data );
	}

	/**
	 * Wrapper for sending a new response to the browser.
	 *
	 * @since 1.0.0
	 */
	protected function response( View $view ) : Response
	{
		return new Response( $view->render() );
	}

	/**
	 * Forwards request to another controller.
	 *
	 * @since 1.0.0
	 */
	protected function forward( string $callback, array $params = [] ) : Response
	{
		$controller = new $callback;
		return $controller( $params );
	}

	/**
	 * Forwards request to the `Error404` controller.
	 *
	 * @since 1.0.0
	 */
	protected function forward404( array $params = [] ) : Response
	{
		return $this->forward( Error404::class, $params );
	}
}
