<?php

/**
 * API writes tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Activity\ActivityFilter;
use Blush\Activity\ActivityLog;
use Blush\Activity\Via;
use Blush\Api\ApiError;
use Blush\Api\ApiWrites;
use Blush\Api\Http\EntryWritesController;
use Blush\Api\Http\MediaWritesController;
use Blush\Auth\Accounts;
use Blush\Auth\Tokens;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Http\UploadedFile;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(ApiWrites::class)]
#[CoversClass(ApiError::class)]
#[CoversClass(EntryWritesController::class)]
#[CoversClass(MediaWritesController::class)]
final class ApiWritesTest extends TestCase
{
	use BuildsContentSite;

	/**
	 * A 1×1 PNG.
	 */
	private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	private Application $app;

	private function boot(string $config = 'new Blush\\Api\\ApiConfig(enabled: true)'): void
	{
		$this->standardContent();
		$this->entry('_post/2021-01-01.mine.md', "title: Mine\npublished: 2021-01-01\nstatus: draft\nauthor: justintadlock", 'First line.');
		$this->writeTemporaryFile('config/cache.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Cache\\CacheConfig(enabled: false);\n");
		$this->writeTemporaryFile('config/api.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn {$config};\n");

		$this->app = $this->site();
	}

	/**
	 * Makes a token-only account with roles, linked to a profile when one
	 * is named, and a token for it, and returns the token's text.
	 *
	 * @param list<string>  $roles
	 * @param ?list<string> $capabilities
	 */
	private function token(string $username, array $roles, ?string $profile = null, ?array $capabilities = null): string
	{
		$accounts = $this->app->container()->make(Accounts::class);
		$account  = $accounts->createTokenOnly($username, $roles);

		if ($profile !== null) {
			$account = $accounts->setProfile($account, self::idFor("_profile/{$profile}.md"));
		}

		return $this->app->container()->make(Tokens::class)->make($account, 'Script', $capabilities)[1];
	}

	/**
	 * @param array<string, mixed>|null $body
	 */
	private function send(string $method, string $uri, ?string $token, ?array $body = null, ?string $revision = null): ResponseInterface
	{
		return $this->handle(Request::create($uri, $method, [
			'Content-Type' => 'application/json',
			...($token === null ? [] : ['Authorization' => "Bearer {$token}"]),
			...($revision === null ? [] : ['If-Match' => "\"{$revision}\""])
		], $body === null ? '' : (string) json_encode((object) $body), ['REMOTE_ADDR' => '203.0.113.9']));
	}

	private function handle(Request $request): ResponseInterface
	{
		return $this->app->container()->make(Kernel::class)->handle($request);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function json(ResponseInterface $response, int $status): array
	{
		$this->assertSame($status, $response->getStatusCode(), (string) $response->getBody());

		$data = json_decode((string) $response->getBody(), true);
		$this->assertIsArray($data);

		/** @var array<string, mixed> $data */
		return $data;
	}

	/**
	 * Returns an error's code and field.
	 *
	 * @return array{mixed, mixed}
	 */
	private function error(ResponseInterface $response, int $status): array
	{
		$error = $this->json($response, $status)['error'] ?? null;

		return is_array($error) ? [$error['code'] ?? null, $error['field'] ?? null] : [null, null];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function text(array $data, string $key): string
	{
		return is_string($data[$key] ?? null) ? $data[$key] : '';
	}

	public function testCreatesADraft(): void
	{
		$this->boot();
		$author = $this->token('writer', ['author'], 'justintadlock');
		$art    = self::idFor('_category/art.md');

		$response = $this->send('POST', '/api/v1/entries?body=markdown', $author, ['type' => 'post', 'title' => 'Fresh', 'body' => 'Hello.', 'relations' => ['category' => [$art, 'book-reviews']]]);
		$created  = $this->json($response, 201);

		$this->assertSame('draft', $created['status'] ?? null, 'Always a draft (D-762).');
		$this->assertSame('/api/v1/entries/' . self::text($created, 'id'), parse_url($response->getHeaderLine('Location'), PHP_URL_PATH));
		$this->assertNotSame('', self::text($created, 'revision'));
		$this->assertSame('Hello.', trim(self::text($created, 'body')));
		$this->assertSame(['Art', 'Book Reviews'], array_column(is_array($created['relations'] ?? null) && is_array($created['relations']['category'] ?? null) ? $created['relations']['category'] : [], 'title'), 'Targets by id or slug.');
		$this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));

		$this->assertSame(['invalid_body', 'status'], $this->error($this->send('POST', '/api/v1/entries', $author, ['type' => 'post', 'title' => 'X', 'status' => 'published']), 400));
		$this->assertSame(['invalid_body', 'fields.mood'], $this->error($this->send('POST', '/api/v1/entries', $author, ['type' => 'post', 'title' => 'X', 'fields' => ['mood' => 'sunny']]), 400), 'Only declared fields (D-743).');
		$this->assertSame(['invalid_body', 'summary'], $this->error($this->send('POST', '/api/v1/entries', $author, ['type' => 'post', 'title' => 'X', 'summary' => 'Hi']), 400));
		$this->assertSame(['token_required', null], $this->error($this->send('POST', '/api/v1/entries', null, ['type' => 'post', 'title' => 'X']), 401));
		$this->assertSame(['forbidden', null], $this->error($this->send('POST', '/api/v1/entries', $this->token('reader', ['editor'], capabilities: []), ['type' => 'post', 'title' => 'X']), 403), 'A Read token writes nothing.');
	}

	public function testChangesAnEntryAtItsRevision(): void
	{
		$this->boot();
		$author = $this->token('writer', ['author'], 'justintadlock');
		$id     = self::idFor('_post/2021-01-01.mine.md');
		$read   = $this->json($this->send('GET', "/api/v1/entries/{$id}?status=draft", $author), 200);
		$first  = self::text($read, 'revision');

		$this->assertNotSame('', $first, 'A token\'s read carries the revision.');
		$this->assertSame(['revision_required', null], $this->error($this->send('PATCH', "/api/v1/entries/{$id}", $author, ['title' => 'Mine, Again']), 428));

		$changed = $this->json($this->send('PATCH', "/api/v1/entries/{$id}", $author, ['title' => 'Mine, Again', 'subtitle' => 'A line'], $first), 200);

		$this->assertSame(['Mine, Again', 'A line'], [$changed['title'] ?? null, $changed['subtitle'] ?? null]);
		$this->assertNotSame($first, self::text($changed, 'revision'));
		$this->assertSame(['revision_mismatch', null], $this->error($this->send('PATCH', "/api/v1/entries/{$id}", $author, ['title' => 'Stale'], $first), 412), 'Never saved over.');

		$this->clock->advance('PT1M');

		$published = $this->json($this->send('PATCH', "/api/v1/entries/{$id}", $author, ['status' => 'published'], self::text($changed, 'revision')), 200);
		$this->assertSame('published', $published['status'] ?? null);

		[$log] = $this->app->container()->make(ActivityLog::class)->list(new ActivityFilter(subject: $id));

		$this->assertSame(['entry.status', 'entry.update'], array_map(static fn ($activity): string => $activity->action->value, $log), 'Logged, for Undo (D-758).');
		$this->assertSame(Via::Api, $log[0]->via);
		$this->assertSame('Script', $log[0]->client);

		$other = self::idFor('_post/2008-04-20.rainy.md');
		$this->assertSame(['not_found', null], $this->error($this->send('PATCH', "/api/v1/entries/{$other}", $author, ['title' => 'Mine now'], 'x'), 404), 'Someone else\'s isn\'t found.');
	}

	public function testTrashesAndRestores(): void
	{
		$this->boot();
		$editor  = $this->token('editor', ['editor']);
		$id      = self::idFor('_post/2008-04-20.rainy.md');
		$read    = $this->json($this->send('GET', "/api/v1/entries/{$id}", $editor), 200);
		$trashed = $this->json($this->send('DELETE', "/api/v1/entries/{$id}", $editor, revision: self::text($read, 'revision')), 200);

		$this->assertSame('trash', $trashed['status'] ?? null);
		$this->assertSame(['unprocessable', 'status'], $this->error($this->send('PATCH', "/api/v1/entries/{$id}", $editor, ['title' => 'Back'], self::text($trashed, 'revision')), 422), 'Only restoring.');
		$this->assertSame('draft', $this->json($this->send('PATCH', "/api/v1/entries/{$id}", $editor, ['status' => 'draft'], self::text($trashed, 'revision')), 200)['status'] ?? null);
		$this->assertSame(['invalid_body', 'status'], $this->error($this->send('PATCH', "/api/v1/entries/{$id}", $editor, ['status' => 'trash'], 'x'), 400));
	}

	public function testLimitsWritesPerToken(): void
	{
		$this->boot('new Blush\\Api\\ApiConfig(enabled: true, maxWrites: 1)');
		$editor = $this->token('editor', ['editor']);

		$this->json($this->send('POST', '/api/v1/entries', $editor, ['type' => 'post', 'title' => 'One']), 201);

		$response = $this->send('POST', '/api/v1/entries', $editor, ['type' => 'post', 'title' => 'Two']);

		$this->assertSame(['too_many_writes', null], $this->error($response, 429));
		$this->assertNotSame('', $response->getHeaderLine('Retry-After'));
	}

	public function testUploadsAndDescribesMedia(): void
	{
		$this->boot();
		$editor    = $this->token('editor', ['editor']);
		$png       = (string) base64_decode(self::PNG, true);
		$temporary = $this->writeTemporaryFile('uploads/dot', $png);
		$request   = Request::create('/api/v1/media', 'POST', ['Authorization' => "Bearer {$editor}"], '', ['REMOTE_ADDR' => '203.0.113.9'])
			->withUploadedFiles(['file' => new UploadedFile($temporary, strlen($png), UPLOAD_ERR_OK, 'Dot.png', 'image/png')])
			->withParsedBody(['alt' => 'A dot']);

		$response = $this->handle($request);
		$file     = $this->json($response, 201);
		$id       = self::text($file, 'id');

		$this->assertSame('A dot', $file['alt'] ?? null);
		$this->assertStringEndsWith("/api/v1/media/{$id}", $response->getHeaderLine('Location'));

		$changed = $this->json($this->send('PATCH', "/api/v1/media/{$id}", $editor, ['caption' => 'Small.', 'alt' => null]), 200);

		$this->assertSame(['Small.', ''], [$changed['caption'] ?? null, $changed['alt'] ?? null]);
		$this->assertSame(['invalid_body', 'size'], $this->error($this->send('PATCH', "/api/v1/media/{$id}", $editor, ['size' => 3]), 400));

		$entry   = self::idFor('_post/2021-01-01.mine.md');
		$author  = $this->token('writer', ['author'], 'justintadlock');
		$read    = $this->json($this->send('GET', "/api/v1/entries/{$entry}?status=draft", $author), 200);
		$pictured = $this->json($this->send('PATCH', "/api/v1/entries/{$entry}?body=none", $author, ['image' => $id], self::text($read, 'revision')), 200);

		$this->assertSame($id, is_array($pictured['image'] ?? null) ? ($pictured['image']['id'] ?? null) : null, 'Media by id.');
		$this->assertStringContainsString("image: {$id}", (string) file_get_contents($this->temporaryDirectory() . '/user/content/_post/2021-01-01.mine.md'), 'Kept by id (D-773).');
	}

	public function testPreflightsAllowWrites(): void
	{
		$this->boot();
		$response = $this->handle(Request::create('/api/v1/entries', 'OPTIONS'));

		$this->assertStringContainsString('PATCH', $response->getHeaderLine('Access-Control-Allow-Methods'));
		$this->assertStringContainsString('If-Match', $response->getHeaderLine('Access-Control-Allow-Headers'));
	}
}
