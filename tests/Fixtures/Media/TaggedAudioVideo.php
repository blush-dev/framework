<?php

/**
 * Sound and video files with embedded metadata, for tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Media;

/**
 * Builds small MP3, MP4, Ogg Vorbis and Opus, WAV, and WebM files, each
 * with tags, a known duration, and (for video) a size, written the way
 * each format writes them, so the readers can be tested without binary
 * fixtures. None holds real sound or pictures.
 */
final class TaggedAudioVideo
{
	/**
	 * An MP3: an ID3v2.3 tag (title, artist, album, track, genre by
	 * number, year, a UTF-16 comment, and a picture), an MPEG-1 Layer III
	 * frame with a Xing header counting 1,000 frames (26.122 s at 44.1
	 * kHz), and an ID3v1 tag, which the v2 tag wins over. Without Xing, a
	 * 128 kbit/s frame and 16,000 bytes of audio: one second.
	 */
	public static function mp3(bool $xing = true): string
	{
		$text   = static fn (string $id, string $value): string => self::frame($id, "\x03{$value}");
		$utf16  = "\xFF\xFE" . mb_convert_encoding('Recorded live', 'UTF-16LE', 'UTF-8');
		$frames = $text('TIT2', 'Morning Song')
			. $text('TPE1', 'The Harbors')
			. $text('TALB', 'Tides')
			. $text('TRCK', '3/12')
			. $text('TCON', '(17)Rock')
			. $text('TYER', '2019')
			. self::frame('COMM', "\x01eng\xFF\xFE\0\0{$utf16}")
			. self::frame('APIC', "\x00image/jpeg\x00\x03\x00" . str_repeat("\xAB", 2048));
		$size   = strlen($frames);
		$tag    = 'ID3' . "\x03\x00\x00" . implode('', array_map(static fn (int $shift): string => chr(($size >> $shift) & 0x7F), [21, 14, 7, 0])) . $frames;
		$frame  = "\xFF\xFB\x90\x00" . str_repeat("\0", 32);
		$frame .= $xing ? 'Xing' . pack('N', 1) . pack('N', 1000) : '';
		$audio  = str_pad($frame, $xing ? 417 : 16_000, "\0");
		$v1     = 'TAG' . str_pad('Old Title', 30, "\0") . str_pad('Old Artist', 30, "\0") . str_pad('Old Album', 30, "\0") . '2001' . str_pad('', 30, "\0") . "\x11";

		return $tag . $audio . $v1;
	}

	private static function frame(string $id, string $data): string
	{
		return $id . pack('N', strlen($data)) . "\x00\x00" . $data;
	}

	/**
	 * An MP4: a 1280×720 video track, 12.5 seconds, tagged with a title,
	 * artist, date, track, and cover art, and media data after the index.
	 */
	public static function mp4(): string
	{
		$item = static fn (string $type, string $value, int $kind = 1): string => self::box($type, self::box('data', pack('NN', $kind, 0) . $value));

		$mvhd = self::box('mvhd', pack('NNNNN', 0, 0, 0, 1000, 12500) . str_repeat("\0", 80));
		$tkhd = self::box('tkhd', pack('NNNNN', 0, 0, 0, 1, 0) . pack('N', 12500) . str_repeat("\0", 8) . str_repeat("\0", 8) . str_repeat("\0", 36) . pack('NN', 1280 << 16, 720 << 16));
		$ilst = self::box('ilst', $item("\xA9nam", 'Harbor Timelapse') . $item("\xA9ART", 'Jane Doe') . $item("\xA9day", '2023-06-01T10:00:00Z') . $item('trkn', pack('nnnn', 0, 2, 5, 0), 0) . $item('covr', "\xFF\xD8" . str_repeat("\x10", 3000), 13));
		$meta = self::box('meta', "\0\0\0\0" . self::box('hdlr', str_repeat("\0", 8) . 'mdirappl' . str_repeat("\0", 9)) . $ilst);

		return self::box('ftyp', 'isom' . pack('N', 512) . 'isomiso2mp41')
			. self::box('mdat', str_repeat("\0", 4096))
			. self::box('moov', $mvhd . self::box('trak', $tkhd) . self::box('udta', $meta));
	}

	private static function box(string $type, string $data): string
	{
		return pack('N', strlen($data) + 8) . $type . $data;
	}

	/**
	 * An Ogg Vorbis stream at 44.1 kHz, ten seconds long, or an Opus one
	 * at 48 kHz with a 312-sample pre-skip, five seconds long.
	 */
	public static function ogg(bool $opus = false): string
	{
		$identity = $opus
			? 'OpusHead' . "\x01\x02" . pack('v', 312) . pack('V', 44100) . "\0\0\0"
			: "\x01vorbis" . pack('V', 0) . "\x02" . pack('V', 44100) . str_repeat("\0", 12) . "\xB8\x01";
		$vendor   = $opus ? 'libopus 1.4' : 'Xiph.Org libVorbis I 20200704';
		$comments = ['TITLE=Evening Song', 'ARTIST=The Harbors', 'ALBUM=Tides', 'DATE=2021-04', 'TRACKNUMBER=4', 'METADATA_BLOCK_PICTURE=' . base64_encode(pack('N', 3) . pack('N', 9) . 'image/png' . pack('N', 0) . str_repeat("\0", 16) . pack('N', 40) . str_repeat("\x20", 40))];
		$tags     = ($opus ? 'OpusTags' : "\x03vorbis") . pack('V', strlen($vendor)) . $vendor . pack('V', count($comments));

		foreach ($comments as $comment) {
			$tags .= pack('V', strlen($comment)) . $comment;
		}

		$tags    .= $opus ? '' : "\x01";
		$granule  = $opus ? 312 + 48000 * 5 : 441000;

		return self::page(2, 0, 0, $identity) . self::page(0, 0, 1, $tags) . self::page(4, $granule, 2, "\0");
	}

	/**
	 * One Ogg page holding one packet.
	 */
	private static function page(int $type, int $granule, int $sequence, string $packet): string
	{
		$table = str_repeat("\xFF", intdiv(strlen($packet), 255)) . chr(strlen($packet) % 255);

		return 'OggS' . "\x00" . pack('C', $type) . pack('P', $granule) . pack('V', 1234) . pack('V', $sequence) . pack('V', 0) . pack('C', strlen($table)) . $table . $packet;
	}

	/**
	 * A WAV: 16-bit mono at 8 kHz (16,000 bytes a second), two seconds of
	 * silence, with an INFO list.
	 */
	public static function wav(): string
	{
		$info  = 'INFO' . self::chunk('INAM', "Tone\0") . self::chunk('IART', "Jane Doe\0") . self::chunk('ICRD', "2020\0");
		$body  = 'WAVE' . self::chunk('fmt ', pack('vvVVvv', 1, 1, 8000, 16000, 2, 16)) . self::chunk('LIST', $info) . self::chunk('data', str_repeat("\0", 32000));

		return 'RIFF' . pack('V', strlen($body)) . $body;
	}

	private static function chunk(string $id, string $data): string
	{
		return $id . pack('V', strlen($data)) . $data . (strlen($data) % 2 === 1 ? "\0" : '');
	}

	/**
	 * A WebM: a 640×360 video, 3.5 seconds, titled, in a segment of
	 * unknown size (as live streams write it), then a cluster.
	 */
	public static function webm(): string
	{
		// Short sizes in a byte, as real files write them; long ones in eight.
		$element = static fn (string $id, string $data): string => $id . (strlen($data) < 127 ? chr(0x80 | strlen($data)) : "\x01" . substr(pack('J', strlen($data)), 1)) . $data;
		$info    = $element("\x2A\xD7\xB1", "\x0F\x42\x40") . $element("\x44\x89", pack('E', 3500.0)) . $element("\x7B\xA9", 'Harbor Clip') . $element("\x57\x41", 'Blush Test');
		$video   = $element("\xB0", "\x02\x80") . $element("\xBA", "\x01\x68");
		$tracks  = $element("\xAE", $element("\xE0", $video));

		return $element("\x1A\x45\xDF\xA3", $element("\x42\x82", 'webm'))
			. "\x18\x53\x80\x67\x01\xFF\xFF\xFF\xFF\xFF\xFF\xFF"
			. $element("\x15\x49\xA9\x66", $info)
			. $element("\x16\x54\xAE\x6B", $tracks)
			. $element("\x1F\x43\xB6\x75", str_repeat("\0", 64));
	}
}
