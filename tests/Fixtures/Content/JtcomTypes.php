<?php

/**
 * jtcom's 1.x content types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Content;

/**
 * jtcom's `config/content.php` from 1.x, verbatim apart from the routing
 * controller it didn't use. `ContentConfig::fromArray()` must accept it
 * unchanged (D-078).
 */
final class JtcomTypes
{
	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions(): array
	{
		return [
			'post' => [
				'path'          => '_posts',
				'collection'    => ['order' => 'desc'],
				'date_archives' => true,
				'feed'          => ['taxonomy' => 'category'],
				'routing'       => [
					'prefix' => 'archives',
					'paths'  => ['single' => '{year}/{month}/{day}/{name}']
				]
			],
			'category' => [
				'path'            => 'topics',
				'collection'      => ['number' => 9999],
				'taxonomy'        => true,
				'term_collect'    => 'post',
				'term_collection' => ['order' => 'desc']
			],
			'era' => [
				'path'            => 'eras',
				'collection'      => ['order' => 'desc', 'number' => 9999],
				'taxonomy'        => true,
				'term_collect'    => 'post',
				'term_collection' => ['order' => 'desc']
			],
			'literature' => [
				'path'       => 'writing',
				'collection' => ['order' => 'desc', 'number' => 9999]
			],
			'literary_form' => [
				'path'            => 'writing/forms',
				'taxonomy'        => true,
				'term_collect'    => 'literature',
				'term_collection' => ['order' => 'desc', 'number' => 9999]
			],
			'literary_genre' => [
				'path'            => 'writing/genres',
				'taxonomy'        => true,
				'term_collect'    => 'literature',
				'term_collection' => ['order' => 'desc', 'number' => 9999]
			],
			'literary_technique' => [
				'path'            => 'writing/techniques',
				'taxonomy'        => true,
				'term_collect'    => 'literature',
				'term_collection' => ['order' => 'desc', 'number' => 9999]
			]
		];
	}
}
