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

use Blush\Proxies\App;
use Blush\Template\Engine;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller {

	/**
	 * Callback method when route matches request.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array  $params
	 * @return Response
	 */
	public function __invoke( array $params = [] ) {
		return $this->response( $this->view( 'index' ) );
	}

	/**
	 * Wrapper for the template engine view class.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string|array     $names
	 * @param  array|Collection $data
	 * @return View
	 */
	protected function view( $names, $data = [] ) {
		return App::resolve( Engine::class )->view( $names, $data );
	}

	/**
	 * Wrapper for sending a new response to the browser.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  View   $view
	 * @return Response
	 */
	protected function response( $view ) {
		$response = new Response();
		$response->setContent( $view->render() );
		return $response;
	}

	/**
	 * Forwards request to another controller.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  array  $params
	 * @return Response
	 */
	protected function forward( string $callback, array $params = [] ) {
		$controller = new $callback;
		return $controller( $params );
	}
}
