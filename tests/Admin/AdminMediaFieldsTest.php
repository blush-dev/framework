<?php

/**
 * Media metadata fields tests.
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
use Blush\Config\InvalidConfig;
use Blush\Media\MediaKind;
use Blush\Media\MediaKindTarget;
use Blush\Media\MediaMetadataStore;
use Blush\Media\MediaSchemas;
use Blush\Tests\Fixtures\Media\TaggedAudioVideo;
use Blush\Tests\Fixtures\Media\TaggedJpeg;

#[CoversClass(MediaKind::class)]
#[CoversClass(MediaKindTarget::class)]
#[CoversClass(MediaSchemas::class)]
#[CoversClass(MediaListController::class)]
#[CoversClass(MediaMetadataStore::class)]
final class AdminMediaFieldsTest extends TestCase
{
	use BootsAdmin;

	/**
	 * A 1×1 PNG.
	 */
	private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	private function site(bool $fields = true): void
	{
		$this->writeTemporaryFile('user/media/photo.png', (string) base64_decode(self::PNG, true));

		if ($fields) {
			$this->writeTemporaryFile('user/data/fields/photo.json', '{"label": "Photo", "targets": ["media:image"], "fields": [{"name": "photographer", "aliases": ["shot_by"]}]}');
			$this->writeTemporaryFile('user/data/fields/rights.json', '{"targets": ["media:image", "media:video", "media:audio", "media:file"], "fields": [{"name": "license", "type": "enum", "options": ["cc-by", "all-rights"], "required": true}]}');
		}

		$this->boot();
		$this->login();
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function patch(array $data): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send('PATCH', '/media/photo.png', json_encode($data) ?: '', ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}

	/**
	 * @return list<string>
	 */
	private static function names(mixed $fields): array
	{
		return array_values(array_filter(array_column(is_array($fields) ? $fields : [], 'name'), is_string(...)));
	}

	public function testAnswersWhatAFileSaysAboutItselfButNotWhere(): void
	{
		$this->writeTemporaryFile('user/media/tagged.jpg', TaggedJpeg::bytes());
		$this->site(false);

		$response = $this->send('GET', '/media/tagged.jpg');
		$file     = self::json($response);
		$embedded = is_array($file['embedded'] ?? null) ? $file['embedded'] : [];

		$this->assertSame('Harbor at First Light', is_array($embedded['values'] ?? null) ? $embedded['values']['title'] ?? null : null);
		$this->assertTrue($embedded['location'] ?? null, 'It says it has a location.');
		$this->assertStringNotContainsString('51.5', (string) $response->getBody(), 'Never where.');
		$plain = self::json($this->send('GET', '/media/photo.png'))['embedded'] ?? null;

		$this->assertSame([], is_array($plain) ? $plain['values'] ?? null : null, 'A file that says nothing.');
	}

	public function testAnswersWithASoundsArtwork(): void
	{
		$this->writeTemporaryFile('user/media/song.mp3', TaggedAudioVideo::mp3());
		$this->writeTemporaryFile('user/media/tone.wav', TaggedAudioVideo::wav());
		$this->site(false);

		$response = $this->send('GET', '/media-artwork/song.mp3');

		$this->assertSame([200, 'image/jpeg', str_repeat("\xAB", 2048)], [$response->getStatusCode(), $response->getHeaderLine('Content-Type'), (string) $response->getBody()]);
		$embedded = self::json($this->send('GET', '/media/song.mp3'))['embedded'] ?? null;
		$values   = is_array($embedded) && is_array($embedded['values'] ?? null) ? $embedded['values'] : [];

		$this->assertSame('JPEG, 2 KB', $values['artwork'] ?? null, 'Its details describe it.');
		$this->assertSame(404, $this->send('GET', '/media-artwork/tone.wav')->getStatusCode(), 'A file without one.');
		$this->assertSame(404, $this->send('GET', '/media-artwork/missing.mp3')->getStatusCode(), 'No such file.');
	}

	public function testKindsComeFromMimeTypes(): void
	{
		$this->assertSame([MediaKind::Image, MediaKind::Video, MediaKind::Audio, MediaKind::File], array_map(MediaKind::fromMime(...), ['image/PNG', 'video/mp4', 'audio/mpeg', 'text/vtt']));
	}

	public function testEachKindHasTheBuiltInFields(): void
	{
		$this->site(false);

		$schemas = $this->app->container()->make(MediaSchemas::class);

		$this->assertSame(['title', 'alt', 'caption', 'credit', 'description'], array_keys($schemas->schema(MediaKind::Image)->fields), 'The title, then its own.');
		$this->assertSame(['title', 'caption', 'credit', 'description'], array_keys($schemas->schema(MediaKind::Audio)->fields), 'Only images have alt text.');
		$this->assertSame(['title', 'alt', 'caption', 'credit', 'description'], self::names(self::json($this->send('GET', '/media/photo.png'))['fields'] ?? null));
	}

	public function testFieldSetsAddFieldsByKind(): void
	{
		$this->site();

		$file = self::json($this->send('GET', '/media/photo.png'));

		$this->assertSame(['title', 'alt', 'caption', 'credit', 'description', 'photographer', 'license'], self::names($file['fields'] ?? null), 'The built-in fields, then each set\'s, by set name.');
		$this->assertSame([
			['name' => 'photo', 'label' => 'Photo', 'description' => '', 'fields' => ['photographer']],
			['name' => 'rights', 'label' => 'Rights', 'description' => '', 'fields' => ['license']]
		], $file['sets'] ?? null);
		$this->assertContains(['field' => 'license', 'message' => 'is required.', 'severity' => 'error'], (array) ($file['violations'] ?? []));
		$this->assertSame(['title', 'caption', 'credit', 'description', 'license'], array_keys($this->app->container()->make(MediaSchemas::class)->schema(MediaKind::Audio)->fields), 'Only the sets on a kind.');
	}

	public function testASetCantReuseABuiltInField(): void
	{
		$this->writeTemporaryFile('user/data/fields/strict.json', '{"targets": ["media:image"], "fields": [{"name": "alt", "required": true}]}');
		$this->site(false);

		$this->expectException(InvalidConfig::class);
		$this->expectExceptionMessage('media:image can\'t take field set "strict": Schema key "alt"');

		$this->app->container()->make(MediaSchemas::class)->schema(MediaKind::Image);
	}

	public function testSavesSetsAndRemovesFields(): void
	{
		$this->site();

		$metadata = 'user/data/media/photo.png.json';
		$this->writeTemporaryFile($metadata, '{"shot_by": "Jane", "credit": "Old", "notes": "kept"}');

		$response = $this->patch(['set' => ['photographer' => 'Sam', 'license' => 'cc-by', 'alt' => "A   dog\nrunning"], 'remove' => ['credit']]);
		$file     = self::json($response);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['shot_by' => 'Sam', 'notes' => 'kept', 'license' => 'cc-by', 'alt' => 'A dog running'], json_decode((string) file_get_contents($this->temporaryDirectory() . "/{$metadata}"), true), 'An alias in use is kept; other keys stay.');
		$this->assertSame(['alt' => 'A dog running', 'photographer' => 'Sam', 'license' => 'cc-by'], $file['values'] ?? null, 'In the fields\' order.');
		$this->assertSame(['notes' => 'kept'], $file['extra'] ?? null);
		$this->assertSame('A dog running', $file['alt'] ?? null);

		$named = self::json($this->patch(['set' => ['title' => "  Sam's\n dog  "]]));

		$this->assertSame("Sam's dog", $named['title'] ?? null, 'A title is one line, as the library shows it.');
	}

	public function testChecksValuesByTheirFields(): void
	{
		$this->site();

		$wrong = $this->patch(['set' => ['license' => 'mine']]);

		$this->assertSame(422, $wrong->getStatusCode());
		$this->assertSame('license', self::json($wrong)['field'] ?? null);
		$this->assertSame(422, $this->patch(['set' => ['artist' => 'x']])->getStatusCode(), 'Not a field of images.');
		$this->assertSame(422, $this->patch(['remove' => ['notes']])->getStatusCode());
		$this->assertSame(400, $this->patch(['remove' => 'credit'])->getStatusCode());
	}
}
