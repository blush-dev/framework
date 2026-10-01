<?php

/**
 * Sound and video metadata tests.
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
use Blush\Media\Embedded\BinaryFile;
use Blush\Media\Embedded\EmbeddedMetadata;
use Blush\Media\Embedded\Id3Reader;
use Blush\Media\Embedded\MatroskaReader;
use Blush\Media\Embedded\Mp4Reader;
use Blush\Media\Embedded\OggReader;
use Blush\Media\Embedded\RiffReader;
use Blush\Media\Index\MediaIndex;
use Blush\Media\Index\MediaIndexer;
use Blush\Media\MediaResolver;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\Media\TaggedAudioVideo;

#[CoversClass(BinaryFile::class)]
#[CoversClass(EmbeddedMetadata::class)]
#[CoversClass(Id3Reader::class)]
#[CoversClass(MatroskaReader::class)]
#[CoversClass(Mp4Reader::class)]
#[CoversClass(OggReader::class)]
#[CoversClass(RiffReader::class)]
#[CoversClass(MediaResolver::class)]
final class AudioVideoMetadataTest extends TestCase
{
	use BootsScratchSite;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	private function file(string $name, string $bytes): string
	{
		return $this->writeTemporaryFile($name, $bytes);
	}

	public function testReadsMp3(): void
	{
		$read = new Id3Reader()->read($this->file('song.mp3', TaggedAudioVideo::mp3()), 'audio/mpeg');

		$this->assertSame([
			'title'       => 'Morning Song',
			'description' => 'Recorded live',
			'creator'     => 'The Harbors',
			'album'       => 'Tides',
			'track'       => '3/12',
			'genre'       => 'Rock',
			'created'     => '2019',
			'duration'    => 26.122,
			'artwork'     => 'image/jpeg, 2 KB'
		], $read->values, 'ID3v2 over ID3v1, the genre\'s number dropped, a UTF-16 comment, and Xing\'s frame count.');

		$this->assertSame(1.0, new Id3Reader()->read($this->file('cbr.mp3', TaggedAudioVideo::mp3(false)), 'audio/mpeg')->values['duration'] ?? null, 'A constant bit rate.');
	}

	public function testReadsMp4(): void
	{
		$read = new Mp4Reader()->read($this->file('clip.mp4', TaggedAudioVideo::mp4()), 'video/mp4');

		$this->assertSame([
			'title'    => 'Harbor Timelapse',
			'creator'  => 'Jane Doe',
			'track'    => '2/5',
			'created'  => '2023-06-01 10:00:00 Z',
			'duration' => 12.5,
			'artwork'  => 'image/jpeg, 3 KB',
			'width'    => 1280,
			'height'   => 720
		], $read->values, 'The index after the media data, found by skipping it.');
	}

	public function testReadsOggVorbisAndOpus(): void
	{
		$vorbis = new OggReader()->read($this->file('song.ogg', TaggedAudioVideo::ogg()), 'audio/ogg');

		$this->assertSame(['title' => 'Evening Song', 'creator' => 'The Harbors', 'album' => 'Tides', 'track' => '4', 'created' => '2021-04', 'duration' => 10.0, 'artwork' => 'image/png, 1 KB', 'software' => 'Xiph.Org libVorbis I 20200704'], $vorbis->values);
		$this->assertSame(5.0, new OggReader()->read($this->file('song.opus', TaggedAudioVideo::ogg(true)), 'audio/ogg')->values['duration'] ?? null, 'Opus: 48 kHz, less its pre-skip.');
	}

	public function testReadsWav(): void
	{
		$read = new RiffReader()->read($this->file('tone.wav', TaggedAudioVideo::wav()), 'audio/wav');

		$this->assertSame(['title' => 'Tone', 'creator' => 'Jane Doe', 'created' => '2020', 'duration' => 2.0], $read->values);
	}

	public function testReadsWebm(): void
	{
		$read = new MatroskaReader()->read($this->file('clip.webm', TaggedAudioVideo::webm()), 'video/webm');

		$this->assertSame(['title' => 'Harbor Clip', 'duration' => 3.5, 'width' => 640, 'height' => 360, 'software' => 'Blush Test'], $read->values, 'A segment of unknown size.');
	}

	public function testReadersKeepToTheirFormats(): void
	{
		$mp3 = $this->file('song.mp3', TaggedAudioVideo::mp3());

		foreach ([new Mp4Reader(), new OggReader(), new RiffReader(), new MatroskaReader()] as $reader) {
			$this->assertTrue($reader->read($mp3, 'audio/mpeg')->isEmpty(), $reader::class);
		}

		$junk = $this->file('junk.mp4', str_repeat("\xFF", 64));

		foreach ([[new Mp4Reader(), 'video/mp4'], [new OggReader(), 'audio/ogg'], [new RiffReader(), 'audio/wav'], [new MatroskaReader(), 'video/webm'], [new Id3Reader(), 'audio/mpeg']] as [$reader, $mime]) {
			$this->assertSame([], array_diff_key($reader->read($junk, $mime)->values, ['duration' => true]), $reader::class . ' reads nothing from junk.');
		}
	}

	public function testTheIndexKeepsSoundAndVideo(): void
	{
		$this->writeTemporaryFile('user/media/song.mp3', TaggedAudioVideo::mp3());
		$this->writeTemporaryFile('user/media/clip.webm', TaggedAudioVideo::webm());
		$this->writeTemporaryFile('user/media/tone.wav', TaggedAudioVideo::wav());

		$app = $this->scratchApplication();
		$app->boot();
		$app->container()->make(MediaIndexer::class)->index();

		$records = $app->container()->make(MediaIndex::class)->snapshot()->records;
		$song    = $records['song.mp3'] ?? null;
		$clip    = $records['clip.webm'] ?? null;

		$this->assertSame(26.122, $song?->embedded?->values['duration'] ?? null);
		$this->assertSame([640, 360], [$clip?->width, $clip?->height], 'A video\'s size comes from what it says.');
		$this->assertSame('audio/wav', ($records['tone.wav'] ?? null)?->mime, 'A WAV is audio/wav, whatever the magic database calls it.');
	}
}
