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

class Home extends Controller {

	public function __invoke( array $params = [] ) {
		$this->params = $params;

		$query = new Query( '', [ 'slug' => 'index' ] );

		$all   = $query->all();
		$entry = array_shift( $all );

		return $this->response(
			$this->view( 'home', [], [
				'title'   => config( 'app', 'title' ),
				'query'   => $entry ? $entry : false,
				'page'    => 1,
				'entries' => $query
			] )
		);
	}
}
