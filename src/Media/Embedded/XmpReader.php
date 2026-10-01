<?php

/**
 * XMP reader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Embedded;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Override;

/**
 * Reads XMP (D-289), the RDF packet any format can carry (JPEG, PNG,
 * WebP, GIF, AVIF, TIFF, and SVG's `<metadata>`), found by its markers in
 * the file's first bytes, so no format needs parsing. A property may be
 * written as an attribute of an `rdf:Description` or as an element, a
 * language alternative (`rdf:Alt`, the default language first), or a list
 * (`rdf:Seq`, `rdf:Bag`); each is read either way. External entities and
 * the network are never loaded.
 */
final readonly class XmpReader implements EmbeddedReader
{
	/**
	 * How much of a file is looked through for the packet.
	 */
	private const int LIMIT = 8_388_608;

	/**
	 * The namespaces read, by the prefix used here.
	 *
	 * @var array<string, string>
	 */
	private const array NAMESPACES = [
		'rdf'       => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
		'dc'        => 'http://purl.org/dc/elements/1.1/',
		'xmp'       => 'http://ns.adobe.com/xap/1.0/',
		'photoshop' => 'http://ns.adobe.com/photoshop/1.0/',
		'exif'      => 'http://ns.adobe.com/exif/1.0/',
		'exifEX'    => 'http://cipa.jp/exif/1.0/',
		'aux'       => 'http://ns.adobe.com/exif/1.0/aux/',
		'tiff'      => 'http://ns.adobe.com/tiff/1.0/'
	];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $path, string $mime): EmbeddedMetadata
	{
		$packet = self::packet($path);

		if ($packet === null) {
			return new EmbeddedMetadata();
		}

		$document = new DOMDocument();

		if (! @$document->loadXML($packet, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
			return new EmbeddedMetadata();
		}

		$xpath = new DOMXPath($document);

		foreach (self::NAMESPACES as $prefix => $uri) {
			$xpath->registerNamespace($prefix, $uri);
		}

		$one  = fn (string $name): string => implode(', ', self::values($xpath, $name));
		$make = $one('tiff:Make');

		return new EmbeddedMetadata([
			'title'       => $one('dc:title'),
			'description' => $one('dc:description'),
			'creator'     => $one('dc:creator'),
			'copyright'   => $one('dc:rights'),
			'credit'      => $one('photoshop:Credit'),
			'keywords'    => self::values($xpath, 'dc:subject'),
			'created'     => EmbeddedMetadata::date(array_find([$one('photoshop:DateCreated'), $one('exif:DateTimeOriginal'), $one('xmp:CreateDate')], static fn (string $date): bool => $date !== '') ?? ''),
			'camera'      => trim(str_starts_with(strtolower($one('tiff:Model')), strtolower(strtok($make, ' ') ?: '')) && $make !== '' ? $one('tiff:Model') : "{$make} {$one('tiff:Model')}"),
			'lens'        => array_find([$one('exifEX:LensModel'), $one('aux:Lens')], static fn (string $lens): bool => $lens !== '') ?? '',
			'orientation' => is_numeric($one('tiff:Orientation')) ? (int) $one('tiff:Orientation') : null,
			'software'    => $one('xmp:CreatorTool')
		], self::location($one('exif:GPSLatitude'), $one('exif:GPSLongitude')));
	}

	/**
	 * The XMP packet in a file's first bytes: `x:xmpmeta`, or a bare
	 * `rdf:RDF` (SVG writes one in its `<metadata>`), or `null`.
	 */
	private static function packet(string $path): ?string
	{
		$bytes = @file_get_contents($path, false, null, 0, self::LIMIT);

		if (! is_string($bytes)) {
			return null;
		}

		foreach (['<x:xmpmeta' => '</x:xmpmeta>', '<rdf:RDF' => '</rdf:RDF>'] as $open => $close) {
			$start = strpos($bytes, $open);
			$end   = $start === false ? false : strpos($bytes, $close, $start);

			if ($start !== false && $end !== false) {
				$packet = substr($bytes, $start, $end - $start + strlen($close));

				// A bare `rdf:RDF` may rely on namespaces declared above it.
				return $open === '<rdf:RDF' && ! str_contains($packet, 'xmlns:rdf=')
					? str_replace('<rdf:RDF', '<rdf:RDF xmlns:rdf="' . self::NAMESPACES['rdf'] . '"', $packet)
					: $packet;
			}
		}

		return null;
	}

	/**
	 * A property's values, however it's written: an attribute, an
	 * element's text, a language alternative (the default first, else the
	 * first), or a list's items.
	 *
	 * @return list<string>
	 */
	private static function values(DOMXPath $xpath, string $name): array
	{
		$attribute = $xpath->query("//rdf:Description/@{$name}");

		if ($attribute !== false && $attribute->length > 0) {
			return [EmbeddedMetadata::text((string) $attribute->item(0)?->nodeValue)];
		}

		$element = self::elements($xpath, "//rdf:Description/{$name}")[0] ?? null;

		if ($element === null) {
			return [];
		}

		$default = self::elements($xpath, 'rdf:Alt/rdf:li[@xml:lang="x-default"]', $element)[0] ?? null;

		if ($default !== null) {
			return [EmbeddedMetadata::text($default->textContent)];
		}

		$items  = self::elements($xpath, 'rdf:Alt/rdf:li | rdf:Seq/rdf:li | rdf:Bag/rdf:li', $element);
		$values = [];

		foreach ($items as $item) {
			$values[] = EmbeddedMetadata::text($item->textContent);

			// A language alternative has one value to show.
			if ($item->parentNode instanceof DOMElement && $item->parentNode->localName === 'Alt') {
				break;
			}
		}

		if ($items !== []) {
			return array_values(array_filter($values, static fn (string $value): bool => $value !== ''));
		}

		$text = EmbeddedMetadata::text($element->textContent);

		return $text === '' ? [] : [$text];
	}

	/**
	 * The elements a query finds.
	 *
	 * @return list<DOMElement>
	 */
	private static function elements(DOMXPath $xpath, string $query, ?DOMElement $context = null): array
	{
		$found = $xpath->query($query, $context);
		$list  = [];

		if ($found !== false) {
			foreach ($found as $node) {
				if ($node instanceof DOMElement) {
					$list[] = $node;
				}
			}
		}

		return $list;
	}

	/**
	 * XMP writes GPS as `DDD,MM.mmK` or `DDD,MM,SSK` (K is N, S, E, or W).
	 *
	 * @return ?array{lat: float, lng: float}
	 */
	private static function location(string $lat, string $lng): ?array
	{
		$lat = self::degrees($lat);
		$lng = self::degrees($lng);

		return $lat === null || $lng === null ? null : ['lat' => $lat, 'lng' => $lng];
	}

	private static function degrees(string $value): ?float
	{
		if (preg_match('/^\s*(\d+),(\d+(?:\.\d+)?)(?:,(\d+(?:\.\d+)?))?\s*([NSEW])\s*$/i', $value, $parts) !== 1) {
			return null;
		}

		// An unmatched optional group is `''`, which is 0.
		$degrees = (float) $parts[1] + (float) $parts[2] / 60 + (float) $parts[3] / 3600;

		return round(in_array(strtoupper($parts[4]), ['S', 'W'], true) ? -$degrees : $degrees, 6);
	}
}
