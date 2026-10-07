<?php

/**
 * Media directive tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Field\Field;
use Blush\Field\Fields\MediaField;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Media\MediaArtworkController;
use Blush\Media\MediaConfig;
use Blush\Media\MediaResolver;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\Media\TaggedAudioVideo;
use Blush\View\ViewContext;
use Blush\Directive\DirectiveName;
use Blush\Directive\DirectiveDefinition;
use Blush\Directive\Media\Audio;
use Blush\Markdown\MarkdownConfig;
use Blush\Media\MediaArtwork;
use Blush\Directive\Media\File;
use Blush\Directive\Media\MediaPreload;
use Blush\Directive\Media\Video;
use Blush\Directive\MediaProp;
use Blush\Directive\MarkdownDirectives;

#[CoversClass(Audio::class)]
#[CoversClass(MediaArtworkController::class)]
#[CoversClass(File::class)]
#[CoversClass(MediaPreload::class)]
#[CoversClass(Video::class)]
#[CoversClass(MediaProp::class)]
#[CoversClass(MarkdownDirectives::class)]
final class MediaDirectivesTest extends TestCase
{
	use BootsScratchSite;

	/**
	 * Returns three silent MPEG audio frames (1,251 bytes), which sniff as
	 * `audio/mpeg`.
	 */
	private static function mp3(): string
	{
		return str_repeat("\xFF\xFB\x90\x64" . str_repeat("\x00", 413), 3);
	}

	private function media(): MediaResolver
	{
		return new MediaResolver(Paths::fromRoot($this->temporaryDirectory()), new MediaConfig());
	}

	/**
	 * A video directive, its artwork read from the scratch site.
	 */
	private function video(AppConfig $config, string $src = '', string $poster = '', ?int $width = null): Video
	{
		$app = $this->scratchApplication();
		$app->boot();

		return new Video($this->media(), $config, $app->container()->make(MediaArtwork::class), new MarkdownConfig(), $src, $poster, width: $width);
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

	private function mp4(string $path): void
	{
		$this->writeTemporaryFile($path, "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom" . str_repeat("\x00", 64));
	}

	public function testFilesShowTheirFormatAndSize(): void
	{
		$cue = "WEBVTT\n\n00:00.000 --> 00:01.000\n";
		$this->writeTemporaryFile('user/media/notes.vtt', $cue . str_repeat('x', 1536 - strlen($cue)));

		$file = new File($this->media(), new AppConfig(), '/media/notes.vtt?v=2');

		$this->assertSame('notes.vtt', $file->name);
		$this->assertSame('VTT', $file->format);
		$this->assertSame('1.5 KB', $file->size);
		$this->assertSame('1,5 KB', new File($this->media(), new AppConfig(locale: 'de_DE'), '/media/notes.vtt')->size);

		// A translation's page: its locale, not the site's (D-466).
		$file->attach(new DirectiveName('blush', 'file'), [], context: new ViewContext(locale: 'de_DE'));

		$this->assertSame('1,5 KB', $file->size);

		// Not local media: no size.
		$remote = new File($this->media(), new AppConfig(), 'https://example.com/files/Annual%20report.pdf');

		$this->assertSame('Annual report.pdf', $remote->name);
		$this->assertSame('PDF', $remote->format);
		$this->assertSame('', $remote->size);
		$this->assertFalse(new File($this->media(), new AppConfig())->shouldRender());
	}

	public function testVideosTakeTheirPostersSize(): void
	{
		$this->png('user/media/poster.png', 16, 9);

		$video = $this->video(new AppConfig(locale: 'fr_CA'), '/media/clip.mp4', '/media/poster.png');

		$this->assertSame([16, 9], [$video->width, $video->height]);
		$this->assertSame('fr', $video->trackLang);
		$this->assertSame([640, null], [$this->video(new AppConfig(), '/media/clip.mp4', '/media/poster.png', width: 640)->width, $this->video(new AppConfig(), '/media/clip.mp4', '/media/poster.png', width: 640)->height]);
		$this->assertSame([null, null], [$this->video(new AppConfig(), '/media/clip.mp4')->width, $this->video(new AppConfig(), '/media/clip.mp4')->height]);
		$this->assertFalse($this->video(new AppConfig())->shouldRender());
	}

	public function testMediaPropsAreMediaFields(): void
	{
		$props = new DirectiveDefinition(new DirectiveName('blush', 'video'), Video::class)->props();
		$types = array_combine(
			array_map(static fn (Field $field): string => $field->name, $props),
			array_map(static fn (Field $field): string => $field->type(), $props)
		);

		// The services aren't props.
		$this->assertSame(
			['src' => 'media', 'poster' => 'media', 'track' => 'media', 'width' => 'number', 'height' => 'number', 'preload' => 'enum', 'loop' => 'bool', 'muted' => 'bool', 'label' => 'text'],
			$types
		);
		$this->assertInstanceOf(MediaField::class, $props[0]);

		$kinds = array_map(static fn (Field $field): ?string => $field instanceof MediaField ? $field->kind?->value : null, array_slice($props, 0, 3));

		$this->assertSame(['video', 'image', null], $kinds, 'A video plays videos, its poster is an image, and its track is any file.');
		$audio = new DirectiveDefinition(new DirectiveName('blush', 'audio'), Audio::class)->props();

		$this->assertSame('audio', ($audio[0] ?? null)?->toArray()['kind'] ?? null);
	}

	/**
	 * A gallery holds only images (D-529): text, other blocks, and
	 * directives it doesn't hold are left out, and the images in a
	 * paragraph with text are kept, each a figure.
	 */
	public function testAGalleryRendersOnlyItsImages(): void
	{
		$this->writeTemporaryFile('user/content/trip/index.md', <<<'MD'
			---
			title: Trip
			---
			:::gallery

			![First](/media/trip/poster.png)

			Stray text.

			## A heading

			::file{src=/media/trip/poster.png}

			Look: ![Second](/media/trip/poster.png) and [![Third](/media/trip/poster.png)](/about)
			![Fourth](/media/trip/poster.png)

			:::

			Outside it.
			MD);
		$this->png('user/media/trip/poster.png', 32, 18);

		$app = $this->scratchApplication(['APP_ENV' => 'development']);
		$app->boot();

		$html    = (string) $app->container()->make(Kernel::class)->handle(Request::create('/trip'))->getBody();
		$gallery = preg_match('#<div class="directive-gallery[^>]*>(.*?)</div>#s', $html, $match) === 1 ? $match[1] : '';

		$this->assertSame(4, substr_count($gallery, '<figure'));
		$this->assertStringContainsString('alt="Fourth"', $gallery);
		$this->assertStringNotContainsString('Stray', $gallery);
		$this->assertStringNotContainsString('Look', $gallery);
		$this->assertStringNotContainsString('<h2', $gallery);
		$this->assertStringNotContainsString('directive-file', $gallery);
		$this->assertStringNotContainsString('<p', $gallery);
		$this->assertStringContainsString('Outside it.', $html);
	}

	/**
	 * `:::audio` is a mistyped `::audio` (D-530): audio isn't a container,
	 * so the line doesn't open one, take the gallery's closing fence, or
	 * render; the gallery closes, and what follows it shows.
	 */
	public function testADirectiveWrittenInAFormItIsntRegisteredForDoesNothing(): void
	{
		$this->writeTemporaryFile('user/content/trip/index.md', <<<'MD'
			---
			title: Trip
			---
			:::gallery

			:::audio{src=/media/song.mp3}

			![First](/media/trip/poster.png)
			![Second](/media/trip/poster.png)

			:::

			After the gallery.
			MD);
		$this->png('user/media/trip/poster.png', 32, 18);
		$this->writeTemporaryFile('user/media/song.mp3', self::mp3());

		$app = $this->scratchApplication(['APP_ENV' => 'development']);
		$app->boot();

		$html    = (string) $app->container()->make(Kernel::class)->handle(Request::create('/trip'))->getBody();
		$gallery = preg_match('#<div class="directive-gallery[^>]*>(.*?)</div>#s', $html, $match) === 1 ? $match[1] : '';

		$this->assertSame(2, substr_count($gallery, '<figure'));
		$this->assertStringNotContainsString('directive-audio', $html);
		$this->assertStringContainsString('<p>After the gallery.</p>', $html);
	}

	/**
	 * A gallery never closed renders everything (D-530), rather than
	 * leaving out the rest of the entry.
	 */
	public function testAnUnclosedGalleryLeavesNothingOut(): void
	{
		$this->writeTemporaryFile('user/content/trip/index.md', <<<'MD'
			---
			title: Trip
			---
			:::gallery

			![First](/media/trip/poster.png)

			The rest of the entry.
			MD);
		$this->png('user/media/trip/poster.png', 32, 18);

		$app = $this->scratchApplication(['APP_ENV' => 'development']);
		$app->boot();

		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/trip'))->getBody();

		$this->assertStringContainsString('The rest of the entry.', $html);
	}

	public function testTheyRenderLibraryFiles(): void
	{
		$this->writeTemporaryFile('user/content/trip/index.md', <<<'MD'
			---
			title: Trip
			---
			::video[Launch]{src=/media/trip/clip.mp4 poster=/media/trip/poster.png track=/media/trip/clip.vtt muted}

			::audio{src=/media/song.mp3 preload=none loop}

			::file[The notes]{src=/media/trip/clip.vtt}

			::file{src=https://example.com/report.pdf}

			:::figure[The poster]
			![A poster](/media/trip/poster.png "Not a second caption")
			:::

			::video{src=missing.mp4}
			MD);
		$this->mp4('user/media/trip/clip.mp4');
		$this->png('user/media/trip/poster.png', 32, 18);
		$this->writeTemporaryFile('user/media/trip/clip.vtt', "WEBVTT\n");
		$this->writeTemporaryFile('user/media/song.mp3', self::mp3());

		$app = $this->scratchApplication(['APP_ENV' => 'development']);
		$app->boot();

		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/trip'))->getBody();

		$this->assertStringContainsString('<video class="directive-video__player" src="http://localhost/media/trip/clip.mp4" controls playsinline preload="metadata" poster="http://localhost/media/trip/poster.png" width="32" height="18" muted>', $html);
		$this->assertStringContainsString('<track class="directive-video__track" kind="captions" src="http://localhost/media/trip/clip.vtt" srclang="en" label="Captions" default>', $html);
		$this->assertStringContainsString('<a href="http://localhost/media/trip/clip.mp4">Download the video</a>', $html);
		$this->assertStringContainsString('<figcaption>Launch</figcaption>', $html);
		$this->assertStringContainsString('<audio class="directive-audio__player" src="http://localhost/media/song.mp3" controls preload="none" loop>', $html);
		$this->assertStringContainsString('<a class="directive-file__link" href="http://localhost/media/trip/clip.vtt" download>The notes</a>', $html);
		$this->assertStringContainsString('<span class="directive-file__details">(VTT, 7 B)</span>', $html);
		$this->assertStringContainsString('<a class="directive-file__link" href="https://example.com/report.pdf" download>report.pdf</a>', $html);
		$this->assertStringContainsString('<span class="directive-file__details">(PDF)</span>', $html);
		$this->assertMatchesRegularExpression('#<figure class="directive-figure">\s*<img width="32" height="18" src="http://localhost/media/trip/poster.png" alt="A poster" title="Not a second caption" />\s*<figcaption>The poster</figcaption>#', $html, 'The figure is the container; its image stands alone.');

		// A file that isn't there stays as written.
		$this->assertStringContainsString('<video class="directive-video__player" src="missing.mp4"', $html);
	}

	public function testMediaAndLinksBecomeFullUrlsForFeeds(): void
	{
		$this->writeTemporaryFile('config/media.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Media\\MediaConfig(url: '/user/media');\n");
		$this->writeTemporaryFile('config/app.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Core\\AppConfig(url: 'https://example.com', environment: Blush\\Core\\Environment::Development);\n");
		$this->writeTemporaryFile('user/media/audio/novas-anthem-001.mp3', self::mp3());
		$this->writeTemporaryFile('user/content/songs/index.md', <<<'MD'
			---
			title: Songs
			---
			::audio[Nova's Anthem]{src=user/media/audio/novas-anthem-001.mp3}

			::audio[Again]{src=/user/media/audio/novas-anthem-001.mp3}

			::file[Full]{src=https://example.com/user/media/audio/novas-anthem-001.mp3}

			:button[Listen]{url=/songs}

			:button[Relative]{url=elsewhere}

			:button[Away]{url=https://other.test/}
			MD);

		$app = $this->scratchApplication();
		$app->boot();

		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/songs'))->getBody();
		$full = 'https://example.com/user/media/audio/novas-anthem-001.mp3';

		$this->assertSame(2, substr_count($html, "<audio class=\"directive-audio__player\" src=\"{$full}\""));

		// A full URL on the site is still found, so its size shows.
		$this->assertStringContainsString("href=\"{$full}\" download>Full</a>", $html);
		$this->assertStringContainsString('(MP3, 1.2 KB)', $html);

		$this->assertStringContainsString('href="https://example.com/songs"', $html);
		$this->assertStringContainsString('href="elsewhere"', $html);
		$this->assertStringContainsString('href="https://other.test/"', $html);

		// With absolute links off, media keep their site path.
		$this->writeTemporaryFile('config/markdown.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Markdown\\MarkdownConfig(absoluteLinks: false);\n");

		$app = $this->scratchApplication();
		$app->boot();

		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/songs'))->getBody();

		$this->assertStringContainsString('<audio class="directive-audio__player" src="/user/media/audio/novas-anthem-001.mp3"', $html);
	}

	/**
	 * The `card` variant (D-575): the file's title, artist, album, and
	 * artwork, each a prop first; the artwork served at the artwork URL.
	 */
	public function testACardShowsWhatTheFileCarries(): void
	{
		$this->writeTemporaryFile('user/content/songs/index.md', <<<'MD'
			---
			title: Songs
			---
			::audio{src=/media/song.mp3 variant=card}

			::audio[Given]{src=/media/song.mp3 variant=card artist=Someone art=/media/cover.png}

			::audio{src=/media/bare.mp3 variant=card}
			MD);
		$this->writeTemporaryFile('user/media/song.mp3', TaggedAudioVideo::mp3());
		$this->writeTemporaryFile('user/media/bare.mp3', self::mp3());
		$this->png('user/media/cover.png', 8, 8);

		$app = $this->scratchApplication(['APP_ENV' => 'development']);
		$app->boot();

		$kernel = $app->container()->make(Kernel::class);
		$html   = (string) $kernel->handle(Request::create('/songs'))->getBody();

		$this->assertFalse($app->container()->make(Audio::class)->shouldRender());
		$this->assertMatchesRegularExpression('#<figure class="directive-audio directive-audio--card audio-card">\s*<figcaption class="audio-card__meta">\s*<span class="audio-card__title">Morning Song</span>\s*<span class="audio-card__by">The Harbors · Tides</span>#', $html);
		$this->assertMatchesRegularExpression('#<img class="audio-card__art" src="http://localhost/media-artwork/song\.mp3\?v=[0-9a-f]{8}" alt=""#', $html);
		$this->assertStringContainsString('<span class="audio-card__title">Given</span>', $html, 'The label is the title.');
		$this->assertStringContainsString('<span class="audio-card__by">Someone · Tides</span>', $html, 'A prop wins over the file.');
		$this->assertStringContainsString('<img class="audio-card__art" src="http://localhost/media/cover.png"', $html);
		$this->assertStringContainsString('label-volume="Volume"', $html);

		// A file that carries nothing: the player alone.
		$this->assertMatchesRegularExpression('#<figure class="directive-audio directive-audio--card audio-card">\s*<blush-audio-player#', $html);

		$artwork = $kernel->handle(Request::create('/media-artwork/song.mp3'));

		$this->assertSame(200, $artwork->getStatusCode());
		$this->assertSame('image/jpeg', $artwork->getHeaderLine('Content-Type'));
		$this->assertSame(str_repeat("\xAB", 2048), (string) $artwork->getBody());
		$this->assertSame(404, $kernel->handle(Request::create('/media-artwork/bare.mp3'))->getStatusCode());
		$this->assertSame(404, $kernel->handle(Request::create('/media-artwork/cover.png'))->getStatusCode());
	}

	/**
	 * A file's artwork (D-581): the library image its metadata names wins
	 * over the picture it carries, for a card and for a video's poster,
	 * which a video without one takes from what it carries, at its size.
	 */
	public function testArtworkIsTheLibraryImageAFileNames(): void
	{
		$this->writeTemporaryFile('user/content/songs/index.md', <<<'MD'
			---
			title: Songs
			---
			::audio{src=/media/song.mp3 variant=card}

			::video{src=/media/clip.mp4}

			::video{src=/media/linked.mp4}
			MD);
		$this->writeTemporaryFile('user/media/song.mp3', TaggedAudioVideo::mp3());
		$this->writeTemporaryFile('user/media/clip.mp4', TaggedAudioVideo::mp4());
		$this->writeTemporaryFile('user/media/linked.mp4', TaggedAudioVideo::mp4());
		$this->png('user/media/cover.png', 8, 8);
		$this->writeTemporaryFile('user/data/media/cover.png.json', "{\"id\": \"01890a5d-ac96-774b-bcce-b302099a8057\"}\n");
		$this->writeTemporaryFile('user/data/media/song.mp3.json', "{\"artwork\": \"01890A5D-AC96-774B-BCCE-B302099A8057\"}\n");
		$this->writeTemporaryFile('user/data/media/linked.mp4.json', "{\"artwork\": \"01890a5d-ac96-774b-bcce-b302099a8057\"}\n");

		$app = $this->scratchApplication(['APP_ENV' => 'development']);
		$app->boot();

		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/songs'))->getBody();

		$this->assertStringContainsString('<img class="audio-card__art" src="http://localhost/media/cover.png"', $html, 'Any case of the id.');
		$this->assertMatchesRegularExpression('#src="http://localhost/media/clip\.mp4"[^>]* poster="http://localhost/media-artwork/clip\.mp4\?v=[0-9a-f]{8}" width="1280" height="720"#', $html);
		$this->assertMatchesRegularExpression('#src="http://localhost/media/linked\.mp4"[^>]* poster="http://localhost/media/cover\.png" width="1280" height="720"#', $html);
	}
}
