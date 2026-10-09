<?php

/**
 * Admin editor pickers' API tests.
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
use Blush\Admin\IconsController;
use Blush\Admin\MediaListController;
use Blush\Admin\MediaUploadController;
use Blush\Media\MediaMetadata;
use Blush\Media\MediaMetadataStore;
use Blush\Media\MediaUploads;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Http\UploadedFile;
use Blush\Support\Uuid;
use Psr\Http\Message\ResponseInterface;
use Blush\Tests\SavedSettings;

#[CoversClass(IconsController::class)]
#[CoversClass(MediaListController::class)]
#[CoversClass(MediaUploadController::class)]
#[CoversClass(MediaMetadata::class)]
#[CoversClass(MediaMetadataStore::class)]
#[CoversClass(MediaUploads::class)]
final class AdminPickersTest extends TestCase
{
	use BootsAdmin;
	use SavedSettings;

	/**
	 * A 1×1 PNG.
	 */
	private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	/**
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['editor']): void
	{
		$png = (string) base64_decode(self::PNG, true);

		touch($this->writeTemporaryFile('user/media/2020/old.png', $png), 1_600_000_000);
		$this->writeTemporaryFile('user/media/2026/new-photo.png', $png);
		$this->writeTemporaryFile('user/media/notes.txt', 'not media');
		$this->writeTemporaryFile('user/media/.hidden.png', $png);
		$this->writeTemporaryFile('user/content/trip/index.md', "---\ntitle: Trip\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/trip/beach.png', $png);
		$this->writeTemporaryFile('user/content/other.md', "---\ntitle: Other\nauthors: sam\n---\n");
		$this->writeTemporaryFile('extensions/test/site/theme.json', '{"name": "test/site", "label": "Test Site", "namespace": "site", "parent": "blush/default"}');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'test/site');\n");
		$this->writeTemporaryFile('extensions/test/site/icons/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg>');

		$this->boot(roles: $roles);
		$this->login();
	}

	/**
	 * @return array<mixed>
	 */
	private function media(string $query = ''): array
	{
		return self::json($this->send('GET', "/media{$query}"));
	}

	public function testListsTheLibraryNewestFirst(): void
	{
		$this->site();

		$list = $this->media();

		$this->assertSame(2, $list['total'] ?? null, 'Only allowed, visible media files.');
		$this->assertSame(['new-photo.png', 'old.png'], array_column(is_array($list['files'] ?? null) ? $list['files'] : [], 'name'));

		$first = is_array($list['files'] ?? null) ? $list['files'][0] ?? null : null;

		$this->assertIsArray($first);
		$this->assertSame(['/media/2026/new-photo.png', '2026', 'image', 1, 1], [$first['reference'], $first['folder'], $first['kind'], $first['width'], $first['height']]);
		$this->assertArrayNotHasKey('beside', $list, 'Nothing beside an entry is media (D-294).');
	}

	public function testSearchesPagesAndFilters(): void
	{
		$this->site();

		$this->assertSame(['old.png'], array_column((array) ($this->media('?search=OLD')['files'] ?? []), 'name'));
		$this->assertSame(['old.png'], array_column((array) ($this->media('?per=1&page=2')['files'] ?? []), 'name'));
		$this->assertSame(0, $this->media('?kind=video')['total'] ?? null);
		$this->assertSame(0, $this->media('?kind=file')['total'] ?? null, 'A file is any kind but images, video, and audio.');

		foreach (['?kind=text', '?page=0', '?per=101', '?search[]=x'] as $query) {
			$this->assertSame(400, $this->send('GET', "/media{$query}")->getStatusCode(), $query);
		}
	}

	public function testFiltersByMissingAltTextAndSearchesMetadata(): void
	{
		$this->site();

		$names = fn (string $query): array => array_column((array) ($this->media($query)['files'] ?? []), 'name');

		$this->assertSame(['new-photo.png', 'old.png'], $names('?missing=alt'));
		$this->assertSame(200, $this->patch('2026/new-photo.png', ['alt' => 'A red kite over the hill'])->getStatusCode());
		$this->assertSame(['old.png'], $names('?missing=alt'), 'The list follows a save at once.');
		$this->assertSame(['new-photo.png'], $names('?search=KITE'), 'Alt text is searched.');
		$this->assertSame(400, $this->send('GET', '/media?missing=caption')->getStatusCode());
	}

	public function testNothingBesideAnEntryIsMedia(): void
	{
		$this->site();

		$this->assertSame(404, $this->send('GET', '/media/_content/trip/beach.png')->getStatusCode(), 'A file beside an entry isn\'t in the library (D-294).');
		$this->assertSame(404, $this->patch('_content/trip/beach.png', ['alt' => 'Sand and sea'])->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/media/_content/trip/beach.png.json');
	}

	public function testChecksWhoMayUseMedia(): void
	{
		$this->site(['author']);

		$this->assertSame(200, $this->send('GET', '/media')->getStatusCode(), 'An author may choose media.');
	}

	public function testDescribesOneLibraryFile(): void
	{
		$this->site();

		$file = self::json($this->send('GET', '/media/2026/new-photo.png'));

		$this->assertSame(['/media/2026/new-photo.png', 'new-photo.png', 'image/png'], [$file['reference'] ?? null, $file['name'] ?? null, $file['mime'] ?? null]);

		foreach (['notes.txt', '.hidden.png', '2026/missing.png', '../user/content/trip/beach.png'] as $path) {
			$this->assertSame(404, $this->send('GET', "/media/{$path}")->getStatusCode(), $path);
		}
	}

	/**
	 * @param positive-int $width
	 * @param positive-int $height
	 */
	private function png(string $path, int $width, int $height): void
	{
		$image = imagecreatetruecolor($width, $height);
		$this->assertNotFalse($image);
		$this->writeTemporaryFile($path, '');
		imagepng($image, $this->temporaryDirectory() . '/' . $path);
	}

	public function testListsOneItemPerImageWithItsSizes(): void
	{
		$this->png('user/media/2019/photo.png', 60, 40);
		$this->png('user/media/2019/photo-30x20.png', 30, 20);
		$this->png('user/media/2019/photo-15x10.png', 15, 10);
		$this->writeTemporaryFile('user/data/media/2019/photo.png.json', '{"alt": "A photo", "renditions": {"2019/photo-15x10.png": {"width": 15, "height": 10}}, "id": "0199b6e2-0000-7000-8000-0000000000aa"}');
		$this->writeTemporaryFile('user/content/uses-size.md', "---\ntitle: Uses a Size\nauthors: jane\n---\n![](/media/2019/photo-30x20.png)\n");
		$this->site();

		$list  = $this->media('?search=2019/photo');
		$files = is_array($list['files'] ?? null) ? $list['files'] : [];

		$this->assertSame(['photo.png'], array_column($files, 'name'), 'Sizes aren\'t items of their own (D-488).');
		$this->assertSame([2], array_column($files, 'sizeCount'));

		$image = self::json($this->send('GET', '/media/2019/photo.png'));
		$sizes = is_array($image['sizes'] ?? null) ? $image['sizes'] : [];

		$this->assertSame(['2019/photo-15x10.png', '2019/photo-30x20.png'], array_column($sizes, 'path'));
		$this->assertSame([15, 30], array_column($sizes, 'width'), 'Smallest first.');
		$this->assertNull($image['original'] ?? null);
		$this->assertSame(['Uses a Size'], array_column(is_array($image['usedIn'] ?? null) ? $image['usedIn'] : [], 'title'), 'A size\'s uses are the image\'s.');

		$size = self::json($this->send('GET', '/media/2019/photo-30x20.png'));

		$this->assertSame(['2019/photo.png', 'A photo', false], [is_array($size['original'] ?? null) ? $size['original']['path'] ?? null : null, $size['alt'] ?? null, is_array($size['may'] ?? null) ? $size['may']['edit'] ?? null : null], 'A size goes by its image\'s details.');
		$this->assertSame(422, $this->patch('2019/photo-30x20.png', ['alt' => 'Its own'])->getStatusCode());

		$this->assertSame(200, $this->remove('2019/photo-15x10.png')->getStatusCode());
		$this->assertStringNotContainsString('photo-15x10', (string) file_get_contents($this->temporaryDirectory() . '/user/data/media/2019/photo.png.json'), 'A deleted size leaves its image\'s list.');

		$deleted = self::json($this->remove('2019/photo.png'));

		$this->assertSame(['2019/photo-30x20.png'], $deleted['sizes'] ?? null, 'An image goes with its sizes.');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/media/2019/photo-30x20.png');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/media/2019/photo.png.json');
	}

	/**
	 * Uploads a file with the multipart field `file`, as the browser would.
	 */
	private function upload(string $name, string $contents, int $error = UPLOAD_ERR_OK): ResponseInterface
	{
		$token     = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';
		$temporary = $this->writeTemporaryFile('uploads/' . bin2hex(random_bytes(4)), $contents);
		$request   = Request::create('https://example.test/admin/api/media', 'POST', ['Origin' => 'https://example.test', 'Sec-Fetch-Site' => 'same-origin', 'X-CSRF-Token' => is_string($token) ? $token : ''], '', ['REMOTE_ADDR' => '203.0.113.5'])
			->withCookieParams(['__Host-blush_session' => (string) $this->cookie])
			->withUploadedFiles(['file' => new UploadedFile($temporary, strlen($contents), $error, $name, 'application/octet-stream')]);

		return $this->app->container()->make(Kernel::class)->handle($request);
	}

	public function testUploadsIntoTheLibrary(): void
	{
		$this->site();

		$png  = (string) base64_decode(self::PNG, true);
		$list = $this->media();

		$this->assertSame(['extensions' => ['apng', 'avif', 'gif', 'jpeg', 'jpg', 'png', 'webp', 'mp3', 'oga', 'ogg', 'wav', 'm4v', 'mp4', 'ogv', 'webm', 'pdf', 'vtt']], array_diff_key(is_array($list['upload'] ?? null) ? $list['upload'] : [], ['limit' => true]), 'The picker learns what it may upload.');

		$first = $this->upload('My Holiday (1).PNG', $png);
		$file  = self::json($first);

		$folder = $file['folder'] ?? null;

		$this->assertSame(201, $first->getStatusCode());
		$this->assertIsString($folder);
		$this->assertMatchesRegularExpression('#^\d{4}/\d{2}$#', $folder, 'Filed by year and month.');
		$this->assertSame(['My-Holiday-1.png', 'image', 1], [$file['name'] ?? null, $file['kind'] ?? null, $file['width'] ?? null], 'A name safe for a URL, with its extension lowercased.');
		$this->assertFileExists($this->temporaryDirectory() . "/user/media/{$folder}/My-Holiday-1.png");
		$id = $file['id'] ?? null;

		$this->assertIsString($id);
		$this->assertTrue(Uuid::isValid($id), 'Every upload has an id (D-487).');
		$this->assertSame($id, array_last((array) json_decode((string) file_get_contents($this->temporaryDirectory() . "/user/data/media/{$folder}/My-Holiday-1.png.json"), true)), 'Kept last, after the owner.');

		$again = self::json($this->upload('My Holiday (1).png', $png));

		$this->assertSame('My-Holiday-1-2.png', $again['name'] ?? null, 'Nothing is replaced.');
		$this->assertSame("/media/{$folder}/My-Holiday-1-2.png", $again['reference'] ?? null);

		$files = $this->media()['files'] ?? null;

		$this->assertIsArray($files);
		$this->assertSame('My-Holiday-1-2.png', is_array($files[0] ?? null) ? $files[0]['name'] ?? null : null, 'It\'s the newest in the library.');
	}

	public function testRefusesWhatTheLibraryDoesNotTake(): void
	{
		$this->site();

		$this->assertSame(422, $this->upload('notes.txt', 'plain text')->getStatusCode(), 'Not a library type.');
		$this->assertSame(422, $this->upload('page.png', '<html><script>alert(1)</script></html>')->getStatusCode(), 'Not what its name says.');

		$svg = $this->upload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

		$this->assertSame(422, $svg->getStatusCode(), 'SVG is never uploaded (D-497).');
		$this->assertStringContainsString('For icons, add an icon pack.', (string) $svg->getBody());
		$this->assertSame(422, $this->upload('logo.png', '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"></svg>')->getStatusCode(), 'Nor under another name.');
		$this->assertSame(413, $this->upload('big.png', '', UPLOAD_ERR_INI_SIZE)->getStatusCode());
		$this->assertSame([], glob($this->temporaryDirectory() . '/user/media/*/*/{.,}*.png', GLOB_BRACE) ?: [], 'Nothing is left behind.');
		$this->assertSame(['.hidden.png'], array_map(basename(...), glob($this->temporaryDirectory() . '/user/media/.*.png') ?: []));

		$this->assertSame('file.png', MediaUploadController::safeName('../../.png'));
		$this->assertSame('etc-passwd.svg', MediaUploadController::safeName('/etc passwd.SVG'));
		$this->assertSame('shell-php.jpg', MediaUploadController::safeName('shell.php.jpg'), 'No second extension (D-499).');
		$this->assertSame('photo-2024-v2.png', MediaUploadController::safeName('photo. 2024..v2.png'));
		$this->assertTrue(MediaUploads::refuses('Text/HTML'));
		$this->assertTrue(MediaUploads::refuses('application/vnd.ms-word.document.macroEnabled.12'));
		$this->assertFalse(MediaUploads::refuses('image/png'));

		$shell = $this->upload('shell.php.png', (string) base64_decode(self::PNG, true));

		$this->assertSame(201, $shell->getStatusCode());
		$this->assertStringContainsString('shell-php.png', (string) $shell->getBody());
	}

	public function testFollowsTheUploadRules(): void
	{
		$this->writeSettings((string) json_encode(['media' => ['uploads' => [
			'path'  => '{kind}/{year}',
			'kinds' => ['image' => ['path' => 'pics/{kind}', 'maxSize' => 1], 'audio' => ['enabled' => false]]
		]]]));
		$this->site();

		$png  = (string) base64_decode(self::PNG, true);
		$year = date('Y');
		$list = $this->media();

		$this->assertNotContains('mp3', is_array($list['upload'] ?? null) && is_array($list['upload']['extensions'] ?? null) ? $list['upload']['extensions'] : [], 'A kind turned off isn\'t offered.');
		$this->assertSame('pics/images', self::json($this->upload('a.png', $png))['folder'] ?? null, 'A kind\'s own path.');
		$this->assertSame("documents/{$year}", self::json($this->upload('rider.pdf', "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n"))['folder'] ?? null, 'Every kind\'s path, with {kind}.');
		$this->assertSame(413, $this->upload('big.png', $png . str_repeat("\0", 1024 * 1024))->getStatusCode(), 'Larger than the kind\'s largest.');
		$this->assertSame(422, $this->upload('song.mp3', 'ID3')->getStatusCode(), 'A kind turned off.');

		$this->writeSettings((string) json_encode(['media' => ['uploads' => ['enabled' => false]]]));
		// The settings are read at boot; the session carries over.
		$this->app = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test', 'APP_SECRET' => str_repeat('s', 64)]);
		$this->app->boot();

		$this->assertSame(403, $this->upload('b.png', $png)->getStatusCode(), 'Every upload turned off.');
		$this->assertNull($this->media()['upload'] ?? null, 'Nothing to upload.');
	}

	private function remove(string $path): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send('DELETE', "/media/{$path}", '', ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}

	public function testChecksWhoMayUpload(): void
	{
		$this->site(['member']);

		$this->assertSame(403, $this->upload('photo.png', (string) base64_decode(self::PNG, true))->getStatusCode());
	}

	public function testMediaIsChangedByWhoseItIs(): void
	{
		$this->site(['contributor']);

		$png  = (string) base64_decode(self::PNG, true);
		$mine = self::json($this->upload('mine.png', $png));
		$path = sprintf('%s/mine.png', is_string($mine['folder'] ?? null) ? $mine['folder'] : '');

		$this->assertSame($this->janeId(), $mine['owner'] ?? null, 'The uploader is recorded, by id (D-668).');
		$upload = $this->media()['upload'] ?? null;
		$offers = is_array($upload) && is_array($upload['extensions'] ?? null) ? $upload['extensions'] : [];

		$this->assertSame(['png'], array_values(array_filter(['png', 'mp4', 'pdf'], static fn (string $extension): bool => in_array($extension, $offers, true))), 'Images only.');
		$this->assertSame(403, $this->upload('clip.mp4', 'not a video')->getStatusCode(), 'Not a kind it may upload.');

		$own = self::json($this->send('GET', "/media/{$path}"));

		$this->assertSame(['username' => 'jane', 'name' => 'jane'], $own['uploader'] ?? null);
		$this->assertSame(['edit' => true, 'delete' => false, 'addArtwork' => false], $own['may'] ?? null);
		$this->assertSame(200, $this->patch($path, ['set' => ['alt' => 'Mine']])->getStatusCode(), 'Its own.');
		$this->assertSame(403, $this->patch('2020/old.png', ['set' => ['alt' => 'Old']])->getStatusCode(), 'No owner is anyone\'s.');
		$this->assertSame(403, $this->remove($path)->getStatusCode(), 'No deleting.');
		$this->assertSame(['mine.png'], array_column(is_array($this->media('?mine=1')['files'] ?? null) ? $this->media('?mine=1')['files'] : [], 'name'), 'Only theirs.');
	}

	public function testDeletesMediaSayingWhereItsUsed(): void
	{
		$this->writeTemporaryFile('user/content/notes.md', "---\ntitle: Notes\nauthors: jane\n---\n![Old](/media/2020/old.png)\n");
		$this->writeTemporaryFile('user/data/media/2020/old.png.json', '{"alt": "Old"}');
		$this->site(['editor']);

		$old = self::json($this->send('GET', '/media/2020/old.png'));

		$this->assertNull($old['uploader'] ?? null);
		$this->assertSame([['id' => $this->idOf('notes.md'), 'path' => 'notes.md', 'title' => 'Notes', 'type' => 'page', 'typeLabel' => 'Page']], $old['usedIn'] ?? null, 'Where it\'s used.');
		$this->assertSame(200, $this->remove('2020/old.png')->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/media/2020/old.png');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/media/2020/old.png.json', 'Its details go with it.');
		$this->assertSame(404, $this->send('GET', '/media/2020/old.png')->getStatusCode());
	}

	/**
	 * Changes a library file's metadata, as the Media screen does.
	 *
	 * @param array<mixed> $data
	 */
	private function patch(string $path, array $data): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send('PATCH', "/media/{$path}", json_encode($data) ?: '', ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}

	public function testKeepsAltTextAndCaptionsOutsideTheMediaFolder(): void
	{
		$this->site();

		$file = self::json($this->send('GET', '/media/2026/new-photo.png'));

		$this->assertSame(['', ''], [$file['alt'] ?? null, $file['caption'] ?? null], 'None yet.');

		$saved = $this->patch('2026/new-photo.png', ['alt' => "  A red\nsquare. ", 'caption' => 'The first one']);
		$data  = $this->temporaryDirectory() . '/user/data/media/2026/new-photo.png.json';

		$this->assertSame(200, $saved->getStatusCode(), (string) $saved->getBody());
		$this->assertSame(['A red square.', 'The first one'], [self::json($saved)['alt'] ?? null, self::json($saved)['caption'] ?? null], 'On one line, trimmed.');
		$this->assertMatchesRegularExpression('/\A\{\n    "alt": "A red square\.",\n    "caption": "The first one",\n    "id": "[0-9a-f-]{36}"\n\}\n\z/', (string) file_get_contents($data), 'A new file is JSON (D-490), with an id, last (D-675).');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/media/2026/new-photo.png.json', 'Never beside the file.');

		$files = $this->media()['files'] ?? null;

		$this->assertIsArray($files);
		$this->assertSame('A red square.', is_array($files[0] ?? null) ? $files[0]['alt'] ?? null : null, 'The library lists it.');

		// One changed, the other kept; keys it doesn't know stay as written.
		file_put_contents($data, '{"credit": "Jane", "alt": "A red square.", "caption": "The first one"}');
		$this->patch('2026/new-photo.png', ['caption' => '']);

		$kept = json_decode((string) file_get_contents($data), true);

		$this->assertSame(['credit' => 'Jane', 'alt' => 'A red square.'], is_array($kept) ? array_diff_key($kept, ['id' => true]) : null, 'With the id it gets when it\'s saved (D-675).');

		$this->patch('2026/new-photo.png', ['alt' => '']);
		$this->assertSame(['credit' => 'Jane'], array_diff_key((array) json_decode((string) file_get_contents($data), true), ['id' => true]));

		unlink($data);
		$this->writeTemporaryFile('user/data/media/2020/old.png.json', '{"$schema": "../../../media.schema.json", "credit": "Sam"}');
		$this->patch('2020/old.png', ['alt' => 'Old']);

		$this->assertSame(['$schema' => '../../../media.schema.json', 'credit' => 'Sam', 'alt' => 'Old'], array_diff_key((array) json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/media/2020/old.png.json'), true), ['id' => true]), 'JSON stays JSON, its schema key kept (D-491).');

		$this->patch('2020/old.png', ['alt' => '']);
		$this->patch('2026/new-photo.png', ['alt' => 'Back', 'caption' => '']);
		$this->writeTemporaryFile('user/data/media/2026/new-photo.png.json', '{"$schema": "../../../media.schema.json", "alt": "Back", "id": "0199b6e2-0000-7000-8000-0000000000ab"}');
		$this->assertSame('Back', self::json($this->send('GET', '/media/2026/new-photo.png'))['alt'] ?? null);
		$this->patch('2026/new-photo.png', ['alt' => '']);

		$this->assertSame(['$schema' => '../../../media.schema.json', 'id' => '0199b6e2-0000-7000-8000-0000000000ab'], json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/media/2026/new-photo.png.json'), true), 'With nothing else to say, it keeps its id: the file\'s own (D-487, D-675).');
	}

	public function testChecksChangesToMetadata(): void
	{
		$this->site();

		$this->assertSame(422, $this->patch('2026/new-photo.png', ['alt' => ['not', 'text']])->getStatusCode());
		$this->assertSame(400, $this->patch('2026/new-photo.png', ['set' => 'x'])->getStatusCode());
		$this->assertSame(404, $this->patch('2026/missing.png', ['alt' => 'x'])->getStatusCode());
		$this->assertSame(404, $this->patch('notes.txt', ['alt' => 'x'])->getStatusCode());

		$this->writeTemporaryFile('user/data/media/2026/broken.png.json', '{"alt": [');
		$this->assertSame('', self::json($this->send('GET', '/media/2026/new-photo.png'))['alt'] ?? null, 'Its own file, not a broken one.');
	}

	public function testOnlyThoseWhoMayUploadChangeMetadata(): void
	{
		$this->site(['contributor']);

		$this->assertSame(403, $this->patch('2026/new-photo.png', ['alt' => 'x'])->getStatusCode());
	}

	public function testAnUploadDoesNotTakeOnLeftoverMetadata(): void
	{
		$this->site();

		$folder = date('Y/m');

		$this->writeTemporaryFile("user/data/media/{$folder}/gone.png.json", '{"alt": "Someone else\'s"}');

		$this->assertSame('gone-2.png', self::json($this->upload('gone.png', (string) base64_decode(self::PNG, true)))['name'] ?? null);
	}

	public function testDescribesTheIcons(): void
	{
		$this->site();

		$icons = self::json($this->send('GET', '/icons'))['icons'] ?? null;

		$this->assertIsArray($icons);

		$house = array_find($icons, static fn (mixed $icon): bool => is_array($icon) && ($icon['name'] ?? null) === 'house');

		$this->assertIsArray($house, 'Core icons use their short names.');
		$this->assertSame('Home', $house['label'] ?? null, 'Labels come from the catalog.');
		$this->assertContains('home', is_array($house['keywords'] ?? null) ? $house['keywords'] : []);
		$this->assertStringContainsString('<svg', is_string($house['svg'] ?? null) ? $house['svg'] : '');
		$this->assertSame(['places', null], [$house['category'] ?? null, $house['source'] ?? null], 'A core icon has a category and no source.');

		$uncategorized = array_filter($icons, static fn (mixed $icon): bool => is_array($icon) && is_string($icon['name'] ?? null) && ! str_contains($icon['name'], '/') && ($icon['category'] ?? null) === null);

		$this->assertSame([], array_column($uncategorized, 'name'), 'Every core icon has a category in categories.json.');

		$logo = array_find($icons, static fn (mixed $icon): bool => is_array($icon) && ($icon['name'] ?? null) === 'site/logo');

		$this->assertIsArray($logo, 'A theme\'s icons are named in full.');
		$this->assertSame([null, ['kind' => 'theme', 'label' => 'Test Site']], [$logo['category'] ?? null, $logo['source'] ?? null], 'The rest are grouped by where they come from.');
	}
}
