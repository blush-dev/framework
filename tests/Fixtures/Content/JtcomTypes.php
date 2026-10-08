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
				'path'       => 'topics',
				'collection' => ['number' => 9999],
				'order'      => 'position',
				'llms'       => false
			],
			'era' => [
				'path'       => 'eras',
				'collection' => ['order' => 'desc', 'number' => 9999],
				'order'      => 'position',
				'llms'       => false
			],
			'literature' => [
				'path'       => 'writing',
				'collection' => ['order' => 'desc', 'number' => 9999]
			],
			'literary_form' => [
				'path'   => 'writing/forms',
				'order'  => 'position',
				'llms'   => false
			],
			'literary_genre' => [
				'path'   => 'writing/genres',
				'order'  => 'position',
				'llms'   => false
			],
			'literary_technique' => [
				'path'   => 'writing/techniques',
				'order'  => 'position',
				'llms'   => false
			]
		];
	}

	/**
	 * Returns the classify relations that file jtcom's entries under its
	 * terms (D-593), as its 1.x taxonomies did.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function relations(): array
	{
		$relation = static fn (string $from, array $listing): array => ['kind' => 'classify', 'from' => [$from], 'to' => [], 'create' => true, 'inverse' => ['page' => true, 'listing' => $listing]];
		$relations = [
			'category'           => $relation('post', ['order' => 'desc']),
			'era'                => $relation('post', ['order' => 'desc']),
			'literary_form'      => $relation('literature', ['order' => 'desc', 'perPage' => 9999]),
			'literary_genre'     => $relation('literature', ['order' => 'desc', 'perPage' => 9999]),
			'literary_technique' => $relation('literature', ['order' => 'desc', 'perPage' => 9999])
		];

		foreach ($relations as $name => $definition) {
			$relations[$name]['to'] = [$name];
		}

		// Posts and writing credit authors (D-602).
		$relations['authors'] = ['kind' => 'credit', 'from' => ['post', 'literature'], 'to' => ['profile'], 'aliases' => ['author']];

		return $relations;
	}
}
