<?php

/**
 * PDF metadata tests.
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
use Blush\Media\Embedded\PdfReader;
use Blush\Media\Index\MediaIndex;
use Blush\Media\Index\MediaIndexer;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\Media\TaggedDocument;

#[CoversClass(PdfReader::class)]
final class PdfMetadataTest extends TestCase
{
	use BootsScratchSite;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	public function testReadsAPdf(): void
	{
		$read = new PdfReader()->read($this->writeTemporaryFile('guide.pdf', TaggedDocument::pdf()), 'application/pdf');

		$this->assertSame([
			'title'       => 'Meridian Style Guide',
			'description' => 'How we write (and why)',
			'creator'     => 'Nadia',
			'keywords'    => ['style', 'brand', 'voice'],
			'created'     => '2026-08-14 11:05:00 -05:00',
			'format'      => 'PDF 1.7',
			'pages'       => 12,
			'pageSize'    => '8.5 × 11 in (Letter)',
			'software'    => 'Word',
			'producer'    => 'Adobe PDF Library 17.0'
		], $read->values, 'Literal, escaped, and UTF-16 strings; the size on the page tree.');
	}

	public function testReadsACompressedObjectStream(): void
	{
		$read = new PdfReader()->read($this->writeTemporaryFile('packed.pdf', TaggedDocument::packedPdf()), 'application/pdf');

		$this->assertSame([
			'title'    => 'Packed (title)',
			'creator'  => 'Ana',
			'format'   => 'PDF 1.5',
			'pages'    => 3,
			'pageSize' => '210 × 297 mm (A4)'
		], $read->values, 'Objects in a Flate object stream, a title in an object of its own, and the size on the page.');
	}

	public function testAnEncryptedPdfGivesOnlyItsStructure(): void
	{
		$read = new PdfReader()->read($this->writeTemporaryFile('locked.pdf', TaggedDocument::pdf(true)), 'application/pdf');

		$this->assertSame(['format' => 'PDF 1.7', 'pages' => 12, 'pageSize' => '8.5 × 11 in (Letter)'], $read->values);
	}

	public function testReadsOnlyPdfs(): void
	{
		$this->assertTrue(new PdfReader()->read($this->writeTemporaryFile('guide.pdf', TaggedDocument::pdf()), 'text/plain')->isEmpty(), 'Another type.');
		$this->assertTrue(new PdfReader()->read($this->writeTemporaryFile('junk.pdf', str_repeat("\xFF", 64)), 'application/pdf')->isEmpty(), 'Not a PDF.');
	}

	public function testTheIndexKeepsDocuments(): void
	{
		$this->writeTemporaryFile('user/media/guide.pdf', TaggedDocument::pdf());

		$app = $this->scratchApplication();
		$app->boot();
		$app->container()->make(MediaIndexer::class)->index();

		$guide = $app->container()->make(MediaIndex::class)->snapshot()->records['guide.pdf'] ?? null;

		$this->assertSame(12, $guide?->embedded?->values['pages'] ?? null);
	}
}
