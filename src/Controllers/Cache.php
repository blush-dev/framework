<?php
/**
 * Cache controller.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Controllers;

use Blush\App;
use Blush\Content\Entry\Virtual;
use Blush\Template\Tags\DocumentTitle;
use Symfony\Component\HttpFoundation\{Request, Response};

class Cache extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array  $params
	 * @return Response
	 */
	public function __invoke( array $params = [], Request $request ): Response
	{
		$key = App::get( 'config.cache' )->get( 'secret_key' );

		$title = 'Cache Purge Failure';
		$content = '<p>Invalid cache purge request.<p>';

		if ( isset( $params['key'] ) && $key === $params['key'] ) {
			$this->recursiveRemove( App::get( 'path.cache' ) );
			$title = 'Cache Purged';
			$content = '<p>Cached content and data successfully purged.</p>';
		}

		// Create a virtual entry for the content.
		$single = new Virtual( [
			'content' => $content,
			'meta'    => [ 'title' => $title ]
		] );

		$doctitle = new DocumentTitle( $single->title() );

		return $this->response( $this->view( [
			"single-page-cache",
			'single-page',
			'single',
			'index'
		], [
			'doctitle'   => $doctitle,
			'pagination' => false,
			'single'     => $single,
			'collection' => false
		] ) );
	}

	/**
	 * Recursively removes a directory.
	 *
	 * @since 1.0.0
	 */
	private function recursiveRemove( string $path ): void
	{
		if ( ! is_dir( $path ) ) {
			return;
		}

		$filepaths = glob( "{$path}/*" );

		foreach ( $filepaths as $filepath ) {
			is_dir( $filepath )
			    ? $this->recursiveRemove( $filepath )
			    : unlink( $filepath );
		}

		// Don't remove the cache directory itself.
		if ( App::get( 'path.cache' ) !== $path ) {
			rmdir( $path );
		}
	}
}
