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

	protected $params = [];

	public function __invoke( array $params = [] ) {
		$this->params = $params;

		return $this->response( $this->view( 'index' ) );
	}

	protected function getParameter( string $name ) {
		return $this->params[ $name ] ?? null;
	}

	protected function view( $names, $data = [] ) {
		return App::resolve( Engine::class )->view( $names, $data );
	}

	protected function response( $view ) {
		$response = new Response();
		$response->setContent( $view->render() );
		return $response;
	}

	protected function forward( string $controller, array $params = [] ) {
		// return response.
	}
}
