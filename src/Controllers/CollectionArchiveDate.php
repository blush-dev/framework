<?php
/**
 * Content type archive controller.
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
use Blush\Template\Tags\{DocumentTitle, Pagination};
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\{Request, Response};

class CollectionArchiveDate extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since 1.0.0
	 */
	public function __invoke( array $params = [], Request $request ): Response
	{
		$types = App::resolve( 'content.types' );

		// Get or set params.
		$path   = $basepath = $params['path'] ?? '';
		$page   = intval( $params['page'] ?? 1 );
		$second = $params['second'] ?? '';
		$minute = $params['minute'] ?? '';
		$hour   = $params['hour']   ?? '';
		$day    = $params['day']    ?? '';
		$month  = $params['month']  ?? '';
		$year   = $params['year']   ?? '';
		$type   = false;

		// Strip page from path.
		if ( Str::contains( $path, "/page/{$page}" ) ) {
			$path = $basepath = Str::beforeFirst( $path, "/page/{$page}" );
		}

		// Explodes the path into parts and loops through each. Strips
		// the last part off the original path with each iteration and
		// checks if it's the path or URI for the content type. If a
		// match is found, break out of the loop.
		foreach ( explode( '/', $path ) as $part ) {
			$path = Str::beforeLast( $path, "/{$part}" );

			// Check type by path and URI.
			if ( $type = $types->getTypeFromPath( $path ) ) {
				break;
			} elseif ( $type = $types->getTypeFromUri( $path ) ) {
				break;
			}
		}

		// If there is no type, bail early.
		if ( ! $type ) {
			return $this->forward404( $params, $request );
		}

		// Get the content type collection vars.
		$query_args = $type->collectionArgs();

		// Set required variables for the query.
		$query_args['number'] = $query_args['number'] ?? 10;
		$query_args['offset'] = $query_args['number'] * ( $page - 1 );

		if ( $second ) { $query_args['second'] = $second; }
		if ( $minute ) { $query_args['minute'] = $minute; }
		if ( $hour   ) { $query_args['hour']   = $hour;   }
		if ( $day    ) { $query_args['day']    = $day;    }
		if ( $month  ) { $query_args['month']  = $month;  }
		if ( $year   ) { $query_args['year']   = $year;   }

		// Build the title for the type of date archive.
		if ( $second && $minute && $hour && $day && $month && $year ) {
			$title = date( 'F j, Y \@ H:i:s', strtotime( "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}" ) );
		} elseif ( $minute && $hour && $day && $month && $year ) {
			$title = date( 'F j, Y \@ H:i', strtotime( "{$year}-{$month}-{$day} {$hour}:{$minute}:00" ) );
		} elseif ( $hour && $day && $month && $year ) {
			$title = date( 'F j, Y \@ H', strtotime( "{$year}-{$month}-{$day} {$hour}:00:00" ) );
		} elseif ( $day && $month && $year ) {
			$title = date( 'F j, Y', strtotime( "{$year}-{$month}-{$day}" ) );
		} elseif ( $month && $year ) {
			$title = date( 'F Y', strtotime( "{$year}-{$month}" ) );
		} elseif ( $year ) {
			$title = date( 'Y', strtotime( $year ) );
		}

		// Create a virtual entry for the archive data.
		$single = new Virtual( [
			'content' => '',
			'meta'    => [ 'title' => $title ?? 'Archives' ]
		] );

		// Query the content type collection.
		$collection = Query::make( $query_args );

		if ( $collection->all() ) {
			$type_name = $type->name();

			$doctitle = new DocumentTitle( $single->title(), [
				'page' => $page
			] );

			$pagination = new Pagination( [
				'basepath' => $basepath,
				'current'  => $page,
				'total'    => $collection->pages()
			] );

			return $this->response( $this->view( [
				"collection-{$type_name}-archive-datetime",
				'collection-archive-datetime',
				"collection-{$type_name}-archive",
				'collection-archive',
				'collection',
				'index'
			], [
				'doctitle'   => $doctitle,
				'pagination' => $pagination,
				'single'     => $single,
				'collection' => $collection
			] ) );
		}

		// If all else fails, return a 404.
		return $this->forward404( $params, $request );
	}
}
