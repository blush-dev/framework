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

use Blush\{App, Query};
use Blush\Template\Tags\DocumentTitle;
use Symfony\Component\HttpFoundation\{Request, Response};

class Error404 extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since 1.0.0
	 */
	public function __invoke( array $params = [], Request $request ): Response
	{
		$single = Query::make( [
			'path' => '_error',
			'slug' => '404'
		] )->single();

		if ( $single ) {
			$doctitle = new DocumentTitle( $single->title() );

			return $this->response( $this->view( [
				'single-error-404',
				'single-error',
				'single',
				'index'
			], [
				'doctitle'   => $doctitle,
				'pagination' => false,
				'single'     => $single,
				'collection' => false
			] ), Response::HTTP_NOT_FOUND );
		}

		return new Response( 'Nothing Found', Response::HTTP_NOT_FOUND );
	}
}
