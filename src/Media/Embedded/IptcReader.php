<?php

/**
 * IPTC reader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Embedded;

use Override;

/**
 * Reads IPTC-IIM (D-289), the captioning block newsrooms and photo
 * libraries write into JPEGs: the headline or object name, the caption,
 * the byline, the copyright notice, the credit, the keywords, and when it
 * was made. Text is made UTF-8 where an older file wrote Latin-1.
 */
final readonly class IptcReader implements EmbeddedReader
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $path, string $mime): EmbeddedMetadata
	{
		if ($mime !== 'image/jpeg') {
			return new EmbeddedMetadata();
		}

		$info = [];

		if (@getimagesize($path, $info) === false || ! is_array($info) || ! is_string($info['APP13'] ?? null)) {
			return new EmbeddedMetadata();
		}

		$data = iptcparse($info['APP13']);

		if (! is_array($data)) {
			return new EmbeddedMetadata();
		}

		$first = static fn (string $tag): string => is_array($data[$tag] ?? null) && is_string($data[$tag][0] ?? null) ? $data[$tag][0] : '';
		$title = $first('2#105') !== '' ? $first('2#105') : $first('2#005');

		return new EmbeddedMetadata([
			'title'       => $title,
			'description' => $first('2#120'),
			'creator'     => implode(', ', array_filter(is_array($data['2#080'] ?? null) ? $data['2#080'] : [], is_string(...))),
			'copyright'   => $first('2#116'),
			'credit'      => $first('2#110'),
			'keywords'    => array_values(array_filter(is_array($data['2#025'] ?? null) ? $data['2#025'] : [], is_string(...))),
			'created'     => $first('2#055') === '' ? '' : EmbeddedMetadata::date($first('2#055') . ($first('2#060') === '' ? '' : 'T' . $first('2#060')))
		]);
	}
}
