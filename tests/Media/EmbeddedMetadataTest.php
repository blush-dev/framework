<?php

/**
 * Embedded metadata tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;
use Blush\Media\Embedded\EmbeddedMetadata;
use Blush\Media\Embedded\EmbeddedMetadataReader;
use Blush\Media\Embedded\EmbeddedReaderFactory;
use Blush\Media\Embedded\EmbeddedReaderRegistrar;
use Blush\Media\Embedded\EmbeddedReaderRegistry;
use Blush\Media\Embedded\EmbeddedReaderType;
use Blush\Media\Embedded\ExifReader;
use Blush\Media\Embedded\IptcReader;
use Blush\Media\Embedded\XmpReader;
use Blush\Media\Index\MediaIndex;
use Blush\Media\Index\MediaIndexer;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\Media\TaggedJpeg;

#[CoversClass(EmbeddedMetadata::class)]
#[CoversClass(EmbeddedMetadataReader::class)]
#[CoversClass(EmbeddedReaderFactory::class)]
#[CoversClass(EmbeddedReaderRegistrar::class)]
#[CoversClass(EmbeddedReaderRegistry::class)]
#[CoversClass(EmbeddedReaderType::class)]
#[CoversClass(ExifReader::class)]
#[CoversClass(IptcReader::class)]
#[CoversClass(XmpReader::class)]
final class EmbeddedMetadataTest extends TestCase
{
	use BootsScratchSite;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * @param list<'exif'|'iptc'|'xmp'> $with
	 */
	private function photo(array $with = ['exif', 'iptc', 'xmp']): string
	{
		return $this->writeTemporaryFile('photo.jpg', TaggedJpeg::bytes($with));
	}

	#[RequiresFunction('exif_read_data')]
	public function testReadsExif(): void
	{
		$read = new ExifReader()->read($this->photo(['exif']), 'image/jpeg');

		$this->assertSame([
			'description' => 'A harbor at dawn',
			'creator'     => 'Jane Doe',
			'copyright'   => 'Copyright Jane Doe',
			'created'     => '2024-05-01 06:30:00',
			'camera'      => 'Canon EOS R5',
			'lens'        => 'RF35mm F1.8',
			'focalLength' => '35 mm',
			'aperture'    => 'f/2.8',
			'exposure'    => '1/250 s',
			'iso'         => 400,
			'orientation' => 1,
			'software'    => 'Lightroom'
		], $read->values, 'A model that names its maker isn\'t named twice.');
		$this->assertSame(['lat' => 51.5, 'lng' => -0.1275], $read->location);
	}

	public function testReadsIptc(): void
	{
		$read = new IptcReader()->read($this->photo(['iptc']), 'image/jpeg');

		$this->assertSame([
			'title'       => 'Harbor at Dawn',
			'description' => 'Boats waiting for the tide.',
			'creator'     => 'Jane Doe, Sam Roe',
			'copyright'   => '© 2024 Jane Doe',
			'credit'      => 'Acme Photos',
			'keywords'    => ['harbor', 'boats'],
			'created'     => '2024-05-01 06:30:00 -05:00'
		], $read->values);
	}

	public function testReadsXmpEitherWay(): void
	{
		$read = new XmpReader()->read($this->photo(['xmp']), 'image/jpeg');

		$this->assertSame([
			'title'    => 'Harbor at First Light',
			'creator'  => 'Jane Doe',
			'keywords' => ['harbor', 'sunrise'],
			'software' => 'Darktable 4.6'
		], $read->values, 'The default language, a list, a bag, and an attribute.');

		$svg = $this->writeTemporaryFile('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><metadata><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/"><rdf:Description><dc:title>Logo</dc:title></rdf:Description></rdf:RDF></metadata></svg>');

		$this->assertSame(['title' => 'Logo'], new XmpReader()->read($svg, 'image/svg+xml')->values, 'An SVG\'s bare RDF.');
	}

	public function testReadersFailQuietly(): void
	{
		$broken = $this->writeTemporaryFile('broken.jpg', "\xFF\xD8<x:xmpmeta><rdf:RDF><not xml");

		foreach ([new ExifReader(), new IptcReader(), new XmpReader()] as $reader) {
			$this->assertTrue($reader->read($broken, 'image/jpeg')->isEmpty(), $reader::class);
		}
	}

	public function testMergesInOrderAndCachesInTheIndex(): void
	{
		$this->writeTemporaryFile('user/media/photo.jpg', TaggedJpeg::bytes());
		$this->writeTemporaryFile('user/media/plain.jpg', TaggedJpeg::plain());

		$app = $this->scratchApplication();
		$app->boot();

		$read = $app->container()->make(EmbeddedMetadataReader::class)->read($this->temporaryDirectory() . '/user/media/photo.jpg', 'image/jpeg');

		$this->assertSame('Harbor at First Light', $read->values['title'] ?? null, 'XMP wins over IPTC.');
		$this->assertSame('Boats waiting for the tide.', $read->values['description'] ?? null, 'IPTC over EXIF.');
		$this->assertSame(['harbor', 'sunrise'], $read->values['keywords'] ?? null);
		$this->assertSame('Acme Photos', $read->values['credit'] ?? null, 'Filled from the next reader.');

		$app->container()->make(MediaIndexer::class)->index();

		$records = $app->container()->make(MediaIndex::class)->snapshot()->records;

		$this->assertSame('Harbor at First Light', ($records['photo.jpg'] ?? null)?->embedded?->values['title'] ?? null);
		$this->assertNull(($records['plain.jpg'] ?? null)?->embedded, 'Nothing to say, nothing kept.');
	}

	public function testNormalizesText(): void
	{
		$this->assertSame(['title' => 'Café', 'keywords' => ['a', 'b']], new EmbeddedMetadata(['title' => "Caf\xE9\0 ", 'keywords' => ['a', '', 'b', 'a'], 'unknown' => 'x'])->values, 'Latin-1 made UTF-8, padding and repeats gone, unknown keys left out.');
		$this->assertSame('2024-05-01 06:30:00 +02:00', EmbeddedMetadata::date('2024-05-01T06:30:00+0200'));
		$this->assertSame('2024-05-01', EmbeddedMetadata::date('20240501'));
		$this->assertSame('', EmbeddedMetadata::date('0000:00:00 00:00:00'));
	}
}
