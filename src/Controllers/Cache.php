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

use Blush\Proxies\App;
use Symfony\Component\HttpFoundation\Response;

class Cache {

	/**
	 * Callback method when route matches request.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array  $params
	 * @return Response
	 */
	public function __invoke( array $params = [] ) {

		$key = App::resolve( 'config.cache' )->get( 'secret_key' );

		$response = new Response();
		$response->setContent( 'Invalid cache purge request.' );

		if ( isset( $params['key'] ) && $key === $params['key'] ) {
			$this->recursiveRemove( App::resolve( 'path.cache' ) );
			$response->setContent( 'Cache purged.' );
		}

		return $response;
	}

	/**
	 * Recursively removes a directory.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string  $dir
	 * @return void
	 */
	protected function recursiveRemove( $dir ) {

		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = glob( "{$dir}/*" );

		foreach ( $files as $file ) {
			if ( is_dir( $file ) ) {
				$this->recursiveRemove( $file );
			} else {
				unlink( $file );
			}
		}

		rmdir( $dir );
	}
}
