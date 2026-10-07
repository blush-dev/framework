<?php

/**
 * Media artwork tests.
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
use Blush\Admin\MediaListController;
use Blush\Admin\MediaUploadController;
use Blush\Content\Lint\Linter;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Http\UploadedFile;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\MediaArtwork;
use Blush\Media\MediaMetadata;
use Blush\Media\MediaMetadataCheck;
use Blush\Tests\Fixtures\Media\TaggedAudioVideo;

#[CoversClass(MediaArtwork::class)]
#[CoversClass(MediaListController::class)]
#[CoversClass(MediaUploadController::class)]
#[CoversClass(MediaMetadata::class)]
#[CoversClass(MediaMetadataCheck::class)]
#[CoversClass(MediaLibrary::class)]
final class AdminMediaArtworkTest extends TestCase
{
	use BootsAdmin;

	/**
	 * A 1×1 PNG.
	 */
	private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	/**
	 * A value inside a decoded answer, or `null`.
	 */
	private static function at(mixed $data, string ...$keys): mixed
	{
		foreach ($keys as $key) {
			$data = is_array($data) ? $data[$key] ?? null : null;
		}

		return $data;
	}

	private static function png(): string
	{
		return (string) base64_decode(self::PNG, true);
	}

	private function site(bool $addArtwork = false): void
	{
		$this->writeTemporaryFile('config/media.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Media\\MediaConfig(uploads: new Blush\\Media\\MediaUploads(path: ''), addArtwork: " . ($addArtwork ? 'true' : 'false') . ");\n");
		$this->boot();
		$this->login();
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function put(string $path, array $data): ResponseInterface
	{
		return $this->send('PUT', "/media-artwork/{$path}", json_encode($data) ?: '', ['X-CSRF-Token' => $this->token()]);
	}

	private function token(): string
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return is_string($token) ? $token : '';
	}

	/**
	 * @return array<array-key, mixed>
	 */
	private function show(string $path): array
	{
		return self::json($this->send('GET', "/media/{$path}"));
	}

	/**
	 * A file's library id.
	 */
	private function id(string $path): string
	{
		$id = $this->show($path)['id'] ?? '';

		return is_string($id) ? $id : '';
	}

	/**
	 * A file's `artworkUrl` in the library's list.
	 */
	private function listed(string $name): mixed
	{
		$files = self::json($this->send('GET', '/media'))['files'] ?? [];
		$found = array_find(is_array($files) ? $files : [], static fn (mixed $file): bool => is_array($file) && ($file['name'] ?? null) === $name);

		return self::at($found, 'artworkUrl');
	}

	private function upload(string $name, string $contents): ResponseInterface
	{
		$temporary = $this->writeTemporaryFile('uploads/' . bin2hex(random_bytes(4)), $contents);
		$request   = Request::create('https://example.test/admin/api/media', 'POST', ['Origin' => 'https://example.test', 'Sec-Fetch-Site' => 'same-origin', 'X-CSRF-Token' => $this->token()], '', ['REMOTE_ADDR' => '203.0.113.5'])
			->withCookieParams(['__Host-blush_session' => (string) $this->cookie])
			->withUploadedFiles(['file' => new UploadedFile($temporary, strlen($contents), UPLOAD_ERR_OK, $name, 'application/octet-stream')]);

		return $this->app->container()->make(Kernel::class)->handle($request);
	}

	public function testAddsWhatAFileCarriesToTheLibraryOnce(): void
	{
		$this->writeTemporaryFile('user/media/album/one.mp3', TaggedAudioVideo::mp3(art: self::png()));
		$this->writeTemporaryFile('user/media/album/two.mp3', TaggedAudioVideo::mp3(art: self::png()));
		$this->site();

		$this->assertTrue(self::at(self::json($this->send('GET', '/media/album/one.mp3')), 'may', 'addArtwork'));
		$this->assertNull($this->show('album/one.mp3')['artwork'], 'None yet.');

		$one = self::json($this->put('album/one.mp3', ['from' => 'file']));

		$this->assertSame('album/one-artwork.png', self::at($one, 'artwork', 'image', 'path'));
		$this->assertSame('Artwork for Morning Song', self::at($one, 'artwork', 'image', 'title'), 'Titled for the file.');
		$this->assertSame(self::png(), file_get_contents($this->temporaryDirectory() . '/user/media/album/one-artwork.png'));
		$this->assertSame($this->id('album/one-artwork.png'), self::at($one, 'artwork', 'id'));

		$two = self::json($this->put('album/two.mp3', ['from' => 'file']));

		$this->assertSame('album/one-artwork.png', self::at($two, 'artwork', 'image', 'path'), 'The same picture is one image.');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/media/album/two-artwork.png');

		$image = $this->show('album/one-artwork.png');

		$this->assertSame(['album/one.mp3', 'album/two.mp3'], array_column(is_array($image['artworkFor'] ?? null) ? $image['artworkFor'] : [], 'path'), 'The image says which files show it.');
	}

	public function testLinksAndRemovesALibraryImage(): void
	{
		$this->writeTemporaryFile('user/media/song.mp3', TaggedAudioVideo::mp3());
		$this->site();
		$this->upload('cover.png', self::png());

		$cover = $this->id('cover.png');

		$this->assertFalse(self::at($this->show('song.mp3'), 'may', 'addArtwork'), 'What it carries isn\'t an image.');
		$this->assertSame(403, $this->put('song.mp3', ['from' => 'file'])->getStatusCode(), 'Refused.');
		$this->assertSame(422, $this->put('song.mp3', ['image' => '01890a5d-ac96-774b-bcce-b302099a8057'])->getStatusCode(), 'No such image.');
		$this->assertSame(422, $this->put('cover.png', ['image' => $cover])->getStatusCode(), 'Only sounds and videos.');
		$this->assertSame(400, $this->put('song.mp3', [])->getStatusCode());

		$linked = self::json($this->put('song.mp3', ['image' => $cover]));

		$this->assertSame('cover.png', self::at($linked, 'artwork', 'image', 'name'));
		$this->assertStringContainsString("\"artwork\": \"{$cover}\"", (string) file_get_contents($this->temporaryDirectory() . '/user/data/media/song.mp3.json'));

		$this->assertSame('/media/cover.png', $this->listed('song.mp3'), 'The library shows it as the thumbnail.');

		$removed = self::json($this->send('DELETE', '/media-artwork/song.mp3', '', ['X-CSRF-Token' => $this->token()]));

		$this->assertMatchesRegularExpression('#^/media-artwork/song\.mp3\?v=[0-9a-f]{8}$#', (string) $this->listed('song.mp3'), 'Else what it carries.');
		$this->assertNull($this->listed('cover.png'), 'An image is its own thumbnail.');

		$this->assertNull($removed['artwork']);
		$this->assertFileExists($this->temporaryDirectory() . '/user/media/cover.png', 'The image stays.');
	}

	public function testDeletingAnImageTakesItOffItsFiles(): void
	{
		$this->writeTemporaryFile('user/media/song.mp3', TaggedAudioVideo::mp3(art: self::png()));
		$this->site();
		$this->put('song.mp3', ['from' => 'file']);

		$this->assertSame(200, $this->send('DELETE', '/media/song-artwork.png', '', ['X-CSRF-Token' => $this->token()])->getStatusCode());
		$this->assertNull($this->show('song.mp3')['artwork']);
	}

	public function testLintReportsArtworkThatIsntThere(): void
	{
		$this->writeTemporaryFile('user/media/song.mp3', TaggedAudioVideo::mp3());
		$this->writeTemporaryFile('user/data/media/song.mp3.json', "{\"artwork\": \"01890a5d-ac96-774b-bcce-b302099a8057\", \"id\": \"01890a5d-ac96-774b-bcce-b302099a8058\"}\n");
		$this->writeTemporaryFile('user/media/tone.wav', TaggedAudioVideo::wav());
		$this->writeTemporaryFile('user/data/media/tone.wav.json', "{\"artwork\": \"cover\", \"id\": \"01890a5d-ac96-774b-bcce-b302099a8059\"}\n");
		$this->site();

		[, $found] = $this->app->container()->make(MediaMetadataCheck::class)->check();
		$fields    = static fn (string $path): array => array_map(static fn ($violation): string => $violation->field, $found[$path] ?? []);

		$this->assertContains(MediaMetadata::ARTWORK, $fields('user/data/media/song.mp3.json'));
		$this->assertContains(MediaMetadata::ARTWORK, $fields('user/data/media/tone.wav.json'));
		$this->assertNotContains(Linter::FILE, $fields('user/data/media/song.mp3.json'));
	}

	public function testUploadsAddTheirArtworkWhenTheSiteAsks(): void
	{
		$this->site(addArtwork: true);

		$this->assertSame(201, $this->upload('song.mp3', TaggedAudioVideo::mp3(art: self::png()))->getStatusCode());
		$this->assertSame('song-artwork.png', self::at($this->show('song.mp3'), 'artwork', 'image', 'name'));
	}

	public function testUploadsLeaveTheirArtworkByDefault(): void
	{
		$this->site();
		$this->upload('song.mp3', TaggedAudioVideo::mp3(art: self::png()));

		$this->assertNull($this->show('song.mp3')['artwork']);
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/media/song-artwork.png');
	}
}
