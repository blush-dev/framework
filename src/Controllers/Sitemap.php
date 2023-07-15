<?php
/**
 * Sitemap controller.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Controllers;

use Blush\Core\Proxies\{App, Config, Query};
use Blush\Content\Entry\Virtual;
use Blush\Template\Tag\{DocumentTitle};
use Blush\Tools\{Collection, Str};
use Symfony\Component\HttpFoundation\{Request, Response};

class Sitemap extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since  1.0.0
	 */
	public function __invoke( array $params, Request $request ): Response
	{
		// Get all content types.
		$types = App::get( 'content.types' );

		$type = $params['type'] ?? '';

		if ( ! $type ) {
			return $this->forward404( $params, $request );
		}

		// Query the sitemap's index file.
		$single = new Virtual( [
			'content' => '',
			'meta'    => [ 'title' => 'Sitemap' ]
		] );

		// @todo collect content from all post types.
		$collection = Query::make( [
			'type'      => $types->get( $type )->name(),
			'number'    => 0,
			'orderby'   => 'published',
			'order'     => 'desc',
			'nocontent' => true
		] );

		if ( $single && $collection->hasEntries() ) {

			// Get the feed view.
			return $this->response( $this->view(
				[
					'sitemap'
				], [
					'doctitle'   => new DocumentTitle(),
					'pagination' => false,
					'single'     => $single,
					'collection' => $collection,
				]
			), Response::HTTP_OK, [ 'content-type' => 'text/xml' ] );
		}

		// If all else fails, return a 404.
		return $this->forward404( $params, $request );
	}
}
