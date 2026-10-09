<?php

/**
 * Embed tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Embed;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\InvalidConfig;
use Blush\Core\AppConfig;
use Blush\Core\Application;
use Blush\Embed\EmbedConfig;
use Blush\Embed\EmbedData;
use Blush\Embed\EmbedException;
use Blush\Embed\EmbedProvider;
use Blush\Embed\EmbedProviders;
use Blush\Embed\Embeds;
use Blush\Embed\EmbedType;
use Blush\Embed\Fetcher;
use Blush\Embed\OEmbedProvider;
use Blush\Embed\ProviderFactory;
use Blush\Embed\ProviderRegistrar;
use Blush\Embed\ProviderRegistry;
use Blush\Embed\ProviderType;
use Blush\Embed\Providers\CodePen;
use Blush\Embed\Providers\Flickr;
use Blush\Embed\Providers\SoundCloud;
use Blush\Embed\Providers\Spotify;
use Blush\Embed\Providers\Ted;
use Blush\Embed\Providers\TikTok;
use Blush\Embed\Providers\Twitch;
use Blush\Embed\Providers\Vimeo;
use Blush\Embed\Providers\YouTube;
use Blush\Embed\StreamFetcher;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\Embed\FixtureFetcher;

#[CoversClass(EmbedConfig::class)]
#[CoversClass(EmbedData::class)]
#[CoversClass(EmbedProvider::class)]
#[CoversClass(EmbedProviders::class)]
#[CoversClass(Embeds::class)]
#[CoversClass(OEmbedProvider::class)]
#[CoversClass(ProviderFactory::class)]
#[CoversClass(ProviderRegistrar::class)]
#[CoversClass(ProviderRegistry::class)]
#[CoversClass(ProviderType::class)]
#[CoversClass(CodePen::class)]
#[CoversClass(Flickr::class)]
#[CoversClass(SoundCloud::class)]
#[CoversClass(Spotify::class)]
#[CoversClass(StreamFetcher::class)]
#[CoversClass(Ted::class)]
#[CoversClass(TikTok::class)]
#[CoversClass(Twitch::class)]
#[CoversClass(Vimeo::class)]
#[CoversClass(YouTube::class)]
final class EmbedsTest extends TestCase
{
	use BootsScratchSite;

	private const string YOUTUBE = '{"type": "video", "title": "Never Gonna Give You Up", "provider_name": "YouTube", "width": 200, "height": 113, "thumbnail_url": "https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg", "html": "<iframe width=\\"200\\" height=\\"113\\" src=\\"https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed\\"></iframe>"}';

	private FixtureFetcher $fetcher;

	/**
	 * @param array<string, string> $responses
	 */
	private function app(array $responses = [], string $config = ''): Application
	{
		if ($config !== '') {
			$this->writeTemporaryFile('config/embed.php', "<?php\n\ndeclare(strict_types=1);\n\nuse Blush\\Embed\\EmbedConfig;\nuse Blush\\Embed\\OEmbedProvider;\n\nreturn {$config};\n");
		}

		$app = $this->scratchApplication();
		$app->boot();

		$this->fetcher = new FixtureFetcher($responses);
		$app->container()->instance(Fetcher::class, $this->fetcher);

		return $app;
	}

	public function testBuiltInProvidersFrameFromTheUrlAlone(): void
	{
		$cases = [
			'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1' => ['youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=1'],
			'https://youtu.be/dQw4w9WgXcQ'                   => ['youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
			'https://youtube.com/shorts/dQw4w9WgXcQ'         => ['youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
			'https://vimeo.com/76979871'                     => ['vimeo', 'https://player.vimeo.com/video/76979871?dnt=1'],
			'https://example.com/video'                      => ['', null],
			'javascript:alert(1)'                            => ['', null],
			'https://youtu.be/bad"id'                        => ['', null],
			'https://youtu.be/dQw4w9WgXcQ?si=abc&t=90'       => ['youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=90'],
			'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1m30s' => ['youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=90'],
			'https://www.youtube.com/embed/dQw4w9WgXcQ?start=3725' => ['youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=3725'],
			'https://youtu.be/dQw4w9WgXcQ?t=1h2m5s'          => ['youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=3725'],
			'https://youtu.be/dQw4w9WgXcQ?t=0'               => ['youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
			'https://youtu.be/dQw4w9WgXcQ?t=soon'            => ['youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
			'https://vimeo.com/76979871#t=2m5s'              => ['vimeo', 'https://player.vimeo.com/video/76979871?dnt=1#t=125s']
		];

		$providers = [new YouTube(), new Vimeo()];

		foreach ($cases as $url => [$provider, $src]) {
			$match = array_find($providers, static fn ($candidate): bool => $candidate->matches($url));

			$this->assertSame($provider, $match === null || $match->frame($url, null) === null ? '' : $match->name, $url);
			$this->assertSame($src, $match?->frame($url, null), $url);
		}
	}

	public function testProvidersMatchTheirSchemes(): void
	{
		$provider = new OEmbedProvider('example', 'Example', ['https://video.example.com/*', 'https://*.example.org/watch/*'], 'https://video.example.com/oembed?format=json');

		$this->assertTrue($provider->matches('https://video.example.com/v/1'));
		$this->assertTrue($provider->matches('http://video.example.com/v/1'));
		$this->assertTrue($provider->matches('https://tv.example.org/watch/2'));
		$this->assertFalse($provider->matches('https://video.example.net/v/1'));
		$this->assertFalse($provider->matches('https://evil.test/?https://video.example.com/v/1'));
		$this->assertSame('https://video.example.com/oembed?format=json&url=https%3A%2F%2Fvideo.example.com%2Fv%2F1&format=json', $provider->request('https://video.example.com/v/1'));

		// YouTube asks about the plain watch URL, or the short's.
		$this->assertSame('https://www.youtube.com/oembed?url=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3DdQw4w9WgXcQ&format=json', new YouTube()->request('https://youtu.be/dQw4w9WgXcQ?si=abc&t=5'));
		$this->assertStringContainsString('shorts%2FdQw4w9WgXcQ', new YouTube()->request('https://youtube.com/shorts/dQw4w9WgXcQ'));

		$this->expectException(EmbedException::class);
		new OEmbedProvider('insecure', 'Insecure', ['https://x.test/*'], 'http://x.test/oembed');
	}

	public function testVimeoKeepsAPrivateLinksHash(): void
	{
		$data = new EmbedData(EmbedType::Video, html: '<iframe src="https://player.vimeo.com/video/123?h=abc123&amp;app_id=9"></iframe>');

		$this->assertSame('https://player.vimeo.com/video/123?h=abc123&dnt=1#t=30s', new Vimeo()->frame('https://vimeo.com/123/abc123#t=30s', $data));
		$this->assertSame('https://player.vimeo.com/video/123?h=abc123&dnt=1', new Vimeo()->frame('https://vimeo.com/123/abc123', null));
		$this->assertNull(new Vimeo()->frame('https://vimeo.com/channels/staffpicks', null));
	}

	public function testResponsesAreChecked(): void
	{
		$data = EmbedData::fromResponse([
			'type'          => 'video',
			'title'         => ' A title ',
			'width'         => '640',
			'height'        => '100%',
			'thumbnail_url' => 'http://insecure.test/t.jpg',
			'html'          => '<div><iframe src="https://player.test/1"></iframe></div>'
		]);

		$this->assertNotNull($data);
		$this->assertSame(['A title', 640, null, null], [$data->title, $data->width, $data->height, $data->thumbnail]);
		$this->assertSame('https://player.test/1', $data->frame());
		$this->assertNull(new EmbedData(EmbedType::Rich, html: '<blockquote>Hi</blockquote><script src="https://x.test/w.js"></script>')->frame());
		$this->assertNull(new EmbedData(EmbedType::Video, html: '<iframe src="javascript:alert(1)"></iframe>')->frame());
		$this->assertNull(EmbedData::fromResponse(['type' => 'bogus']));
		$this->assertSame($data->toResponse(), EmbedData::fromResponse($data->toResponse())?->toResponse());
	}

	public function testAnswersAndFailuresAreKept(): void
	{
		$app     = $this->app(['https://www.youtube.com/oembed' => self::YOUTUBE]);
		$embeds  = $app->container()->make(Embeds::class);
		$youtube = $embeds->provider('https://youtu.be/dQw4w9WgXcQ');
		$vimeo   = $embeds->provider('https://vimeo.com/76979871');

		$this->assertInstanceOf(YouTube::class, $youtube);
		$this->assertInstanceOf(Vimeo::class, $vimeo);
		$this->assertSame('Never Gonna Give You Up', $embeds->lookup($youtube, 'https://youtu.be/dQw4w9WgXcQ')?->title);
		$this->assertNull($embeds->lookup($vimeo, 'https://vimeo.com/76979871'));
		$this->assertCount(2, $this->fetcher->requests);

		// A fresh process reads both from the store, even with caching off.
		$again = $this->app();
		$fresh = $again->container()->make(Embeds::class);

		$this->assertSame(113, $fresh->lookup($youtube, 'https://youtu.be/dQw4w9WgXcQ')?->height);
		$this->assertNull($fresh->lookup($vimeo, 'https://vimeo.com/76979871'));
		$this->assertSame([], $this->fetcher->requests);
	}

	public function testFetchingCanBeTurnedOff(): void
	{
		$embeds = $this->app([], 'new EmbedConfig(fetch: false)')->container()->make(Embeds::class);
		$youtube = new YouTube();

		$this->assertNull($embeds->lookup($youtube, 'https://youtu.be/dQw4w9WgXcQ'));
		$this->assertSame([], $this->fetcher->requests);
	}

	public function testConfiguredProvidersComeFirst(): void
	{
		$config = <<<'PHP'
			new EmbedConfig(providers: [
				new OEmbedProvider('youtube', 'Tube', ['https://youtu.be/*'], 'https://tube.test/oembed'),
				['name' => 'example', 'schemes' => ['https://video.example.com/*'], 'endpoint' => 'https://video.example.com/oembed']
			])
			PHP;

		$providers = $this->app([], $config)->container()->make(EmbedProviders::class);

		$this->assertSame(['youtube', 'example', 'vimeo', 'ted', 'codepen', 'spotify', 'soundcloud', 'flickr', 'twitch', 'tiktok'], array_map(static fn (EmbedProvider $provider): string => $provider->name, $providers->all()));
		$this->assertSame('Tube', $providers->forUrl('https://youtu.be/dQw4w9WgXcQ')?->label);
		$this->assertSame('Example', $providers->forUrl('https://video.example.com/1')?->label);
		$this->assertNull($providers->forUrl('https://unknown.test/1'));

		$config = EmbedConfig::fromArray(['providers' => [['name' => 'example', 'schemes' => ['https://x.test/*'], 'endpoint' => 'https://x.test/oembed']], 'timeout' => 5]);

		$this->assertSame($config->toArray(), EmbedConfig::fromArray($config->toArray())->toArray());

		$this->expectException(InvalidConfig::class);
		EmbedConfig::fromArray(['providers' => [['name' => 'bad', 'schemes' => [], 'endpoint' => 'http://x.test']]]);
	}

	public function testMoreBuiltInsFrameFromTheLinkOnTheirOwnHosts(): void
	{
		$cases = [
			'https://www.ted.com/talks/sir_ken_robinson_do_schools_kill_creativity' => 'https://embed.ted.com/talks/sir_ken_robinson_do_schools_kill_creativity',
			'https://embed.ted.com/talks/sir_ken_robinson_do_schools_kill_creativity' => 'https://embed.ted.com/talks/sir_ken_robinson_do_schools_kill_creativity',
			'https://codepen.io/chriscoyier/pen/gfdDu'                 => 'https://codepen.io/chriscoyier/embed/gfdDu?default-tab=result',
			'https://www.ted.com/talks/a"bad'                          => null,
			'https://evil.test/codepen.io/chriscoyier/pen/gfdDu'       => null,
			'https://codepen.io.evil.test/chriscoyier/pen/gfdDu'       => null,
			'https://www.ted.com/talks/a/b'                            => null
		];

		$providers = [new Ted(), new CodePen()];

		foreach ($cases as $url => $src) {
			$match = array_find($providers, static fn (EmbedProvider $candidate): bool => $candidate->matches($url));

			$this->assertSame($src, $match?->frame($url, null), $url);
		}
	}

	public function testCodePenIsNeverAsked(): void
	{
		$embeds  = $this->app()->container()->make(Embeds::class);
		$codepen = $embeds->provider('https://codepen.io/chriscoyier/pen/gfdDu');

		$this->assertInstanceOf(CodePen::class, $codepen);
		$this->assertFalse($codepen->asks());
		$this->assertSame('', $codepen->request('https://codepen.io/chriscoyier/pen/gfdDu'));
		$this->assertNull($embeds->lookup($codepen, 'https://codepen.io/chriscoyier/pen/gfdDu'));
		$this->assertSame([], $this->fetcher->requests);
	}

	public function testProvidersCanBeTurnedOff(): void
	{
		$providers = $this->app([], "new EmbedConfig(off: ['ted', 'youtube'])")->container()->make(EmbedProviders::class);

		$this->assertNull($providers->forUrl('https://www.ted.com/talks/sir_ken_robinson_do_schools_kill_creativity'));
		$this->assertNull($providers->forUrl('https://youtu.be/dQw4w9WgXcQ'));
		$this->assertSame('vimeo', $providers->forUrl('https://vimeo.com/76979871')?->name);
		$this->assertContains('ted', array_map(static fn (EmbedProvider $provider): string => $provider->name, $providers->all()));
		$this->assertSame(['ted'], EmbedConfig::fromArray(['off' => ['ted']])->toArray()['off']);

		$this->expectException(InvalidConfig::class);
		new EmbedConfig(off: ['Not a name']);
	}

	public function testAudioPlayersHaveAFixedHeight(): void
	{
		$spotify    = new Spotify();
		$soundcloud = new SoundCloud();
		$track      = 'https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT?si=abc';

		$this->assertSame('https://open.spotify.com/embed/track/4cOdK2wGLETKBW3PvgPWqT', $spotify->frame($track, null));
		$this->assertSame('https://open.spotify.com/embed/album/1DFixLWuPkv3KT3TnV35m3', $spotify->frame('https://open.spotify.com/intl-de/album/1DFixLWuPkv3KT3TnV35m3', null));
		$this->assertStringContainsString('url=https%3A%2F%2Fopen.spotify.com%2Ftrack%2F4cOdK2wGLETKBW3PvgPWqT&', $spotify->request($track));
		$this->assertNull($spotify->frame('https://open.spotify.com/user/someone', null));
		$this->assertSame(152, $spotify->fixedHeight($track, null));
		$this->assertSame(352, $spotify->fixedHeight('https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M', null));
		$this->assertSame(232, $spotify->fixedHeight($track, new EmbedData(EmbedType::Rich, width: 456, height: 232)));

		$this->assertSame('https://w.soundcloud.com/player/?url=https%3A%2F%2Fsoundcloud.com%2Fforss%2Fflickermood&visual=false&show_artwork=true', $soundcloud->frame('https://m.soundcloud.com/forss/flickermood?in=x', null));
		$this->assertSame('https://w.soundcloud.com/player/?url=https%3A%2F%2Fsoundcloud.com%2Fforss%2Fsets%2Fecclesia&visual=false&show_artwork=true', $soundcloud->frame('https://soundcloud.com/forss/sets/ecclesia', null));
		$this->assertStringContainsString('%2Fs-AbC123', (string) $soundcloud->frame('https://soundcloud.com/forss/demo/s-AbC123', null));
		$this->assertNull($soundcloud->frame('https://soundcloud.com/forss/likes', null));
		$this->assertNull($soundcloud->frame('https://soundcloud.com/forss', null));
		$this->assertSame(166, $soundcloud->fixedHeight('https://soundcloud.com/forss/flickermood', null));
		$this->assertSame(166, $soundcloud->fixedHeight('https://soundcloud.com/forss/flickermood', new EmbedData(EmbedType::Rich, height: 400, fullWidth: true)), 'The answer is the visual player\'s.');
		$this->assertSame(450, $soundcloud->fixedHeight('https://soundcloud.com/forss/sets/ecclesia', null));

		// Any provider whose width is a percentage is fixed at its height.
		$data = EmbedData::fromResponse(['type' => 'rich', 'width' => '100%', 'height' => 120, 'html' => '<iframe src="https://player.test/1"></iframe>']);
		$this->assertNotNull($data);
		$this->assertTrue($data->fullWidth);
		$this->assertSame(['100%', 120], [$data->toResponse()['width'], EmbedData::fromResponse($data->toResponse())?->height]);
		$this->assertTrue(EmbedData::fromResponse($data->toResponse())?->fullWidth);
		$this->assertSame(120, new OEmbedProvider('mix', 'Mix', ['https://mix.test/*'], 'https://mix.test/oembed')->fixedHeight('https://mix.test/1', $data));
		$this->assertNull(new YouTube()->fixedHeight('https://youtu.be/dQw4w9WgXcQ', new EmbedData(EmbedType::Video, width: 200, height: 113)));
	}

	public function testTheDirectiveFramesAFixedPlayer(): void
	{
		$this->writeTemporaryFile('user/content/index.md', "---\nid: d680e8a8-54a7-cbad-6d49-0c445cba2eba\ntitle: Home\n---\n::embed{url=\"https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT\"}\n");

		$app  = $this->app(['https://open.spotify.com/oembed' => '{"type": "rich", "title": "A song", "width": 456, "height": 152, "html": "<iframe width=\\"100%\\" height=\\"152\\" src=\\"https://open.spotify.com/embed/track/4cOdK2wGLETKBW3PvgPWqT?utm_source=oembed\\"></iframe>"}']);
		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertStringContainsString('directive-embed--spotify directive-embed--fixed', $html);
		$this->assertStringContainsString('<div class="directive-embed__wrapper" style="--embed-height: 152px">', $html);
		$this->assertStringContainsString('<iframe class="directive-embed__frame" src="https://open.spotify.com/embed/track/4cOdK2wGLETKBW3PvgPWqT" height="152" title="A song"', $html);
	}

	public function testTwitchAndTikTokFrameFromTheLink(): void
	{
		$twitch = new Twitch(new AppConfig(url: 'https://example.test'));
		$cases  = [
			'https://www.twitch.tv/Shroud'                      => 'https://player.twitch.tv/?channel=shroud&parent=example.test&autoplay=false',
			'https://www.twitch.tv/videos/2245712345'           => 'https://player.twitch.tv/?video=v2245712345&parent=example.test&autoplay=false',
			'https://clips.twitch.tv/FunnyClip-abc_123'         => 'https://clips.twitch.tv/embed?clip=FunnyClip-abc_123&parent=example.test&autoplay=false',
			'https://www.twitch.tv/shroud/clip/FunnyClip-abc'   => 'https://clips.twitch.tv/embed?clip=FunnyClip-abc&parent=example.test&autoplay=false',
			'https://www.twitch.tv/directory'                   => null,
			'https://www.twitch.tv/shroud/videos'               => null
		];

		foreach ($cases as $url => $src) {
			$this->assertTrue($twitch->matches($url), $url);
			$this->assertSame($src, $twitch->frame($url, null), $url);
		}

		$this->assertFalse($twitch->asks());

		$tiktok = new TikTok();

		$this->assertSame('https://www.tiktok.com/player/v1/6718335390845095173?rel=0', $tiktok->frame('https://www.tiktok.com/@scout2015/video/6718335390845095173?lang=en', null));
		$this->assertSame('https://www.tiktok.com/player/v1/6718335390845095173?rel=0', $tiktok->frame('https://www.tiktok.com/embed/v2/6718335390845095173', null));
		$this->assertFalse($tiktok->matches('https://vm.tiktok.com/ZMabc/'));
		$this->assertNull($tiktok->frame('https://www.tiktok.com/@scout2015/video/abc', null));
		$this->assertSame([324, 576], $tiktok->size('https://www.tiktok.com/@scout2015/video/6718335390845095173', EmbedData::fromResponse(['type' => 'video', 'width' => '100%', 'height' => '100%'])));
	}

	public function testPhotosShowAsImages(): void
	{
		$flickr = new Flickr();
		$photo  = new EmbedData(EmbedType::Photo, url: 'https://live.staticflickr.com/3123/2341623661_7c99f48bbf_b.jpg');

		$this->assertNull($flickr->frame('https://www.flickr.com/photos/bees/2341623661/', $photo));
		$this->assertSame('https://live.staticflickr.com/3123/2341623661_7c99f48bbf_b.jpg', $flickr->photo('https://www.flickr.com/photos/bees/2341623661/', $photo));
		$this->assertNull($flickr->photo('https://www.flickr.com/photos/bees/2341623661/', new EmbedData(EmbedType::Photo, url: 'https://evil.test/3123/2341623661_7c99f48bbf_b.jpg')));
		$this->assertNull($flickr->photo('https://www.flickr.com/photos/bees/albums/1', new EmbedData(EmbedType::Rich, html: '<a data-flickr-embed="true"></a>')));

		// Text from a provider loses direction overrides and control characters.
		$this->assertSame('bees', EmbedData::fromResponse(['type' => 'photo', 'author_name' => "\u{202E}\u{202D}\u{202C}bees\u{202C}"])?->author);
		$this->assertSame('A title', EmbedData::fromResponse(['type' => 'photo', 'title' => "A\ntitle"])?->title);

		$this->writeTemporaryFile('user/content/index.md', <<<'MD'
			---
			id: d680e8a8-54a7-cbad-6d49-0c445cba2eba
			title: Home
			---
			::embed[Bees at work]{url="https://www.flickr.com/photos/bees/2341623661/" alt="Bees on a honeycomb"}

			::embed{url="https://www.flickr.com/photos/bees/2341623661/"}
			MD);

		$app  = $this->app(['https://www.flickr.com/services/oembed/' => '{"type": "photo", "title": "ZB8T0193", "author_name": "‮bees", "width": 1024, "height": 683, "url": "https://live.staticflickr.com/3123/2341623661_7c99f48bbf_b.jpg"}']);
		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertStringContainsString('directive-embed--flickr directive-embed--photo', $html);
		$this->assertStringContainsString('<a href="https://www.flickr.com/photos/bees/2341623661/"><img class="directive-embed__photo" src="https://live.staticflickr.com/3123/2341623661_7c99f48bbf_b.jpg" width="1024" height="683" loading="lazy" decoding="async" referrerpolicy="strict-origin-when-cross-origin" alt="Bees on a honeycomb"></a>', $html);
		$this->assertStringContainsString('<figcaption>Bees at work <span class="directive-embed__credit">Photo by bees on Flickr</span></figcaption>', $html);
		$this->assertStringContainsString('alt="ZB8T0193"></a>', $html, 'Without alt, the title.');
		$this->assertStringContainsString('<figcaption><span class="directive-embed__credit">', $html);
	}

	public function testTheDirectiveUsesTheAnswer(): void
	{
		$config = <<<'PHP'
			new EmbedConfig(providers: [
				new OEmbedProvider('example', 'Example', ['https://video.example.com/*'], 'https://video.example.com/oembed'),
				new OEmbedProvider('social', 'Social', ['https://social.example.com/*'], 'https://social.example.com/oembed')
			])
			PHP;

		$this->writeTemporaryFile('user/content/index.md', <<<'MD'
			---
			id: d680e8a8-54a7-cbad-6d49-0c445cba2eba
			title: Home
			---
			::embed{url="https://youtu.be/dQw4w9WgXcQ"}

			::embed[A caption]{url="https://video.example.com/1" title="Given title"}

			::embed{url="https://social.example.com/post/1"}
			MD);

		$app = $this->app([
			'https://www.youtube.com/oembed'     => self::YOUTUBE,
			'https://video.example.com/oembed'   => '{"type": "video", "width": 640, "height": 480, "html": "<iframe src=\\"https://player.example.com/v/1\\"></iframe>"}',
			'https://social.example.com/oembed'  => '{"type": "rich", "title": "A post", "html": "<blockquote>Hi</blockquote><script async src=\\"https://social.example.com/w.js\\"></script>"}'
		], $config);

		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertStringContainsString('<div class="directive-embed__wrapper" style="--embed-ratio: 200 / 113">', $html);
		$this->assertStringContainsString('<iframe class="directive-embed__frame" src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" width="200" height="113" title="Never Gonna Give You Up"', $html);
		$this->assertStringContainsString('<iframe class="directive-embed__frame" src="https://player.example.com/v/1" width="640" height="480" title="Given title"', $html);

		// A script-based rich embed isn't run; it's a link, named by its title.
		$this->assertStringContainsString('<p class="directive-embed directive-embed--link"><a href="https://social.example.com/post/1">A post</a></p>', $html);
		$this->assertStringNotContainsString('w.js', $html);
	}

	public function testTheStreamFetcherOnlyFetchesHttps(): void
	{
		$this->assertNull(new StreamFetcher()->get('http://example.com/oembed', 1));
		$this->assertNull(new StreamFetcher()->get('file:///etc/passwd', 1));
	}
}
