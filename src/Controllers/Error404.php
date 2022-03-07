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

class Error404 extends Controller {

	public function __invoke( array $params = [] ) {
		$this->params = $params;

		http_response_code( 404 );

		$entries = new Query( '_error', [ 'slug' => '404' ] );

		$all   = $entries->all();
		$entry = array_shift( $all );

		return $this->response(
			$this->view( 'error-404', [], [
				'title'   => $entry ? $entry->title() : 'Not Found',
				'query'   => $entry ? $entry : false,
				'page'    => 1,
				'entries' => $entries
			] )
		);
	}
}
