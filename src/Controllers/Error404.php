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
use Blush\Content\Entry\Virtual;
use Blush\Template\Hierarchy;
use Blush\Template\Tag\DocumentTitle;
use Symfony\Component\HttpFoundation\{Request, Response};

class Error404 extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since 1.0.0
	 */
	public function __invoke( array $params, Request $request ): Response
	{
		$single = Query::make( [
			'path' => '_error',
			'slug' => '404'
		] )->single();

		// Create a virtual entry if no user-provided entry.
		if ( ! $single ) {
			$single = new Virtual( [
				'content' => '<p>Sorry, nothing was found here.</p>',
				'meta'    => [ 'title' => 'Nothing Found' ]
			] );
		}

		return $this->response( $this->view(
			Hierarchy::error404(),
			[
				'doctitle'   => new DocumentTitle( $single->title() ),
				'pagination' => false,
				'single'     => $single,
				'collection' => false
			]
		), Response::HTTP_NOT_FOUND );
	}
}
