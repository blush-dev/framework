<?php

/**
 * Array entry store test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Conformance;

use LogicException;
use Override;
use Psr\Clock\ClockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Blush\Content\Entries;
use Blush\Content\Entry\EntryHydrator;
use Blush\Content\Record\EntryPlaces;
use Blush\Content\Record\EntryTable;
use Blush\Content\Record\QueryCompiler;
use Blush\Content\Record\RecordLocations;
use Blush\Content\StoredEntries;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Core\Application;
use Blush\Field\FieldContext;
use Blush\Markdown\MarkdownParser;
use Blush\Storage\Record\ArrayEvaluator;
use Blush\Storage\Record\ArrayRecordStore;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Ref;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageConfig;
use Blush\Storage\StorageDriverFactory;
use Blush\Storage\StorageDriverRegistry;
use Blush\Storage\StorageResolver;
use Blush\Tests\Fixtures\Storage\MemoryContent;

/**
 * A store that keeps no files: the site's records copied, in the order
 * they were made in, into an `ArrayRecordStore` that a storage driver
 * gives for the content area, with `Entries` built over it and locations
 * read from records alone. The target a database driver answers.
 */
#[CoversClass(StoredEntries::class)]
#[CoversClass(EntryHydrator::class)]
#[CoversClass(ArrayRecordStore::class)]
#[CoversClass(ArrayEvaluator::class)]
#[CoversClass(QueryCompiler::class)]
#[CoversClass(RecordLocations::class)]
#[CoversClass(EntryPlaces::class)]
final class ArrayEntryStoreTest extends EntryStoreConformance
{
	#[Override]
	protected function stores(Application $app): RecordStores
	{
		$container = $app->container();
		$files     = $container->make(RecordStores::class);
		$store     = new ArrayRecordStore();

		foreach ([EntryTable::table(), Ref::table(StorageArea::Content)] as $table) {
			foreach ($files->store($table)->select($table, new RecordQuery())->records as $record) {
				$store->save($table, $record->withVersion(null));
			}
		}

		$registry = new StorageDriverRegistry();
		$registry->register('memory', MemoryContent::class);
		$container->instance(ArrayRecordStore::class, $store);

		return new RecordStores(new StorageResolver(
			new StorageConfig(areas: [StorageArea::Content->value => 'memory']),
			new StorageDriverFactory($registry, $container),
			$container
		));
	}

	#[Override]
	protected function entries(Application $app, RecordStores $stores): Entries
	{
		$container = $app->container();
		$types     = $container->make(ContentTypes::class);
		$config    = $container->make(AppConfig::class);
		$clock     = $container->make(ClockInterface::class);

		return new StoredEntries(
			new EntryHydrator($types, $container->make(FieldContext::class), $stores, $container->make(MarkdownParser::class), $clock, $config),
			$types,
			$config,
			$clock,
			$container->make(QueryCompiler::class),
			$stores,
			new RecordLocations($stores, $types),
			static fn (): never => throw new LogicException('This store has no writer.')
		);
	}

	#[Override]
	protected function writes(): bool
	{
		return false;
	}
}
