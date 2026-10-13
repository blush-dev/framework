<?php

/**
 * Media items tests.
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
use Blush\Content\Entries;
use Blush\Core\Application;
use Blush\Media\Index\MediaRecord;
use Blush\Media\MediaItem;
use Blush\Media\MediaItems;
use Blush\Media\MediaValues;
use Blush\View\HtmlAttributes;
use Blush\View\InvalidHtmlAttribute;
use Blush\View\ViewFactory;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\WritesContentConfig;

#[CoversClass(MediaItems::class)]
#[CoversClass(MediaItem::class)]
#[CoversClass(MediaValues::class)]
#[CoversClass(HtmlAttributes::class)]
final class MediaItemsTest extends TestCase
{
	use BootsScratchSite;
	use WritesContentConfig;

	private const string PHOTO = '0199b6e2-7f3a-7c41-9d2e-000000000001';

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
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

	/**
	 * A photo with one smaller size, a sound, and a post featuring the
	 * photo.
	 */
	private function site(): Application
	{
		$this->png('user/media/2019/photo.png', 60, 40);
		$this->png('user/media/2019/photo-30x20.png', 30, 20);
		$this->writeTemporaryFile('user/media/2019/song.mp3', 'ID3');
		$this->writeTemporaryFile('user/data/media/2019/photo.png.json', '{"alt": "A photo", "id": "' . self::PHOTO . '"}');
		$this->contentConfig(['types' => ['post' => ['kind' => 'collection']]]);
		$this->writeTemporaryFile('user/content/_post/hello.md', "---\nid: 0199b6e2-7f3a-7c41-9d2e-0000000000c1\ntitle: Hello\npublished: 2026-01-01\nimage: " . self::PHOTO . "\n---\nBody");

		$app = $this->scratchApplication();
		$app->boot();

		return $app;
	}

	public function testFindsAFileOnceAWayItsReached(): void
	{
		$container = $this->site()->container();
		$hello     = $container->make(Entries::class)->named('post', 'hello');
		$media     = $container->make(MediaItems::class);
		$photo     = $media->find(self::PHOTO);

		$this->assertNotNull($photo);
		$this->assertSame(60, $photo->width);
		$this->assertSame('A photo', $photo->alt());
		$this->assertSame(['2019/photo-30x20.png'], array_map(static fn (MediaRecord $size): string => $size->key, $photo->sizes()), 'Its sizes, smallest first.');
		$this->assertSame($photo, $media->find(self::PHOTO), 'Kept for the request (D-807).');
		$this->assertSame($photo, $hello?->media('image'), 'An entry\'s field is the same item (D-814).');
		$this->assertNull($media->find('0199b6e2-7f3a-7c41-9d2e-00000000ffff'));
		$this->assertNull($media->find(''));
		$this->assertNull($media->find(['not', 'a', 'value']));
	}

	public function testPrintsAnImage(): void
	{
		$media = $this->site()->container()->make(MediaItems::class);
		$photo = $media->find(self::PHOTO);

		$this->assertNotNull($photo);
		$this->assertMatchesRegularExpression(
			'#^<img src="[^"]*/2019/photo\.png" width="60" height="40" alt="A photo" srcset="[^"]*/2019/photo-30x20\.png 30w, [^"]*/2019/photo\.png 60w" loading="lazy" decoding="async">$#',
			(string) $photo->img(),
			'Its size, the library\'s alt text, and a srcset of its sizes.'
		);
		$html = (string) $photo->img(alt: '', loading: 'eager', class: 'hero', sizes: '100vw', dataPhotoId: 7, title: null);

		$this->assertStringContainsString(' alt="" ', $html, 'Decorative, when the page says so.');
		$this->assertStringEndsWith(' loading="eager" decoding="async" class="hero" sizes="100vw" data-photo-id="7">', $html, 'Attributes by name, as link() takes them.');
		$this->assertSame('', (string) $media->find('/user/media/2019/song.mp3')?->img(), 'Only an image is an <img>.');

		$this->expectException(InvalidHtmlAttribute::class);
		$photo->img('hero');
	}

	public function testEveryTemplateHasTheLibrary(): void
	{
		$container = $this->site()->container();

		$this->assertSame($container->make(MediaItems::class), $container->make(ViewFactory::class)->fragment()->shared['media'] ?? null, 'As `$media` (D-814).');
	}
}
