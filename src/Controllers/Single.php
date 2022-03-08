<?php
/**
 * Single controller.
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

class Single extends Controller {

	public function __invoke( array $params = [] ) {
		$this->params = $params;
		$types = App::resolve( 'content/types' );

		$path       = $this->getParameter( 'path' );
		$slug       = $this->getParameter( 'name' );
		$slug_parts = explode( '/', $slug );

		// Checks if the path (w/o slug) is a taxonomy. If so, this is a
		// taxonomy term archive.
		$built = $slug;
		foreach ( array_reverse( $slug_parts ) as $part ) {
			if ( $type = $types->getTypeFromPath( $built ) ) {
				if ( $type->isTaxonomy() ) {
					$controller = new TaxonomyTerm();
					$params['taxonomy'] = $type->type();
					return $controller( $params );
				}
			}
			$built = str_replace( "/{$part}", '', $built );
		}

		// Split the slug into multiple parts.
		if ( 1 < count( $slug_parts ) ) {
			$slug = urldecode( array_pop( $slug_parts ) );
			$path = trim( str_replace( $slug, '', $path ), '/' );
		}

		// Look for an `path/index.md` file.
		$entries = new Query(
			$this->getParameter( 'path' ),
			[ 'slug' => 'index' ]
		);

		if ( $entries->all() ) {
			$all   = $entries->all();
			$entry = array_shift( $all );
			$type = $types->getTypeFromPath( $this->getParameter( 'path' ) );

			$view_slugs = [
				"single-{$slug}",
				sprintf(
					'single-%s',
					$type ? sanitize_with_dashes( $type->type() ) : 'content'
				),
				'single'
			];

			return $this->response(
				$this->view( $view_slugs, [
					'title'   => $entry->title(),
					'query'   => $entry,
					'page'    => 1,
					'entries' => $entries
				] )
			);
		}

		// Look for a `path/{$slug}.md` file.
		$entries = new Query( $path, [ 'slug' => $slug ] );

		if ( $entries->all() ) {
			$all   = $entries->all();
			$entry = array_shift( $all );

			$view_slugs = [
				"single-{$slug}",
				sprintf(
					'single-%s',
					$type ? sanitize_with_dashes( $type->type() ) : 'content'
				),
				'single'
			];

			return $this->response(
				$this->view( $view_slugs, [
					'title'   => $entry->title(),
					'query'   => $entry,
					'page'    => 1,
					'entries' => $entries
				] )
			);
		}

		// If all else fails, return a 404.
		$controller = new Error404();
		return $controller( $params );
	}
}
