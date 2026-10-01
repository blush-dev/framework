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
use Blush\Field\Fields\TextField;
use Blush\Media\MediaFieldSet;
use Blush\Media\MediaFieldSource;
use Blush\Media\MediaKind;
use Blush\Media\MediaMetadataStore;
use Blush\Media\MediaSchemas;
use Blush\Tests\Fixtures\Media\TaggedJpeg;

#[CoversClass(MediaKind::class)]
#[CoversClass(MediaFieldSet::class)]
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
			$this->writeTemporaryFile('config/media.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Media\\MediaConfig::fromArray(['fields' => ['image' => [['name' => 'photographer', 'aliases' => ['shot_by']]], 'all' => [['name' => 'credit', 'required' => true]]]]);\n");
			$this->writeTemporaryFile('user/data/media-fields.yml', "all:\n  - name: license\n    type: enum\n    options: [cc-by, all-rights]\n");
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

	public function testKindsComeFromMimeTypes(): void
	{
		$this->assertSame([MediaKind::Image, MediaKind::Video, MediaKind::Audio, MediaKind::File], array_map(MediaKind::fromMime(...), ['image/PNG', 'video/mp4', 'audio/mpeg', 'text/vtt']));
	}

	public function testEachKindHasTheBuiltInFields(): void
	{
		$this->site(false);

		$schemas = $this->app->container()->make(MediaSchemas::class);

		$this->assertSame(['alt', 'title', 'caption', 'credit', 'description'], array_keys($schemas->schema(MediaKind::Image)->fields), 'Its own first.');
		$this->assertSame(['title', 'caption', 'credit', 'description'], array_keys($schemas->schema(MediaKind::Audio)->fields), 'Only images have alt text.');
		$this->assertSame(['alt', 'title', 'caption', 'credit', 'description'], self::names(self::json($this->send('GET', '/media/photo.png'))['fields'] ?? null));
	}

	public function testSitesAndExtensionsAddFields(): void
	{
		$this->site();

		$container = $this->app->container();
		$container->instance('acme.fields', new class implements MediaFieldSource {
			public function fieldSets(): iterable
			{
				yield new MediaFieldSet([new TextField('camera')], MediaKind::Image);
				yield new MediaFieldSet([new TextField('artist')], MediaKind::Audio);
			}
		});
		$container->tag('acme.fields', MediaFieldSource::TAG);

		$file = self::json($this->send('GET', '/media/photo.png'));

		$this->assertSame(['alt', 'camera', 'photographer', 'title', 'caption', 'credit', 'description', 'license'], self::names($file['fields'] ?? null), 'The image\'s, then every kind\'s, each in order.');
		$this->assertContains(['field' => 'credit', 'message' => 'is required.', 'severity' => 'error'], (array) ($file['violations'] ?? []), 'The config replaced the built-in credit.');
	}

	public function testSavesSetsAndRemovesFields(): void
	{
		$this->site();

		$metadata = 'user/data/media/photo.png.yml';
		$this->writeTemporaryFile($metadata, "# Mine\nshot_by: Jane\ncredit: Old\nnotes: kept\n");

		$response = $this->patch(['set' => ['photographer' => 'Sam', 'license' => 'cc-by', 'alt' => "A   dog\nrunning"], 'remove' => ['credit']]);
		$file     = self::json($response);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame("# Mine\nshot_by: Sam\nnotes: kept\nlicense: cc-by\nalt: \"A dog running\"\n", file_get_contents($this->temporaryDirectory() . "/{$metadata}"), 'An alias in use is kept; other keys and comments stay.');
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
