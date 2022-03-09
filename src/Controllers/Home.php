<?php
/**
 * Home controller.
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

class Home extends Controller {

	/**
	 * Callback method when route matches request.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array  $params
	 * @return Response
	 */
	public function __invoke( array $params = [] ) {

		$entries = new Query( '', [ 'slug' => 'index' ] );

		if ( $entries->all() ) {
			return $this->response(
				$this->view( 'home', [
					'query'   => $entries->first(),
					'title'   => $entries->first()->title(),
					'page'    => 1,
					'entries' => $entries
				] )
			);
		}

		return new Response( 'No user/content/index.md file found.' );
	}
}
