<?php

/**
 * Media tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\InvalidConfig;
use Blush\Console\Commands\PublishMedia;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Application;
use Blush\Core\Paths;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Media\MediaConfig;
use Blush\Media\MediaController;
use Blush\Media\MediaFile;
use Blush\Media\MediaResolver;
use Blush\Media\MediaRoutes;
use Blush\Media\MediaServiceProvider;
use Blush\Support\Filesystem;
use Blush\Tests\BootsScratchSite;

#[CoversClass(MediaConfig::class)]
#[CoversClass(MediaFile::class)]
#[CoversClass(MediaResolver::class)]
#[CoversClass(MediaController::class)]
#[CoversClass(MediaRoutes::class)]
#[CoversClass(MediaServiceProvider::class)]
#[CoversClass(PublishMedia::class)]
#[CoversClass(Filesystem::class)]
final class MediaTest extends TestCase
{
	use BootsScratchSite;

	protected function setUp(): void
	{
		$image = imagecreatetruecolor(5, 2);
		$this->assertNotFalse($image);

		foreach (['user/media/2019/cat.png', 'user/content/_post/hello/photo.png', 'user/media/.hidden/cat.png'] as $path) {
			$this->writeTemporaryFile($path, '');
			imagepng($image, $this->temporaryDirectory() . '/' . $path);
		}

		$this->writeTemporaryFile('user/media/icon.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>');
		$this->writeTemporaryFile('user/media/script.php', '<?php echo 1;');
		$this->writeTemporaryFile('user/content/_post/hello/index.md', "---\ntitle: Hello\n---\n");
	}

	private function resolver(string $url = '/media'): MediaResolver
	{
		return new MediaResolver(Paths::fromRoot($this->temporaryDirectory()), new MediaConfig($url));
	}

	private function site(): Application
	{
		$app = $this->scratchApplication();
		$app->boot();

		return $app;
	}

	public function testResolvesMediaReferences(): void
	{
		$media = $this->resolver();
		$cat   = $media->resolve('/media/2019/cat.png?v=2#x');

		$this->assertNotNull($cat);
		$this->assertSame('/media/2019/cat.png', $cat->url);
		$this->assertSame('image/png', $cat->mime);
		$this->assertSame('image', $cat->type());
		$this->assertTrue($cat->isImage());
		$this->assertSame([5, 2], [$cat->width, $cat->height]);
		$this->assertSame($this->temporaryDirectory() . '/user/media/2019/cat.png', $cat->path);
		$this->assertSame('/media/2019/cat.png', $media->resolve('/user/media/2019/cat.png')?->url);
		$this->assertSame('/media/2019/cat.png', $media->resolve('user/media/2019/cat.png')?->url, 'A relative path is from the site root.');
		$this->assertSame('/media/2019/cat.png', $media->fromKey('2019/cat.png')?->url);

		$svg = $media->resolve('/media/icon.svg');

		$this->assertSame('image/svg+xml', $svg?->mime);
		$this->assertNull($svg->width);

		$refused = [
			'https://example.com/cat.png',
			'//cdn.example/cat.png',
			'data:image/png;base64,AAAA',
			'#top',
			'',
			'/media/missing.png',
			'/media/script.php',
			'/media/.hidden/cat.png',
			'/media/../content/_post/hello/photo.png',
			'../../../.env',
			'/other/cat.png',
			// Media is only ever in `user/media`, never beside entries (D-294).
			'photo.png',
			'/media/_content/_post/hello/photo.png',
			'/media/_content/../media/2019/cat.png'
		];

		foreach ($refused as $reference) {
			$this->assertNull($media->resolve($reference), $reference);
		}

		$this->assertNull($media->fromKey('_content/_post/hello/photo.png'));

		$this->assertNull($media->fromUrl('/user/media/2019/cat.png'));
		$this->assertSame('/user/media/2019/cat.png', $this->resolver('/user/media/')->resolve('/user/media/2019/cat.png')?->url);
	}

	public function testMediaUrlsMustBeSitePaths(): void
	{
		foreach (['/', '', '/media/../x', '/a b', 'https://cdn.example.com'] as $url) {
			try {
				new MediaConfig($url);
				$this->fail("Expected \"{$url}\" to be rejected.");
			} catch (InvalidConfig) {
				$this->addToAssertionCount(1);
			}
		}

		$config = MediaConfig::fromArray(['url' => 'files/', 'types' => ['image/png']]);

		$this->assertSame('/files', $config->url);
		$this->assertTrue($config->allows('IMAGE/PNG'));
		$this->assertFalse($config->allows('image/jpeg'));
		$this->assertSame(['url' => '/files', 'types' => ['image/png'], 'autoIndex' => true, 'uploads' => ['enabled' => true, 'maxSize' => null, 'path' => '{year}/{month}', 'kinds' => []], 'addArtwork' => false], $config->toArray());
	}

	public function testStreamsMediaWithRanges(): void
	{
		$kernel = $this->site()->container()->make(Kernel::class);

		$full = $kernel->handle(Request::create('/media/2019/cat.png'));

		$this->assertSame(200, $full->getStatusCode());
		$this->assertSame('image/png', $full->getHeaderLine('Content-Type'));
		$this->assertSame('nosniff', $full->getHeaderLine('X-Content-Type-Options'));
		$this->assertSame((string) filesize($this->temporaryDirectory() . '/user/media/2019/cat.png'), $full->getHeaderLine('Content-Length'));
		$this->assertSame('no-cache', $full->getHeaderLine('Cache-Control'), 'Media is checked every time (D-699).');
		$this->assertSame(304, $kernel->handle(Request::create('/media/2019/cat.png')->withHeader('If-Modified-Since', $full->getHeaderLine('Last-Modified')))->getStatusCode());

		$part = $kernel->handle(Request::create('/media/2019/cat.png')->withHeader('Range', 'bytes=0-7'));

		$this->assertSame(206, $part->getStatusCode());
		$this->assertSame("\x89PNG\r\n\x1a\n", (string) $part->getBody());

		$this->assertSame(404, $kernel->handle(Request::create('/media/_content/_post/hello/photo.png'))->getStatusCode(), 'Nothing beside an entry is served.');
		$this->assertSame('sandbox', $kernel->handle(Request::create('/media/icon.svg'))->getHeaderLine('Content-Security-Policy'));
		$this->assertSame(404, $kernel->handle(Request::create('/media/script.php'))->getStatusCode());
		$this->assertSame(404, $kernel->handle(Request::create('/media/_content/_post/hello/index.md'))->getStatusCode());
	}

	public function testPublishesMediaByLinkOrCopy(): void
	{
		$tester = new CommandTester($this->site()->container()->make(Console::class));
		$public = $this->temporaryDirectory() . '/public/media';

		$linked = $tester->run('media:publish');

		$this->assertTrue($linked->isSuccessful());
		$this->assertSame("Linked public/media to user/media.\n", $linked->output);
		$this->assertSame('../user/media', readlink($public));
		$this->assertFileExists("{$public}/2019/cat.png");
		$this->assertStringContainsString('is already linked', $tester->run('media:publish')->output);
		$this->assertSame(PublishMedia::HTACCESS, file_get_contents($this->temporaryDirectory() . '/user/media/.htaccess'), 'The linked folder keeps scripts from running (D-499).');

		$copied = $tester->run('media:publish --copy -v');

		$this->assertTrue($copied->isSuccessful());
		$this->assertFalse(is_link($public));
		$this->assertStringContainsString('Copied 2019/cat.png', $copied->output);
		$this->assertStringContainsString('Copied 2 file(s) to public/media; 0 already current.', $copied->output);
		$this->assertFileDoesNotExist("{$public}/script.php");
		$this->assertFileDoesNotExist("{$public}/.hidden/cat.png");
		$this->assertStringContainsString('Copied 0 file(s) to public/media; 2 already current.', $tester->run('media:publish --copy')->output);
		$this->assertSame(PublishMedia::HTACCESS, file_get_contents("{$public}/.htaccess"));

		file_put_contents("{$public}/.htaccess", "Options -Indexes\n");

		$own = $tester->run('media:publish --copy');

		$this->assertTrue($own->isSuccessful());
		$this->assertStringContainsString('public/media/.htaccess is your own', $own->errors);
		$this->assertSame("Options -Indexes\n", file_get_contents("{$public}/.htaccess"), 'A site\'s own .htaccess is left alone.');

		$blocked = $tester->run('media:publish');

		$this->assertSame(ExitCode::Failure, $blocked->exitCode);
		$this->assertStringContainsString('public/media already exists and isn\'t a link', $blocked->errors);
	}

	public function testPublishingNeedsAMediaFolder(): void
	{
		$this->removeTemporaryDirectory();
		$this->writeTemporaryFile('.env', '');

		$result = new CommandTester($this->site()->container()->make(Console::class))->run('media:publish');

		$this->assertSame(ExitCode::Failure, $result->exitCode);
		$this->assertStringContainsString('There is no media folder at user/media.', $result->errors);
	}

	public function testRelativePaths(): void
	{
		$filesystem = new Filesystem();

		$this->assertSame('../user/media', $filesystem->relative('/site/public', '/site/user/media'));
		$this->assertSame('media', $filesystem->relative('/site/public', '/site/public/media'));
		$this->assertSame('.', $filesystem->relative('/site', '/site'));
		$this->assertSame('../../b', $filesystem->relative('/a/x/y', '/a/b'));
	}
}
