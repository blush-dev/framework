<?php

/**
 * Admin on SQLite test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Blush\Clock\SystemClock;
use Blush\Content\Type\DefinitionTables;
use Blush\Storage\Record\KeyedTable;
use Blush\Storage\Sql\SqliteConnection;
use Blush\Storage\Sql\SqliteRecordStore;
use Blush\Storage\StorageConfig;

/**
 * The admin on a site that keeps everything in SQLite (D-662, D-667):
 * entries written, changed, trashed, and deleted through its API, every
 * screen's API answering, and Site Health checking what a database has,
 * with nothing that only files have in the way.
 */
#[CoversNothing]
final class AdminOnSqliteTest extends TestCase
{
	use BootsAdmin;

	private string $token = '';

	protected function setUp(): void
	{
		if (! SqliteConnection::available()) {
			$this->markTestSkipped('PHP has no SQLite with JSON functions.');
		}

		// Types and relations are tables, kept in the database too (D-672).
		$store     = SqliteRecordStore::forSite(new StorageConfig('sqlite'), $this->temporaryDirectory());
		$types     = new KeyedTable($store, DefinitionTables::typesTable(), new SystemClock());
		$relations = new KeyedTable($store, DefinitionTables::relationsTable(), new SystemClock());
		$types->save('post', ['date_archives' => true, 'routing' => ['prefix' => 'archives']]);
		$types->save('category', ['path' => 'topics']);
		$relations->save('category', ['kind' => 'classify', 'from' => ['post'], 'to' => ['category']]);
		$relations->save('authors', ['kind' => 'credit', 'from' => ['post'], 'to' => ['profile']]);

		$this->boot(roles: ['owner'], environment: ['STORAGE_DRIVER' => 'sqlite'], ids: false);
		$this->login();

		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? null;
		$this->assertIsString($token);
		$this->token = $token;
	}

	/**
	 * Sends a request with the CSRF token.
	 *
	 * @param array<string, mixed> $data
	 */
	private function call(string $method, string $path, array $data = []): \Psr\Http\Message\ResponseInterface
	{
		return $this->send($method, $path, $data === [] ? '' : (json_encode($data) ?: ''), ['X-CSRF-Token' => $this->token]);
	}

	/**
	 * Creates an entry through the API and returns its id.
	 *
	 * @param array<string, mixed> $data
	 */
	private function create(array $data): string
	{
		$response = $this->call('POST', '/entries', $data);
		$id       = self::json($response)['id'] ?? null;

		$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
		$this->assertIsString($id);

		return $id;
	}

	public function testWritesEntriesThroughTheApi(): void
	{
		$this->create(['type' => 'category', 'title' => 'Art']);
		$this->create(['type' => 'profile', 'title' => 'Jane']);

		$id    = $this->create(['type' => 'post', 'title' => 'Hello', 'set' => ['category' => ['art'], 'authors' => ['jane']]]);
		$entry = self::json($this->send('GET', "/entries/{$id}"));

		$this->assertSame('Hello', $entry['title'] ?? null);
		$this->assertIsString($entry['revision'] ?? null);

		$changed = $this->call('PATCH', "/entries/{$id}", ['revision' => $entry['revision'], 'set' => ['title' => 'Hello Again'], 'body' => "New words.\n"]);

		$this->assertSame(200, $changed->getStatusCode(), (string) $changed->getBody());
		$this->assertSame('Hello Again', self::json($this->send('GET', "/entries/{$id}"))['title'] ?? null);
		$this->assertSame(409, $this->call('PATCH', "/entries/{$id}", ['revision' => $entry['revision'], 'set' => ['title' => 'Stale']])->getStatusCode(), 'Checked by version.');

		$listed = self::json($this->send('GET', '/entries?type=post'));

		$this->assertSame(['Hello Again'], array_column(is_array($listed['entries'] ?? null) ? $listed['entries'] : [], 'title'));

		$revision = self::json($this->send('GET', "/entries/{$id}"))['revision'] ?? null;

		$this->assertIsString($revision);

		$trashed  = $this->call('DELETE', "/entries/{$id}?revision={$revision}");

		$this->assertSame(200, $trashed->getStatusCode(), (string) $trashed->getBody());
		$this->assertSame('trash', self::json($this->send('GET', "/entries/{$id}"))['status'] ?? null);
	}

	public function testEditsTypesAndRelationsInTheDatabase(): void
	{
		$changed = $this->call('PATCH', '/types/post', ['set' => ['description' => 'Writing.']]);

		$this->assertSame(200, $changed->getStatusCode(), (string) $changed->getBody());

		$created = $this->call('POST', '/relations', ['name' => 'related', 'kind' => 'reference', 'from' => ['post'], 'to' => ['post']]);

		$this->assertSame(201, $created->getStatusCode(), (string) $created->getBody());

		$store = SqliteRecordStore::forSite(new StorageConfig('sqlite'), $this->temporaryDirectory());

		$this->assertSame('Writing.', new KeyedTable($store, DefinitionTables::typesTable(), new SystemClock())->find('post')['description'] ?? null, 'Kept as the type\'s record (D-672).');
		$this->assertTrue(new KeyedTable($store, DefinitionTables::relationsTable(), new SystemClock())->has('related'));
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/types/post.json');
	}

	public function testEveryScreensApiAnswers(): void
	{
		$id = $this->create(['type' => 'post', 'title' => 'Hello']);

		$paths = [
			'/dashboard', '/counts', '/actions', '/jobs', '/logs', '/types', '/types/post', '/relations', '/directives',
			'/fields/types', '/fields/sets', '/icons', '/media', '/references/post', '/entries', '/entries?type=post',
			'/entries/new?type=post', "/entries/{$id}", "/entries/{$id}/referrers", '/health', '/health/site', '/roles',
			'/accounts', '/profiles', '/themes', '/plugins', '/icon-packs', '/icon-packs/core', '/settings/general'
		];

		foreach ($paths as $path) {
			$response = $this->send('GET', $path);

			$this->assertLessThan(500, $response->getStatusCode(), "{$path}: " . substr((string) $response->getBody(), 0, 300));
		}
	}

	public function testSiteHealthChecksWithoutContentFiles(): void
	{
		$this->create(['type' => 'post', 'title' => 'Hello']);

		$answer = self::json($this->call('POST', '/health'));
		$job    = $answer['job'] ?? null;

		if (is_string($job)) {
			do {
				$run = self::json($this->call('POST', "/jobs/{$job}/run"))['job'] ?? null;

				$this->assertIsArray($run);
			} while (in_array($run['status'] ?? null, ['queued', 'running'], true));

			$this->assertSame('done', $run['status'] ?? null, is_string($run['message'] ?? null) ? $run['message'] : '');
		}

		$site  = self::json($this->send('GET', '/health/site'));
		$facts = array_column(is_array($site['site'] ?? null) ? $site['site'] : [], 'value', 'key');

		$this->assertSame('SQLite', $facts['storage'] ?? null);
		$this->assertSame('In the database (user/site.sqlite)', $facts['content'] ?? null);
	}
}
