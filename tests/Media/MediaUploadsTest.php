<?php

/**
 * Media upload rules tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Media;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\InvalidConfig;
use Blush\Media\MediaConfig;
use Blush\Media\MediaKind;
use Blush\Media\MediaResolver;
use Blush\Media\MediaUploadRule;
use Blush\Media\MediaUploads;

#[CoversClass(MediaUploads::class)]
#[CoversClass(MediaUploadRule::class)]
#[CoversClass(MediaKind::class)]
final class MediaUploadsTest extends TestCase
{
	public function testEveryKindFollowsTheDefaults(): void
	{
		$uploads = new MediaUploads();
		$now     = new DateTimeImmutable('2026-10-02 12:00:00');

		$this->assertTrue($uploads->allows(MediaKind::Document));
		$this->assertNull($uploads->maxBytes(MediaKind::Image), 'PHP\'s limit until one is set.');
		$this->assertSame('2026/10', $uploads->folder(MediaKind::Image, $now), 'By year and month, as 1.x filed them.');
		$this->assertSame(['enabled' => true, 'maxSize' => null, 'path' => '{year}/{month}', 'kinds' => []], $uploads->toArray());
	}

	public function testAKindTakesItsOwnRules(): void
	{
		$uploads = MediaUploads::fromArray([
			'maxSize' => 24,
			'path'    => '/{kind}/{year}/',
			'kinds'   => [
				'file'     => ['enabled' => false],
				'audio'    => ['maxSize' => 40, 'path' => 'sound/{kind}'],
				'image'    => ['enabled' => true, 'maxSize' => null, 'path' => '  '],
				'document' => new MediaUploadRule(path: 'docs/{day}')
			]
		]);
		$now = new DateTimeImmutable('2026-10-02 12:00:00');

		$this->assertSame(['audio', 'document', 'file'], array_keys($uploads->kinds), 'In the kinds\' order, without rules that say nothing.');
		$this->assertFalse($uploads->allows(MediaKind::File));
		$this->assertSame(24 * 1024 * 1024, $uploads->maxBytes(MediaKind::Image), 'A kind without its own takes every kind\'s.');
		$this->assertSame(40 * 1024 * 1024, $uploads->maxBytes(MediaKind::Audio));
		$this->assertSame('images/2026', $uploads->folder(MediaKind::Image, $now));
		$this->assertSame('sound/audio', $uploads->folder(MediaKind::Audio, $now));
		$this->assertSame('docs/02', $uploads->folder(MediaKind::Document, $now));
		$this->assertSame($uploads->toArray(), MediaUploads::fromArray($uploads->toArray())->toArray(), 'It reads what it writes.');
		$this->assertFalse(new MediaUploads(enabled: false)->allows(MediaKind::Image), 'All Files turns every kind off.');
		$this->assertSame('', new MediaUploads(path: '/')->folder(MediaKind::Image, $now), 'Straight in user/media.');
	}

	public function testRefusesWhatDoesNotFit(): void
	{
		$refused = [
			['path' => '../up'],
			['path' => 'a/.hidden'],
			['path' => '{week}'],
			['path' => 'by/{ext}'],
			['path' => 'a b'],
			['maxSize' => 0],
			['maxSize' => '24'],
			['kinds' => ['zip' => []]],
			['kinds' => ['image' => ['size' => 2]]],
			['color' => 'red']
		];

		foreach ($refused as $data) {
			try {
				MediaUploads::fromArray($data);
				$this->fail(sprintf('%s is refused.', json_encode($data)));
			} catch (InvalidConfig) {
				$this->addToAssertionCount(1);
			}
		}

		try {
			MediaUploads::fromArray(['path' => '{year}/{ext}']);
		} catch (InvalidConfig $error) {
			$this->assertStringContainsString('renditions in other formats belong in its folder', $error->getMessage(), 'Not {ext} (D-674).');
		}

		$this->assertSame(['enabled' => false, 'maxSize' => 8, 'path' => 'uploads', 'kinds' => []], MediaConfig::fromArray(['uploads' => ['enabled' => false, 'maxSize' => 8, 'path' => 'uploads']])->uploads->toArray());
	}

	public function testDocumentsAreTheirOwnKind(): void
	{
		$this->assertSame(
			[MediaKind::Document, MediaKind::Document, MediaKind::Document, MediaKind::File],
			array_map(MediaKind::fromMime(...), ['application/pdf', 'text/plain', 'application/vnd.oasis.opendocument.text', 'text/vtt'])
		);
		$this->assertSame(['documents', 'Documents'], [MediaKind::Document->folder(), MediaKind::Document->label()]);
		$this->assertContains('application/pdf', MediaConfig::DEFAULT_TYPES, 'PDFs are allowed by default; other documents once listed.');

		$directory = sys_get_temp_dir() . '/blush-docs-' . bin2hex(random_bytes(4));
		mkdir($directory);
		file_put_contents("{$directory}/table.csv", "a,b\n1,2\n");
		file_put_contents("{$directory}/paper.pdf", "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n");

		try {
			$this->assertSame('text/csv', MediaResolver::mimeOf("{$directory}/table.csv"), 'Plain text read by its name.');
			$this->assertSame('application/pdf', MediaResolver::mimeOf("{$directory}/paper.pdf"));
		} finally {
			array_map(unlink(...), glob("{$directory}/*") ?: []);
			rmdir($directory);
		}
	}
}
