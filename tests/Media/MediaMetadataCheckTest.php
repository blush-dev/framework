<?php

/**
 * Media metadata check tests.
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
use Blush\Console\Commands\LintContent;
use Blush\Console\Console;
use Blush\Console\Testing\CommandTester;
use Blush\Content\Lint\Linter;
use Blush\Core\Application;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Media\MediaMetadataCheck;
use Blush\Media\MediaMetadataStore;
use Blush\Tests\BootsScratchSite;

#[CoversClass(MediaMetadataCheck::class)]
#[CoversClass(MediaMetadataStore::class)]
#[CoversClass(LintContent::class)]
final class MediaMetadataCheckTest extends TestCase
{
	use BootsScratchSite;

	/**
	 * A 2×1 PNG.
	 */
	private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAYAAAD0In+KAAAAEUlEQVR42mP8z8Dwn4GBgQEAFQYCAR4v9JgAAAAASUVORK5CYII=';

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	private function site(): Application
	{
		$png = (string) base64_decode(self::PNG, true);

		foreach (['lake', 'sunset', 'broken', 'listed', 'odd'] as $name) {
			$this->writeTemporaryFile("user/media/2026/{$name}.png", $png);
		}

		$this->writeTemporaryFile('user/media/fake.png', 'not a PNG');
		$this->writeTemporaryFile('user/content/trip/beach.png', $png);
		$this->writeTemporaryFile('user/content/trip/index.md', "---\ntitle: Trip\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e75\n---\n");
		$this->writeTemporaryFile('user/media/trip/shell.png', $png);

		$this->writeTemporaryFile('user/data/media/2026/lake.png.json', '{"alt": "A lake at dawn", "caption": "Mist on the water", "id": "0199b6e2-7f3a-7c41-9d2e-000000000001"}');
		$this->writeTemporaryFile('user/data/media/2026/sunset.png.json', '{"alt": "The sun going down", "id": "0199b6e2-7f3a-7c41-9d2e-000000000002"}');
		$this->writeTemporaryFile('user/data/media/2026/broken.png.json', '{"alt": [');
		$this->writeTemporaryFile('user/data/media/2026/listed.png.json', '["A lake", "At dawn"]');
		$this->writeTemporaryFile('user/data/media/2026/odd.png.json', '{"alt": {"nested": "value"}, "mood": "calm", "id": "0199b6e2-7f3a-7c41-9d2e-000000000003"}');
		$this->writeTemporaryFile('user/data/media/2019/gone.png.json', '{"alt": "Deleted"}');
		$this->writeTemporaryFile('user/data/media/fake.png.json', '{"alt": "Not an image"}');
		$this->writeTemporaryFile('user/data/media/_content/trip/beach.png.json', '{"credit": "Jane Doe"}');
		$this->writeTemporaryFile('user/data/media/trip/shell.png.json', '{"alt": "A shell", "id": "0199b6e2-7f3a-7c41-9d2e-000000000004"}');

		$app = $this->scratchApplication();
		$app->boot();

		return $app;
	}

	/**
	 * Returns the violations as strings, by path.
	 *
	 * @param  array<string, list<Violation>> $violations
	 * @return array<string, list<string>>
	 */
	private static function messages(array $violations): array
	{
		$messages = array_map(static fn (array $list): array => array_map(
			static fn (Violation $violation): string => "{$violation->severity->value} {$violation}",
			$list
		), array_filter($violations, static fn (array $list): bool => $list !== []));

		ksort($messages);

		return $messages;
	}

	public function testFindsEveryMetadataFile(): void
	{
		$files = $this->site()->container()->make(MediaMetadataStore::class)->files();

		$this->assertSame(['2019/gone.png', '2026/broken.png', '2026/lake.png', '2026/listed.png', '2026/odd.png', '2026/sunset.png', '_content/trip/beach.png', 'fake.png', 'trip/shell.png'], array_keys($files));
		$this->assertSame('user/data/media/2026/sunset.png.json', $files['2026/sunset.png']['location']);
	}

	public function testReportsOrphanedUnreadableAndInvalidMetadata(): void
	{
		[$checked, $violations] = $this->site()->container()->make(MediaMetadataCheck::class)->check();
		$messages               = self::messages($violations);

		$this->assertSame(9, $checked);
		$this->assertSame([
			'user/data/media/2019/gone.png.json',
			'user/data/media/2026/broken.png.json',
			'user/data/media/2026/listed.png.json',
			'user/data/media/2026/odd.png.json',
			'user/data/media/_content/trip/beach.png.json',
			'user/data/media/fake.png.json'
		], array_keys($messages), 'Well-formed files that describe a file there pass.');

		$this->assertSame(['warning file: describes user/media/2019/gone.png, which isn\'t there; move this file with its media file, or delete it.'], $messages['user/data/media/2019/gone.png.json']);
		$this->assertSame(['warning file: describes user/media/_content/trip/beach.png, which isn\'t there; move this file with its media file, or delete it.'], $messages['user/data/media/_content/trip/beach.png.json'], 'Details for a file beside an entry, which isn\'t media (D-294).');
		$this->assertSame(['warning file: describes user/media/fake.png, which isn\'t a type of media the site allows.'], $messages['user/data/media/fake.png.json']);
		$this->assertStringStartsWith('error file: can\'t be read, so the file has no details: user/data/media/2026/broken.png.json isn\'t valid JSON', $messages['user/data/media/2026/broken.png.json'][0]);
		$this->assertSame(['error file: isn\'t a map of fields, so the file has no details.'], $messages['user/data/media/2026/listed.png.json']);

		$odd = $messages['user/data/media/2026/odd.png.json'];

		$this->assertCount(2, $odd);
		$this->assertStringStartsWith('error alt: ', $odd[0], 'A value that doesn\'t fit its field.');
		$this->assertSame('notice mood: is not declared by the schema.', $odd[1]);
	}

	public function testTheLinterReportsMetadataBesideContent(): void
	{
		$app = $this->site();
		$this->writeTemporaryFile('user/content/about.md', "---\ntitle: About\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74\n---\n");

		$report = $app->container()->make(Linter::class)->lint();

		$this->assertSame(9, $report->metadata);
		$this->assertTrue($report->hasErrors());
		$this->assertSame(3, $report->count(Severity::Error));
		$this->assertSame(3, $report->count(Severity::Warning));
		$this->assertArrayHasKey('user/data/media/2019/gone.png.json', $report->violations(Severity::Warning));

		$result = new CommandTester($app->container()->make(Console::class))->run('content:lint');

		$this->assertFalse($result->isSuccessful());
		$this->assertStringContainsString('user/data/media/2019/gone.png.json', $result->output);
		$this->assertStringContainsString('and 9 media metadata files: 3 errors, 3 warnings.', $result->output . $result->errors);
	}
}
