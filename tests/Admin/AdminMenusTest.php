<?php

/**
 * Admin menus tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Admin\CountsController;
use Blush\Admin\MenusController;
use Blush\Menu\MenuLoader;
use Blush\Menu\Menus;
use Blush\Tests\WritesContentConfig;

#[CoversClass(MenusController::class)]
#[CoversClass(MenuLoader::class)]
#[CoversClass(Menus::class)]
#[CoversClass(CountsController::class)]
final class AdminMenusTest extends TestCase
{
	use BootsAdmin;
	use WritesContentConfig;

	private const string ABOUT = '0199b6e2-0000-7000-8000-00000000b001';

	private const string DRAFT = '0199b6e2-0000-7000-8000-00000000b002';

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * A site with a theme of two locations, two menus, and the main menu
	 * in the primary location.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['editor']): void
	{
		$this->writeTemporaryFile('user/content/about.md', "---\ntitle: About\nid: " . self::ABOUT . "\n---\n");
		$this->writeTemporaryFile('user/content/plans.md', "---\ntitle: Plans\nid: " . self::DRAFT . "\nstatus: draft\n---\n");
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', json_encode([
			'name'      => 'acme/nova',
			'label'     => 'Nova',
			'namespace' => 'nova',
			'menus'     => [
				'primary' => ['label' => 'Header', 'depth' => 2, 'items' => [['url' => '/', 'label' => 'Home']]],
				'footer'  => 'Footer'
			]
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/nova');\n");
		$this->writeTemporaryFile('user/data/menus/main.json', json_encode(['label' => 'Main', 'items' => [
			['entry' => 'page/about', 'ref' => self::ABOUT],
			['label' => 'More', 'children' => [
				['url' => 'https://www.github.com/acme', 'label' => 'GitHub', 'rel' => 'me'],
				['entry' => 'page/plans'],
				['entry' => 'page/gone']
			]]
		]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
		$this->writeTemporaryFile('user/data/menus/social.json', '{"items":[{"url":"https://example.org/","label":"Example"}]}');
		$this->writeTemporaryFile('user/data/settings/acme__nova.json', '{"menus":{"primary":"main"}}');

		$this->boot(roles: $roles);
		$this->login();
	}

	/**
	 * Sends a request with the CSRF token.
	 *
	 * @param array<string, mixed> $data
	 */
	private function write(string $method, string $path, array $data = []): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send($method, $path, json_encode($data) ?: '', ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}

	/**
	 * Returns a value inside a JSON answer by its keys, or `null`.
	 */
	private static function at(mixed $value, string|int ...$keys): mixed
	{
		foreach ($keys as $key) {
			$value = is_array($value) ? ($value[$key] ?? null) : null;
		}

		return $value;
	}

	/**
	 * A menu's file, without the id its record is given.
	 *
	 * @return array<mixed>
	 */
	private function stored(string $name): array
	{
		$data = json_decode((string) file_get_contents($this->temporaryDirectory() . "/user/data/menus/{$name}.json"), true);

		$this->assertIsArray($data);
		unset($data['id']);

		return $data;
	}

	/**
	 * The theme's saved menu assignments.
	 */
	private function assignments(): mixed
	{
		$file = $this->temporaryDirectory() . '/user/data/settings/acme__nova.json';

		$data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

		return is_array($data) ? $data['menus'] ?? [] : null;
	}

	public function testListsMenusAndTheThemesLocations(): void
	{
		$this->site();

		$answer = self::json($this->send('GET', '/menus'));

		$this->assertSame('Nova', $answer['theme'] ?? null);
		$this->assertSame([
			['name' => 'main', 'label' => 'Main', 'items' => 5, 'locations' => ['primary']],
			['name' => 'social', 'label' => '', 'items' => 1, 'locations' => []]
		], $answer['menus'] ?? null, 'Items are counted at every level.');
		$this->assertSame([
			['name' => 'footer', 'label' => 'Footer', 'depth' => null, 'defaults' => 0, 'menu' => null],
			['name' => 'primary', 'label' => 'Header', 'depth' => 2, 'defaults' => 1, 'menu' => 'main']
		], $answer['locations'] ?? null);
		$this->assertSame(2, self::json($this->send('GET', '/counts'))['menus'] ?? null);
	}

	public function testShowsEachItemWithWhereItsLinkLeads(): void
	{
		$this->site();

		$answer = self::json($this->send('GET', '/menus/main'));
		$items  = self::at($answer, 'menu', 'items');

		$this->assertTrue(self::at($answer, 'menu', 'editable'));
		$this->assertSame(['entry' => 'page/about', 'ref' => self::ABOUT], self::at($items, 0, 'item'));
		$this->assertSame(['About', '/about', 'live'], [self::at($items, 0, 'link', 'title'), self::at($items, 0, 'link', 'address'), self::at($items, 0, 'link', 'state')]);
		$this->assertSame(['label' => 'More'], self::at($items, 1, 'item'), 'Children are their own nodes.');
		$this->assertNull(self::at($items, 1, 'link'), 'An item without a link is plain text.');
		$this->assertSame('github.com', self::at($items, 1, 'children', 0, 'link', 'title'));
		$this->assertSame(['Plans', 'draft'], [self::at($items, 1, 'children', 1, 'link', 'title'), self::at($items, 1, 'children', 1, 'link', 'state')]);
		$this->assertSame(self::DRAFT, self::at($items, 1, 'children', 1, 'link', 'ref'));
		$this->assertSame('missing', self::at($items, 1, 'children', 2, 'link', 'state'));
		$this->assertSame(['main', 'social'], array_column(is_array($answer['menus'] ?? null) ? $answer['menus'] : [], 'name'));
		$this->assertContains(['key' => 'entry', 'keys' => ['ref']], is_array($answer['kinds'] ?? null) ? $answer['kinds'] : []);
		$this->assertSame(404, $this->send('GET', '/menus/nope')->getStatusCode());
	}

	public function testAMenuWithTheWrongShapeCantBeEdited(): void
	{
		$this->site();
		$this->writeTemporaryFile('user/data/menus/broken.json', '{"items":["about",{"entry":"page/about","url":"/about"}]}');

		$answer = self::json($this->send('GET', '/menus/broken'));

		$this->assertFalse(self::at($answer, 'menu', 'editable'));
		$this->assertSame(['Item 1 isn\'t a map of keys to values.', 'Item 2 has more than one link (entry, url).'], self::at($answer, 'menu', 'problems'));
	}

	public function testPublishesANewMenuInTheLocationsChosen(): void
	{
		$this->site();

		$response = $this->write('POST', '/menus', ['was' => null, 'name' => 'footer-links', 'label' => 'Footer Links', 'items' => [['entry' => 'page/about', 'ref' => self::ABOUT, 'label' => 'Who we are']], 'locations' => ['footer']]);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['label' => 'Footer Links', 'items' => [['entry' => 'page/about', 'ref' => self::ABOUT, 'label' => 'Who we are']]], $this->stored('footer-links'));
		$this->assertSame(['footer' => 'footer-links', 'primary' => 'main'], $this->assignments());
		$this->assertSame(409, $this->write('POST', '/menus', ['was' => null, 'name' => 'social', 'items' => [], 'locations' => []])->getStatusCode(), 'A name in use is refused.');
	}

	public function testRenamesAMenuAndItsLocationsFollow(): void
	{
		$this->site();

		$response = $this->write('POST', '/menus', ['was' => 'main', 'name' => 'header', 'label' => 'Header Links', 'items' => [['url' => '/feed', 'label' => 'Feed']], 'locations' => ['primary', 'footer']]);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame('header', self::at(self::json($response), 'menu', 'name'));
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/menus/main.json');
		$this->assertFileExists($this->temporaryDirectory() . '/user/data/menus/header.json');
		$this->assertSame(['footer' => 'header', 'primary' => 'header'], $this->assignments());

		$this->write('POST', '/menus', ['was' => 'header', 'name' => 'header', 'label' => '', 'items' => [['url' => '/feed', 'label' => 'Feed']], 'locations' => []]);

		$this->assertSame([], $this->assignments(), 'Locations it leaves go back to their default.');
		$this->assertSame(['items' => [['url' => '/feed', 'label' => 'Feed']]], $this->stored('header'), 'An empty label is left out.');
	}

	public function testRefusesItemsThatCantBeShown(): void
	{
		$this->site();

		$empty = self::json($this->write('POST', '/menus', ['was' => 'main', 'name' => 'main', 'items' => [['entry' => 'page/about'], ['children' => [[]]]], 'locations' => []]));
		$bad   = self::json($this->write('POST', '/menus', ['was' => 'main', 'name' => 'main', 'items' => [['url' => 'javascript:alert(1)', 'label' => 'Bad']], 'locations' => []]));

		$this->assertSame(['Item 2 needs a label or a link.', 'Item 2.1 needs a label or a link.'], $empty['problems'] ?? null);
		$this->assertSame(['Item 1\'s link isn\'t a safe URL.'], $bad['problems'] ?? null);
		$this->assertSame(422, $this->write('POST', '/menus', ['was' => null, 'name' => '-nope', 'items' => [], 'locations' => []])->getStatusCode());
	}

	public function testDeletesAMenuAndAssignsLocations(): void
	{
		$this->site();

		$answer = self::json($this->write('DELETE', '/menus/main'));

		$this->assertSame('main', self::at($answer, 'deleted', 'name'));
		$this->assertSame(['primary'], $answer['locations'] ?? null, 'What Undo needs to put it back.');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/menus/main.json');
		$this->assertSame([], $this->assignments());

		$assigned = self::json($this->write('PUT', '/menu-locations/primary', ['menu' => 'social']));

		$this->assertSame('social', self::at($assigned, 'locations', 1, 'menu'));
		$this->assertSame(['primary' => 'social'], $this->assignments());
		$this->assertSame(404, $this->write('PUT', '/menu-locations/sidebar', ['menu' => 'social'])->getStatusCode());
		$this->assertSame(404, $this->write('PUT', '/menu-locations/footer', ['menu' => 'nope'])->getStatusCode());

		$this->write('PUT', '/menu-locations/primary', ['menu' => null]);

		$this->assertSame([], $this->assignments());
	}

	public function testSearchesWhatAnItemCanLinkTo(): void
	{
		$this->site();

		$entries = self::json($this->send('GET', '/menu-links?search=abo'));
		$types   = self::json($this->send('GET', '/menu-links?search=profiles'));

		$this->assertSame(['kind' => 'entry', 'value' => 'page/about', 'ref' => self::ABOUT, 'title' => 'About', 'address' => '/about', 'state' => 'live', 'message' => null], self::at($entries, 'links', 0));
		$this->assertContains('collection', array_column(is_array($types['links'] ?? null) ? $types['links'] : [], 'kind'));
	}

	public function testRanksTheBestMatchFirstWhateverItsKind(): void
	{
		$this->contentConfig(['types' => ['post' => ['urls' => ['prefix' => 'posts']], 'topic' => ['urls' => ['prefix' => 'topics']]], 'relations' => ['topic' => ['kind' => 'classify', 'to' => ['topic']]]]);

		for ($day = 10; $day < 30; $day++) {
			$this->writeTemporaryFile("user/content/_post/2026-01-{$day}.writing-{$day}.md", "---\ntitle: Writing Notes {$day}\ndate: 2026-01-{$day} 12:00:00\nid: 0199b6e2-0000-7000-8000-0000000c00{$day}\n---\n");
		}

		$this->writeTemporaryFile('user/content/_topic/writing.md', "---\ntitle: Writing\nid: 0199b6e2-0000-7000-8000-00000000c999\n---\n");
		$this->site();

		$answer = self::json($this->send('GET', '/menu-links?search=writing'));

		$this->assertSame(['term', 'topic/writing', 'Writing'], [self::at($answer, 'links', 0, 'kind'), self::at($answer, 'links', 0, 'value'), self::at($answer, 'links', 0, 'title')], 'The title itself, a term, before twenty posts.');
		$this->assertCount(10, is_array($answer['links'] ?? null) ? $answer['links'] : []);
		$this->assertSame(21, $answer['total'] ?? null);
	}

	public function testNeedsTheCapability(): void
	{
		$this->site(['author']);

		$this->assertSame(403, $this->send('GET', '/menus')->getStatusCode());
		$this->assertSame(403, $this->write('POST', '/menus', ['was' => null, 'name' => 'x', 'items' => [], 'locations' => []])->getStatusCode());
		$this->assertArrayNotHasKey('menus', self::json($this->send('GET', '/counts')));
	}
}
