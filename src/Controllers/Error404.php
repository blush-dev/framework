<?php
/**
 * 404 controller.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Controllers;

use Blush\Proxies\App;
use Blush\Content\Query;
use Symfony\Component\HttpFoundation\Response;

class Error404 extends Controller {

	/**
	 * Callback method when route matches request.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array  $params
	 * @return Response
	 */
	public function __invoke( array $params = [] ) {
		http_response_code( 404 );

		$entries = new Query( '_error', [ 'slug' => '404' ] );

		if ( $entries->all() ) {
			return $this->response(
				$this->view( [
					'error-404',
					'error'
				], [
					'query'   => $query->first(),
					'title'   => $query->first()->title(),
					'page'    => 1,
					'entries' => $entries
				] )
			);
		}

		return new Response( 'Nothing Found' );
	}
}
