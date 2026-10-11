<?php

/**
 * API tests.
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
use Blush\Api\ApiConfig;
use Blush\Api\ApiEntries;
use Blush\Api\ApiError;
use Blush\Api\ApiIncludes;
use Blush\Api\ApiQuery;
use Blush\Api\ApiResponses;
use Blush\Api\ApiRoutes;
use Blush\Api\ApiServiceProvider;
use Blush\Api\ApiValues;
use Blush\Api\EntryResource;
use Blush\Api\Http\EntriesController;
use Blush\Api\Http\IndexController;
use Blush\Api\Http\MediaController;
use Blush\Api\Http\MenusController;
use Blush\Api\Http\RelationsController;
use Blush\Api\Http\SiteController;
use Blush\Api\Http\TypesController;
use Blush\Api\MediaResource;
use Blush\Config\InvalidConfig;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(ApiConfig::class)]
#[CoversClass(ApiEntries::class)]
#[CoversClass(ApiError::class)]
#[CoversClass(ApiIncludes::class)]
#[CoversClass(ApiQuery::class)]
#[CoversClass(ApiResponses::class)]
#[CoversClass(ApiRoutes::class)]
#[CoversClass(ApiServiceProvider::class)]
#[CoversClass(ApiValues::class)]
#[CoversClass(EntryResource::class)]
#[CoversClass(MediaResource::class)]
#[CoversClass(IndexController::class)]
#[CoversClass(SiteController::class)]
#[CoversClass(TypesController::class)]
#[CoversClass(EntriesController::class)]
#[CoversClass(RelationsController::class)]
#[CoversClass(MediaController::class)]
#[CoversClass(MenusController::class)]
final class ApiTest extends TestCase
{
	use BuildsContentSite;

	/**
	 * A 1×1 PNG.
	 */
	private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	private const string PHOTO = '0199b6e2-0000-7000-8000-0000000000aa';

	private Application $app;

	private function boot(?string $apiConfig = 'new Blush\\Api\\ApiConfig(enabled: true)', ?string $routeConfig = null): void
	{
		$this->writeTemporaryFile('config/cache.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Cache\\CacheConfig(enabled: false);\n");

		if ($apiConfig !== null) {
			$this->writeTemporaryFile('config/api.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn {$apiConfig};\n");
		}

		if ($routeConfig !== null) {
			$this->writeTemporaryFile('config/routes.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn {$routeConfig};\n");
		}

		$this->app = $this->site();
	}

	private function get(string $uri, string $method = 'GET'): ResponseInterface
	{
		return $this->app->container()->make(Kernel::class)->handle(Request::create($uri, $method));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function json(string $uri, int $status = 200): array
	{
		$response = $this->get($uri);

		$this->assertSame($status, $response->getStatusCode(), $uri . ': ' . $response->getBody());
		$this->assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'), $uri);

		$data = json_decode((string) $response->getBody(), true);
		$this->assertIsArray($data);

		/** @var array<string, mixed> $data */
		return $data;
	}

	/**
	 * Returns a list's items' values under a key.
	 *
	 * @return list<mixed>
	 */
	private function column(string $uri, string $key): array
	{
		return array_column(self::arrayAt($this->json($uri), 'items'), $key);
	}

	/**
	 * Returns a value inside decoded JSON, or `null`.
	 */
	private static function at(mixed $value, string|int ...$keys): mixed
	{
		foreach ($keys as $key) {
			$value = is_array($value) ? ($value[$key] ?? null) : null;
		}

		return $value;
	}

	/**
	 * Returns a value inside decoded JSON as an array, `[]` when it isn't
	 * one.
	 *
	 * @return array<mixed>
	 */
	private static function arrayAt(mixed $value, string|int ...$keys): array
	{
		$value = self::at($value, ...$keys);

		return is_array($value) ? $value : [];
	}

	private function withMedia(): void
	{
		$this->writeTemporaryFile('user/media/2019/photo.png', (string) base64_decode(self::PNG, true));
		$this->writeTemporaryFile('user/data/media/2019/photo.png.json', '{"title": "A photo", "alt": "Flowers", "caption": "In spring", "credit": "Sam", "id": "' . self::PHOTO . '"}');
	}

	public function testIsOffByDefault(): void
	{
		$this->standardContent();
		$this->boot(null);

		$response = $this->get('/api/v1/site');

		$this->assertSame(404, $response->getStatusCode());
		$this->assertStringNotContainsString('application/json', $response->getHeaderLine('Content-Type'), 'Off, the site\'s own 404 answers, so the API isn\'t shown to exist (D-737).');
	}

	public function testRefusesABadPath(): void
	{
		$this->expectException(InvalidConfig::class);

		(void) new ApiConfig(path: '/api/');
	}

	public function testAnswersTheIndexAndTheSite(): void
	{
		$this->standardContent();
		$this->boot();

		$this->assertSame('http://localhost/api/v1/entries', self::at($this->json('/api/v1'), 'resources', 'entries'));
		$this->assertSame([
			'name'        => 'Blush',
			'description' => '',
			'url'         => 'http://localhost',
			'language'    => 'en-US',
			'locale'      => 'en_US',
			'timezone'    => 'America/Chicago',
			'home'        => 'http://localhost/'
		], $this->json('/api/v1/site'));
	}

	public function testServesAtAnotherPath(): void
	{
		$this->standardContent();
		$this->boot('new Blush\\Api\\ApiConfig(enabled: true, path: \'/data\')');

		$this->assertSame('Blush', self::at($this->json('/data/v1/site'), 'name'));
		$this->assertSame(404, $this->get('/api/v1/site')->getStatusCode());
	}

	public function testListsWhatTheSiteLists(): void
	{
		$this->standardContent();
		$this->boot();

		$list = $this->json('/api/v1/entries?type=post');

		$this->assertSame(['hello', 'spring', 'welcome'], array_column(self::arrayAt($list, 'items'), 'slug'), 'Newest first; no drafts, future, or unlisted posts, nor the landing page.');
		$this->assertSame(['total' => 3, 'page' => 1, 'pages' => 1, 'per_page' => 20, 'next' => null, 'prev' => null], array_diff_key($list, ['items' => true]));
		$this->assertArrayNotHasKey('body', self::arrayAt($list, 'items', 0), 'Lists leave bodies out (D-739).');
		$this->assertSame('Hello and welcome to my site.', self::at($list, 'items', 2, 'excerpt'));
		$this->assertSame('2003-04-15T22:39:00Z', self::at($list, 'items', 2, 'published'), 'Dates are in UTC (D-737).');
		$this->assertNotContains('private', $this->column('/api/v1/entries?per_page=100', 'slug'), 'Hidden pages never show.');
		$this->assertNotContains('idea', $this->column('/api/v1/entries?per_page=100', 'slug'));
	}

	public function testPagesAList(): void
	{
		$this->standardContent();
		$this->boot();

		$first  = $this->json('/api/v1/entries?type=post&per_page=2');
		$second = $this->json('/api/v1/entries?type=post&per_page=2&page=2');

		$this->assertSame(['hello', 'spring'], array_column(self::arrayAt($first, 'items'), 'slug'));
		$this->assertSame(2, self::at($first, 'pages'));
		$this->assertSame('http://localhost/api/v1/entries?type=post&per_page=2&page=2', self::at($first, 'next'));
		$this->assertNull(self::at($first, 'prev'));
		$this->assertSame(['welcome'], array_column(self::arrayAt($second, 'items'), 'slug'));
		$this->assertSame('http://localhost/api/v1/entries?type=post&per_page=2', self::at($second, 'prev'), 'The first page has no page parameter.');
	}

	public function testAnswersAnEntryById(): void
	{
		$this->standardContent();
		$this->boot();

		$entry = $this->json('/api/v1/entries/' . self::idFor('_post/2008-04-05.spring.md'));

		$this->assertSame('spring', self::at($entry, 'title'));
		$this->assertSame('http://localhost/archives/spring', self::at($entry, 'url'));
		$this->assertSame("<p>Spring is here.</p>\n", self::at($entry, 'body'), 'A single entry has its HTML (D-739).');
		$this->assertSame(['styles' => [], 'scripts' => []], self::at($entry, 'assets'));
		$this->assertSame([], self::at($entry, 'fields'), 'Undeclared front matter (tag) isn\'t sent (D-743).');
		$this->assertSame(['Justin Tadlock', 'A Guest'], array_column(self::arrayAt($entry, 'relations', 'authors'), 'title'), 'Relations as stubs, in order (D-738).');
		$this->assertSame(['id', 'type', 'title', 'url'], array_keys(self::arrayAt($entry, 'relations', 'category', 0)));
		$this->assertArrayNotHasKey('parent', $entry, 'Posts don\'t nest.');

		$markdown = $this->json('/api/v1/entries/' . self::idFor('_post/2008-04-05.spring.md') . '?body=markdown');
		$this->assertSame('Spring is here.', self::at($markdown, 'body'));
		$this->assertArrayNotHasKey('assets', $markdown);

		$this->assertSame('Rainy', self::at($this->json('/api/v1/entries/' . self::idFor('_post/2008-04-20.rainy.md')), 'title'), 'An unlisted entry has a page, so it can be asked for.');
	}

	public function testRefusesWhatTheSiteDoesntShow(): void
	{
		$this->standardContent();
		$this->boot();

		$this->assertSame(['error' => ['code' => 'not_found', 'message' => 'No entry has the id "nope".']], $this->json('/api/v1/entries/nope', 404));

		foreach (['_post/2020-01-01.unfinished.md', '_post/2026-12-25.future.md', '_private.md'] as $path) {
			$this->assertSame('not_found', self::at($this->json('/api/v1/entries/' . self::idFor($path), 404), 'error', 'code'), $path);
		}
	}

	public function testGivesAPagesParent(): void
	{
		$this->standardContent();
		$this->boot();

		$page = $this->json('/api/v1/entries/' . self::idFor('about/biography.md'));

		$this->assertSame('About', self::at($page, 'parent', 'title'));
		$this->assertSame([], self::arrayAt($page, 'relations'), 'A parent isn\'t a relation entries list.');
	}

	public function testIncludesRelationsInPlace(): void
	{
		$this->standardContent();
		$this->boot();

		$entry = $this->json('/api/v1/entries/' . self::idFor('_post/2008-04-05.spring.md') . '?include=relations.authors');

		$this->assertSame('Writes things.', self::at($entry, 'relations', 'authors', 0, 'excerpt'), 'An included entry is the whole record (D-738).');
		$this->assertArrayNotHasKey('body', self::arrayAt($entry, 'relations', 'authors', 0), 'Included entries have no body.');
		$this->assertSame(['id', 'type', 'title', 'url'], array_keys(self::arrayAt($entry, 'relations', 'category', 0)), 'Only what was named is included.');
		$this->assertSame('invalid_query', self::at($this->json('/api/v1/entries?include=everything', 400), 'error', 'code'));
	}

	public function testFiltersByRelation(): void
	{
		$this->standardContent();
		$this->boot();

		$this->assertSame(['spring'], $this->column('/api/v1/entries?rel:category=art', 'slug'), 'Rainy is unlisted, so only spring.');
		$this->assertSame(['spring', 'welcome'], $this->column('/api/v1/entries?rel:category=art,old-posts', 'slug'), 'Commas mean any of.');
		$this->assertSame(['spring'], $this->column('/api/v1/entries?rel:authors=' . self::idFor('_profile/guest.md'), 'slug'), 'By id.');
		$this->assertSame('invalid_query', self::at($this->json('/api/v1/entries?rel:colors=red', 400), 'error', 'code'));
	}

	public function testFiltersAndSorts(): void
	{
		$this->standardContent();
		$this->boot();

		$this->assertSame(['spring', 'welcome'], $this->column('/api/v1/entries?type=post&published_before=2009-01-01', 'slug'));
		$this->assertSame(['hello'], $this->column('/api/v1/entries?type=post&published_after=2009-01-01', 'slug'));
		$this->assertSame(['welcome', 'spring', 'hello'], $this->column('/api/v1/entries?type=post&sort=published', 'slug'));
		$this->assertSame(['welcome', 'spring', 'hello'], $this->column('/api/v1/entries?type=post&sort=-title', 'slug'), 'Titles sort as written, "spring" lowercase.');
		$this->assertSame(['welcome'], $this->column('/api/v1/entries?ids=' . self::idFor('_post/2003-04-15.welcome.md') . ',' . self::idFor('_post/2020-01-01.unfinished.md'), 'slug'), 'Ids the site doesn\'t show are left out.');
		$this->assertSame(['biography'], $this->column('/api/v1/entries?parent=' . self::idFor('about/index.md'), 'slug'));

		foreach (['sort=size', 'per_page=101', 'page=0', 'type=nothing', 'published_after=soon', 'ids=1', 'bogus=1'] as $query) {
			$this->assertSame('invalid_query', self::at($this->json("/api/v1/entries?{$query}", 400), 'error', 'code'), $query);
		}
	}

	public function testFindsAnEntryByAddress(): void
	{
		$this->standardContent();
		$this->boot(routeConfig: 'new Blush\\Routing\\RouteConfig(redirects: [new Blush\\Routing\\Redirect(\'/old-bio\', \'/about/biography\')])');

		$this->assertSame(['Biography'], $this->column('/api/v1/entries?url=/about/biography/', 'title'), 'A trailing slash doesn\'t count.');
		$this->assertSame(['Biography'], $this->column('/api/v1/entries?url=' . rawurlencode('http://localhost/about/biography'), 'title'), 'A full URL on the site.');
		$this->assertSame(['Rainy'], $this->column('/api/v1/entries?url=/archives/rainy', 'title'));
		$this->assertSame(['Topics'], $this->column('/api/v1/entries?url=/topics', 'title'), 'A listing\'s landing page is the entry at its address, as its Markdown copy is.');
		$this->assertSame([], $this->column('/api/v1/entries?url=/archives/2008', 'title'), 'An archive isn\'t an entry.');
		$this->assertSame([], $this->column('/api/v1/entries?url=' . rawurlencode('https://elsewhere.test/about'), 'title'));

		$moved = $this->json('/api/v1/entries?url=/old-bio');
		$this->assertSame([], self::at($moved, 'items'));
		$this->assertSame(['to' => 'http://localhost/about/biography', 'status' => 301], self::at($moved, 'redirect'));

		$this->assertSame('invalid_query', self::at($this->json('/api/v1/entries?url=/about&type=page', 400), 'error', 'code'), 'url takes only body and include beside it.');
	}

	public function testAnswersTypesAndRelations(): void
	{
		$this->standardContent();
		$this->boot();

		$types = self::arrayAt($this->json('/api/v1/types'), 'items');
		$post  = $this->json('/api/v1/types/post');

		$this->assertSame(['page', 'profile', 'category', 'post'], array_column($types, 'name'));
		$this->assertSame(['name' => 'post', 'kind' => 'collection', 'label' => 'Post', 'plural' => 'Posts', 'description' => '', 'url' => 'http://localhost/', 'urls' => true, 'fields' => [], 'relations' => ['authors', 'category']], $post);
		$this->assertSame('not_found', self::at($this->json('/api/v1/types/nothing', 404), 'error', 'code'));
		$this->assertSame(['authors', 'category'], $this->column('/api/v1/relations', 'name'), 'Not a page\'s parent.');
	}

	public function testAnswersMedia(): void
	{
		$this->standardContent();
		$this->withMedia();
		$this->entry('_post/2011-01-01.photo.md', "title: Photo\npublished: 2011-01-01 10:00:00\nimage: /media/2019/photo.png");
		$this->boot();

		$file = $this->json('/api/v1/media/' . self::PHOTO);

		$this->assertSame(['id' => self::PHOTO, 'kind' => 'image', 'mime' => 'image/png', 'url' => 'http://localhost/media/2019/photo.png'], array_slice($file, 0, 4));
		$this->assertSame([1, 1, 'A photo', 'Flowers', 'In spring'], [self::at($file, 'width'), self::at($file, 'height'), self::at($file, 'title'), self::at($file, 'alt'), self::at($file, 'caption')]);
		$this->assertSame(['credit' => 'Sam'], self::at($file, 'fields'));
		$this->assertSame([self::PHOTO], $this->column('/api/v1/media?kind=image', 'id'), 'Media is public, all of it (D-736).');
		$this->assertSame([], $this->column('/api/v1/media?kind=audio', 'id'));
		$this->assertSame('not_found', self::at($this->json('/api/v1/media/' . self::idFor('nothing'), 404), 'error', 'code'));

		$entry = $this->json('/api/v1/entries/' . self::idFor('_post/2011-01-01.photo.md'));
		$this->assertSame(['id' => self::PHOTO, 'url' => 'http://localhost/media/2019/photo.png', 'alt' => 'Flowers', 'width' => 1, 'height' => 1], self::at($entry, 'image'), 'A media stub (D-738).');
		$this->assertSame('In spring', self::at($this->json('/api/v1/entries/' . self::idFor('_post/2011-01-01.photo.md') . '?include=media'), 'image', 'caption'), 'Included in place.');
	}

	public function testSendsTheFieldsATypeDeclares(): void
	{
		$this->contentConfig([
			'types' => ['note' => ['urls' => ['prefix' => 'notes'], 'fields' => [['name' => 'mood', 'type' => 'text'], ['name' => 'cover', 'type' => 'media'], ['name' => 'rating', 'type' => 'number']]]]
		]);
		$this->withMedia();
		$this->entry('_note/2011-01-01.calm.md', "title: Calm\npublished: 2011-01-01 10:00:00\nmood: happy\ncover: /media/2019/photo.png\nweather: rain");
		$this->boot();

		$this->assertSame(
			['mood' => 'happy', 'cover' => ['id' => self::PHOTO, 'url' => 'http://localhost/media/2019/photo.png', 'alt' => 'Flowers', 'width' => 1, 'height' => 1], 'rating' => null],
			self::at($this->json('/api/v1/entries/' . self::idFor('_note/2011-01-01.calm.md')), 'fields'),
			'Each declared field, null when empty; a media field as a stub; undeclared front matter (weather) left out (D-743).'
		);
	}

	public function testAnswersMenus(): void
	{
		$this->standardContent();
		$this->boot();

		$this->assertSame([['location' => 'primary', 'label' => 'Primary', 'menu' => null]], self::at($this->json('/api/v1/menus'), 'items'));
		$this->assertSame('primary', self::at($this->json('/api/v1/menus/primary'), 'location'));
		$this->assertSame('not_found', self::at($this->json('/api/v1/menus/footer', 404), 'error', 'code'));
	}

	public function testSendsItsHeaders(): void
	{
		$this->standardContent();
		$this->boot('new Blush\\Api\\ApiConfig(enabled: true, maxAge: 300)');

		$response = $this->get('/api/v1/site');

		$this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'), 'Every answer is public (D-741).');
		$this->assertSame('public, max-age=300', $response->getHeaderLine('Cache-Control'));
		$this->assertNotSame('', $response->getHeaderLine('ETag'));
		$this->assertSame('no-store', $this->get('/api/v1/nothing')->getHeaderLine('Cache-Control'), 'Errors aren\'t kept.');

		$preflight = $this->get('/api/v1/entries', 'OPTIONS');
		$this->assertSame(204, $preflight->getStatusCode());
		$this->assertSame('GET, HEAD, OPTIONS', $preflight->getHeaderLine('Access-Control-Allow-Methods'));
	}

	public function testAnswersNotModified(): void
	{
		$this->standardContent();
		$this->boot();

		$etag     = $this->get('/api/v1/site')->getHeaderLine('ETag');
		$response = $this->app->container()->make(Kernel::class)->handle(Request::create('/api/v1/site')->withHeader('If-None-Match', $etag));

		$this->assertSame(304, $response->getStatusCode());
	}
}
