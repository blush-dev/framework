<?php

/**
 * Content config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Content\Type\BuiltInType;

/**
 * The site's content settings, from `config/content.php`:
 *
 *     return new ContentConfig(home: 'post');
 *
 * Types and relations aren't config (D-617): they come from core,
 * plugins (`ContentTypeSource`, `RelationSource`), and data in
 * `user/data/types` and `user/data/relations`, which the admin writes,
 * unless `dataTypes` is off. With `dataTypeUrls` off, data types can't
 * set their own URLs (D-042).
 *
 * With `autoIndex` on (the default), development requests refresh the
 * content index incrementally on first use; elsewhere, `content:index`
 * (or publishing) refreshes it. With `sqliteIndex` on (the default), the
 * index's rows are also kept in SQLite when PHP can, and queries read
 * them there (D-659); off, they're read from the PHP index alone.
 */
final readonly class ContentConfig implements Config
{
	/**
	 * @param  ?string      $home         A type whose collection is the homepage.
	 * @param  bool         $dataTypes    Whether `user/data/types` and `user/data/relations` are read.
	 * @param  bool         $dataTypeUrls Whether data types may set `urls`.
	 * @param  list<string> $disabled     Built-in types to leave out.
	 * @param  bool         $autoIndex    Whether development requests refresh the index.
	 * @param  bool         $sqliteIndex  Whether the index's rows are kept in SQLite when PHP can.
	 * @throws InvalidConfig
	 */
	public function __construct(
		public ?string $home = null,
		public bool $dataTypes = true,
		public bool $dataTypeUrls = true,
		public array $disabled = [],
		public bool $autoIndex = true,
		public bool $sqliteIndex = true
	) {
		foreach ($disabled as $name) {
			$builtIn = BuiltInType::tryFrom($name);

			if ($builtIn === null || ! $builtIn->canDisable()) {
				throw new InvalidConfig(sprintf('ContentConfig can\'t disable "%s"; only these built-in types can be: %s.', $name, implode(', ', array_column(
					array_filter(BuiltInType::cases(), static fn (BuiltInType $type): bool => $type->canDisable()),
					'value'
				))));
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['home', 'dataTypes', 'dataTypeUrls', 'disabled', 'autoIndex', 'sqliteIndex']);

		return new static(
			home: $values->nullableString('home'),
			dataTypes: $values->bool('dataTypes', true),
			dataTypeUrls: $values->bool('dataTypeUrls', true),
			disabled: $values->stringList('disabled'),
			autoIndex: $values->bool('autoIndex', true),
			sqliteIndex: $values->bool('sqliteIndex', true)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'home'         => $this->home,
			'dataTypes'    => $this->dataTypes,
			'dataTypeUrls' => $this->dataTypeUrls,
			'disabled'     => $this->disabled,
			'autoIndex'    => $this->autoIndex,
			'sqliteIndex'  => $this->sqliteIndex
		];
	}
}
