<?php

/**
 * Filesystem entry store test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Conformance;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Blush\Content\Entries;
use Blush\Content\Entry\EntryHydrator;
use Blush\Content\Index\IndexLocations;
use Blush\Content\Index\IndexStore;
use Blush\Content\Index\SnapshotRecords;
use Blush\Content\Record\EntryLocations;
use Blush\Content\Record\EntryPlaces;
use Blush\Content\Record\QueryCompiler;
use Blush\Content\Record\RecordLocations;
use Blush\Content\StoredEntries;
use Blush\Content\Type\ContentTypes;
use Blush\Core\Application;
use Blush\Storage\File\FileRecordStore;
use Blush\Storage\Record\RecordStores;

/**
 * The filesystem driver: content kept as files, answered from its index.
 */
#[CoversClass(StoredEntries::class)]
#[CoversClass(EntryHydrator::class)]
#[CoversClass(FileRecordStore::class)]
#[CoversClass(IndexStore::class)]
#[CoversClass(IndexLocations::class)]
#[CoversClass(SnapshotRecords::class)]
#[CoversClass(QueryCompiler::class)]
#[CoversClass(RecordLocations::class)]
#[CoversClass(EntryPlaces::class)]
final class FilesystemEntryStoreTest extends EntryStoreConformance
{
	#[Override]
	protected function stores(Application $app): RecordStores
	{
		return $app->container()->make(RecordStores::class);
	}

	#[Override]
	protected function entries(Application $app, RecordStores $stores): Entries
	{
		return $app->container()->make(Entries::class);
	}

	public function testTheIndexsLocationsAreTheRecordsOwn(): void
	{
		$container = $this->app->container();
		$index     = $container->make(EntryLocations::class);
		$records   = new RecordLocations($container->make(RecordStores::class), $container->make(ContentTypes::class));

		$this->assertInstanceOf(IndexLocations::class, $index);

		foreach (['', 'about', '_posts', 'topics', 'profiles', '__drafts'] as $folder) {
			$this->assertEqualsCanonicalizing($records->idsIn([$folder]), $index->idsIn([$folder]), "Listed in \"{$folder}\".");
		}

		foreach (['', 'about', 'about/biography', 'idea', 'spring', 'art', 'justintadlock'] as $key) {
			$this->assertEqualsCanonicalizing($records->idsWithKey($key), $index->idsWithKey($key), "With the key \"{$key}\".");
		}

		$spring = (string) $this->content->named('post', 'spring')?->id;

		$this->assertSame('_posts/2008-04-05.spring.md', $index->path($spring), 'Where the file is, for showing.');
		$this->assertSame('', $records->path($spring), 'Records keep no files.');
	}
}
