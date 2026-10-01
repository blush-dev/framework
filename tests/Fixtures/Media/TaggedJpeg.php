<?php

/**
 * A JPEG with embedded metadata, for tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Media;

/**
 * Builds a 2×1 JPEG carrying EXIF (camera, exposure, date, artist,
 * copyright, and GPS), IPTC (headline, caption, byline, credit,
 * keywords), and an XMP packet, each as its format writes it, so the
 * readers can be tested without a binary fixture.
 */
final class TaggedJpeg
{
	/**
	 * A plain 2×1 JPEG.
	 */
	private const string BASE = '/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2NjIpLCBxdWFsaXR5ID0gNTAK/9sAQwAQCwwODAoQDg0OEhEQExgoGhgWFhgxIyUdKDozPTw5Mzg3QEhcTkBEV0U3OFBtUVdfYmdoZz5NcXlwZHhcZWdj/9sAQwEREhIYFRgvGhovY0I4QmNjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2Nj/8AAEQgAAQACAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A8/ooooA//9k=';

	/**
	 * A plain JPEG, without metadata.
	 */
	public static function plain(): string
	{
		return (string) base64_decode(self::BASE, true);
	}

	/**
	 * The JPEG with all three, or with only the ones asked for.
	 *
	 * @param list<'exif'|'iptc'|'xmp'> $with
	 */
	public static function bytes(array $with = ['exif', 'iptc', 'xmp']): string
	{
		$jpeg     = self::plain();
		$segments = '';

		if (in_array('exif', $with, true)) {
			$segments .= self::segment("Exif\0\0" . self::exif());
		}

		if (in_array('xmp', $with, true)) {
			$segments .= self::segment("http://ns.adobe.com/xap/1.0/\0" . self::xmp());
		}

		// IPTC lives in a Photoshop resource block (8BIM 0x0404) in APP13,
		// as `iptcembed()` writes it.
		if (in_array('iptc', $with, true)) {
			$iptc      = self::iptc();
			$resource  = "8BIM\x04\x04\0\0" . pack('N', strlen($iptc)) . $iptc . (strlen($iptc) % 2 === 1 ? "\0" : '');
			$data      = "Photoshop 3.0\0" . $resource;
			$segments .= "\xFF\xED" . pack('n', strlen($data) + 2) . $data;
		}

		return substr($jpeg, 0, 2) . $segments . substr($jpeg, 2);
	}

	private static function segment(string $data): string
	{
		return "\xFF\xE1" . pack('n', strlen($data) + 2) . $data;
	}

	/**
	 * An IPTC block: headline, caption, byline (twice), credit, copyright,
	 * keywords, and date and time created.
	 */
	private static function iptc(): string
	{
		$data = '';

		foreach ([[105, 'Harbor at Dawn'], [120, 'Boats waiting for the tide.'], [80, 'Jane Doe'], [80, 'Sam Roe'], [110, 'Acme Photos'], [116, '© 2024 Jane Doe'], [25, 'harbor'], [25, 'boats'], [55, '20240501'], [60, '063000-0500']] as [$tag, $value]) {
			$data .= "\x1C\x02" . chr($tag) . pack('n', strlen($value)) . $value;
		}

		return $data;
	}

	/**
	 * An XMP packet: a title as a language alternative, a creator list,
	 * keywords in a bag, and the software as an attribute.
	 */
	private static function xmp(): string
	{
		return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?><x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
			. '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xmp="http://ns.adobe.com/xap/1.0/" xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/" xmp:CreatorTool="Darktable 4.6">'
			. '<dc:title><rdf:Alt><rdf:li xml:lang="fr">Port à l\'aube</rdf:li><rdf:li xml:lang="x-default">Harbor at First Light</rdf:li></rdf:Alt></dc:title>'
			. '<dc:creator><rdf:Seq><rdf:li>Jane Doe</rdf:li></rdf:Seq></dc:creator>'
			. '<dc:subject><rdf:Bag><rdf:li>harbor</rdf:li><rdf:li>sunrise</rdf:li></rdf:Bag></dc:subject>'
			. '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
	}

	/**
	 * A little-endian TIFF block: IFD0 (description, make, model,
	 * orientation, software, artist, copyright, and the EXIF and GPS
	 * pointers), the EXIF IFD (exposure, aperture, ISO, date, focal
	 * length, lens), and the GPS IFD (51.5° N, 0.1275° W).
	 */
	private static function exif(): string
	{
		$ifd0 = [
			[0x010E, 2, 'A harbor at dawn'],
			[0x010F, 2, 'Canon'],
			[0x0110, 2, 'Canon EOS R5'],
			[0x0112, 3, 1],
			[0x0131, 2, 'Lightroom'],
			[0x013B, 2, 'Jane Doe'],
			[0x8298, 2, 'Copyright Jane Doe'],
			[0x8769, 4, 0],
			[0x8825, 4, 0]
		];
		$exif = [
			[0x829A, 5, [[1, 250]]],
			[0x829D, 5, [[28, 10]]],
			[0x8827, 3, 400],
			[0x9003, 2, '2024:05:01 06:30:00'],
			[0x920A, 5, [[35, 1]]],
			[0xA434, 2, 'RF35mm F1.8']
		];
		$gps = [
			[0x0001, 2, 'N'],
			[0x0002, 5, [[51, 1], [30, 1], [0, 1]]],
			[0x0003, 2, 'W'],
			[0x0004, 5, [[0, 1], [7, 1], [39, 1]]]
		];

		// Lay out IFD0, the EXIF IFD, the GPS IFD, then each one's data.
		$size   = static fn (array $ifd): int => 2 + count($ifd) * 12 + 4;
		$start  = [8, 8 + $size($ifd0), 8 + $size($ifd0) + $size($exif)];
		$data   = 8 + $size($ifd0) + $size($exif) + $size($gps);
		$blocks = '';
		$tiff   = "II*\0" . pack('V', 8);

		foreach ([$ifd0, $exif, $gps] as $ifd) {
			$tiff .= pack('v', count($ifd));

			foreach ($ifd as [$tag, $type, $value]) {
				// The EXIF and GPS pointers, to where their IFDs start.
				if ($tag === 0x8769 || $tag === 0x8825) {
					$tiff .= pack('vvVV', $tag, $type, 1, $start[$tag === 0x8769 ? 1 : 2]);
				} elseif (is_string($value)) {
					$bytes = $value . "\0";

					if (strlen($bytes) <= 4) {
						$tiff .= pack('vvV', $tag, $type, strlen($bytes)) . str_pad($bytes, 4, "\0");
					} else {
						$tiff   .= pack('vvVV', $tag, $type, strlen($bytes), $data + strlen($blocks));
						$blocks .= $bytes;
					}
				} elseif (is_int($value)) {
					$tiff .= pack('vvVvv', $tag, $type, 1, $value, 0);
				} else {
					$tiff .= pack('vvVV', $tag, $type, count($value), $data + strlen($blocks));

					foreach ($value as [$numerator, $denominator]) {
						$blocks .= pack('VV', $numerator, $denominator);
					}
				}
			}

			$tiff .= pack('V', 0);
		}

		return $tiff . $blocks;
	}
}
