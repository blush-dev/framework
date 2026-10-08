<?php

/**
 * PDF reader.
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
 * Reads PDFs (D-551): the version its header gives (`format`, "PDF
 * 1.7"); how many pages its page tree counts; the first page's size
 * (`pageSize`, named when it's a common one); and its document
 * information: title, author, subject, keywords, what made it
 * (`software`), what wrote the PDF (`producer`), and when. Objects are
 * found by their headers, and those in compressed object streams
 * (Flate) are read too when PHP has `zlib`; the information of an
 * encrypted PDF can't be.
 * A file over 16 MB is read at its start and end, where a PDF keeps its
 * structure, and may give less.
 */
final readonly class PdfReader implements EmbeddedReader
{
	/**
	 * The largest file read whole, and how much of each end of a larger
	 * one is read.
	 */
	private const int WHOLE = 16_777_216;

	private const int PART = 4_194_304;

	/**
	 * Document information by what it holds.
	 *
	 * @var array<string, string>
	 */
	private const array INFO = [
		'Title'        => 'title',
		'Author'       => 'creator',
		'Subject'      => 'description',
		'Keywords'     => 'keywords',
		'Creator'      => 'software',
		'Producer'     => 'producer',
		'CreationDate' => 'created'
	];

	/**
	 * Common page sizes by name, in points, and the unit each is given in.
	 *
	 * @var array<string, array{int, int, string}>
	 */
	private const array SIZES = [
		'Letter'  => [612, 792, 'in'],
		'Legal'   => [612, 1008, 'in'],
		'Tabloid' => [792, 1224, 'in'],
		'A3'      => [842, 1191, 'mm'],
		'A4'      => [595, 842, 'mm'],
		'A5'      => [420, 595, 'mm']
	];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(string $path, string $mime): EmbeddedMetadata
	{
		if ($mime !== 'application/pdf') {
			return new EmbeddedMetadata();
		}

		$file = new BinaryFile($path);

		if (preg_match('/%PDF-(\d\.\d)/', $file->read(0, 1024), $version) !== 1) {
			return new EmbeddedMetadata();
		}

		$bytes   = $file->size <= self::WHOLE
			? $file->read(0, $file->size)
			: $file->read(0, self::PART) . "\n" . $file->read($file->size - self::PART, self::PART);
		$objects = self::objects($bytes);
		$values  = ['format' => "PDF {$version[1]}", ...self::pages($bytes, $objects)];

		// An encrypted file's strings are encrypted too.
		if (preg_match('/\/Encrypt\s*(?:\d+\s+\d+\s+R|<<)/', $bytes) === 1) {
			return new EmbeddedMetadata($values);
		}

		$info = self::reference($bytes, 'Info');
		$dict = $info === null ? '' : ($objects[$info] ?? '');

		foreach (self::INFO as $name => $key) {
			$text = self::text(self::value($dict, $name, $objects));

			$values[$key] = match ($key) {
				'created'  => self::date($text),
				'keywords' => preg_split('/\s*[,;]\s*/', $text) ?: [],
				default    => $text
			};
		}

		return new EmbeddedMetadata($values);
	}

	/**
	 * Every object's contents by number, without stream data, a later one
	 * of a number (an update) winning; the objects in a compressed object
	 * stream come in where it is.
	 *
	 * @return array<int, string>
	 */
	private static function objects(string $bytes): array
	{
		$objects = [];

		if (preg_match_all('/(?<!\d)(\d+)\s+\d+\s+obj\b/', $bytes, $found, PREG_OFFSET_CAPTURE) === false) {
			return [];
		}

		foreach ($found[0] as $i => [$header, $offset]) {
			$start  = $offset + strlen($header);
			$end    = strpos($bytes, 'endobj', $start);
			$body   = substr($bytes, $start, ($end === false ? strlen($bytes) : $end) - $start);
			$stream = strpos($body, 'stream');
			$dict   = $stream === false ? $body : substr($body, 0, $stream);

			$objects[(int) $found[1][$i][0]] = trim($dict);

			if ($stream !== false && preg_match('/\/Type\s*\/ObjStm\b/', $dict) === 1 && str_contains($dict, '/FlateDecode')) {
				$objects = array_replace($objects, self::packed($dict, substr($body, $stream + 6)));
			}
		}

		return $objects;
	}

	/**
	 * The objects in a compressed object stream: pairs of an object's
	 * number and its offset from `First`, then the objects.
	 *
	 * @return array<int, string>
	 */
	private static function packed(string $dict, string $stream): array
	{
		if (! function_exists('gzuncompress')) {
			return [];
		}

		$stream = ltrim($stream, "\r\n");
		$length = preg_match('/\/Length\s+(\d+)(?!\s+\d+\s+R)/', $dict, $match) === 1 ? (int) $match[1] : null;
		$end    = strrpos($stream, 'endstream');
		$data   = @gzuncompress($length === null ? rtrim(substr($stream, 0, $end === false ? null : $end), "\r\n") : substr($stream, 0, $length));
		$first  = preg_match('/\/First\s+(\d+)/', $dict, $match) === 1 ? (int) $match[1] : null;

		if (! is_string($data) || $first === null) {
			return [];
		}

		preg_match_all('/\d+/', substr($data, 0, $first), $numbers);

		$pairs   = array_chunk(array_map(intval(...), $numbers[0]), 2);
		$objects = [];

		foreach ($pairs as $i => $pair) {
			if (count($pair) === 2) {
				$next = $pairs[$i + 1][1] ?? strlen($data) - $first;

				$objects[$pair[0]] = trim(substr($data, $first + $pair[1], $next - $pair[1]));
			}
		}

		return $objects;
	}

	/**
	 * The page count and the first page's size, from the page tree the
	 * catalog names, else from any page tree node.
	 *
	 * @param  array<int, string>   $objects
	 * @return array<string, mixed>
	 */
	private static function pages(string $bytes, array $objects): array
	{
		$root  = self::reference($bytes, 'Root');
		$node  = $root === null ? null : self::ref($objects[$root] ?? '', 'Pages');
		$count = $node === null ? null : self::number(self::value($objects[$node] ?? '', 'Count', $objects));

		if ($count === null) {
			foreach ($objects as $object) {
				if (preg_match('/\/Type\s*\/Pages\b/', $object) === 1) {
					$count = max($count ?? 0, self::number(self::value($object, 'Count', $objects)) ?? 0);
				}
			}
		}

		// The first page's media box, inherited from the nearest node
		// above it that has one.
		$box  = null;
		$seen = 0;

		while ($node !== null && $seen++ < 32) {
			$dict = $objects[$node] ?? '';
			$box  = self::box(self::value($dict, 'MediaBox', $objects)) ?? $box;
			$kids = self::value($dict, 'Kids', $objects) ?? '';
			$node = preg_match('/(\d+)\s+\d+\s+R/', $kids, $kid) === 1 ? (int) $kid[1] : null;
		}

		if ($box === null && preg_match('/\/MediaBox\s*(\[[^\]]*\])/', $bytes, $first) === 1) {
			$box = self::box($first[1]);
		}

		return [
			'pages'    => $count === null || $count === 0 ? null : $count,
			'pageSize' => $box === null ? null : self::pageSize(...$box)
		];
	}

	/**
	 * A width and height from a rectangle, `[x1 y1 x2 y2]`.
	 *
	 * @return ?array{float, float}
	 */
	private static function box(?string $value): ?array
	{
		if ($value === null || preg_match('/\[\s*(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s*\]/', $value, $edges) !== 1) {
			return null;
		}

		$width  = abs((float) $edges[3] - (float) $edges[1]);
		$height = abs((float) $edges[4] - (float) $edges[2]);

		return $width > 0 && $height > 0 ? [$width, $height] : null;
	}

	/**
	 * A page size in words: "8.5 × 11 in (Letter)", "210 × 297 mm (A4)".
	 * A size without a name is in inches when it's in half inches, else
	 * in millimeters.
	 */
	private static function pageSize(float $width, float $height): string
	{
		foreach (self::SIZES as $name => [$short, $long, $unit]) {
			if ((abs($width - $short) <= 2 && abs($height - $long) <= 2) || (abs($width - $long) <= 2 && abs($height - $short) <= 2)) {
				return self::measure($width, $height, $unit) . " ({$name})";
			}
		}

		$halves = static fn (float $points): bool => abs($points / 36 - round($points / 36)) < 0.02;

		return self::measure($width, $height, $halves($width) && $halves($height) ? 'in' : 'mm');
	}

	private static function measure(float $width, float $height, string $unit): string
	{
		$size = static fn (float $points): string => $unit === 'in'
			? rtrim(rtrim(number_format($points / 72, 2, '.', ''), '0'), '.')
			: (string) (int) round($points / 72 * 25.4);

		return "{$size($width)} × {$size($height)} {$unit}";
	}

	/**
	 * The object a trailer (or cross-reference stream) names for a key,
	 * the last one written winning.
	 */
	private static function reference(string $bytes, string $key): ?int
	{
		return preg_match_all('/\/' . $key . '\s+(\d+)\s+\d+\s+R/', $bytes, $found) > 0 ? (int) end($found[1]) : null;
	}

	/**
	 * The object a dictionary's key refers to.
	 */
	private static function ref(string $dict, string $key): ?int
	{
		$token = self::token($dict, $key);

		return $token !== null && preg_match('/^(\d+)\s+\d+\s+R$/', $token, $match) === 1 ? (int) $match[1] : null;
	}

	/**
	 * A dictionary key's value as written, an object it refers to read in
	 * its place.
	 *
	 * @param array<int, string> $objects
	 */
	private static function value(string $dict, string $key, array $objects): ?string
	{
		$token = self::token($dict, $key);

		if ($token !== null && preg_match('/^(\d+)\s+\d+\s+R$/', $token, $match) === 1) {
			return $objects[(int) $match[1]] ?? null;
		}

		return $token;
	}

	/**
	 * The value after a key in a dictionary: a string (`(…)` or `<…>`), an
	 * array, a reference, a number, or a name.
	 */
	private static function token(string $dict, string $key): ?string
	{
		if (preg_match('/\/' . $key . '(?![A-Za-z0-9])\s*/', $dict, $match, PREG_OFFSET_CAPTURE) !== 1) {
			return null;
		}

		$at   = $match[0][1] + strlen($match[0][0]);
		$rest = substr($dict, $at);

		if (str_starts_with($rest, '(')) {
			$depth = 0;

			for ($i = 0; $i < strlen($rest); $i++) {
				$character = $rest[$i];

				if ($character === '\\') {
					$i++;
				} elseif ($character === '(') {
					$depth++;
				} elseif ($character === ')' && --$depth === 0) {
					return substr($rest, 0, $i + 1);
				}
			}

			return null;
		}

		$pattern = match (true) {
			str_starts_with($rest, '<<') => null,
			str_starts_with($rest, '<')  => '/^<[^>]*>/',
			str_starts_with($rest, '[')  => '/^\[[^\]]*\]/',
			default                      => '/^(?:\d+\s+\d+\s+R\b|-?[\d.]+|\/[^\s\/\[\]()<>]+)/'
		};

		return $pattern !== null && preg_match($pattern, $rest, $value) === 1 ? $value[0] : null;
	}

	/**
	 * A string's text: a literal one unescaped, a hex one decoded, and
	 * UTF-16 (marked by its byte order mark) made UTF-8. Other text is
	 * PDFDocEncoding, read as Latin-1.
	 */
	private static function text(?string $token): string
	{
		if ($token === null || $token === '') {
			return '';
		}

		if ($token[0] === '(') {
			// An escape: an octal code, a line break (the line goes on),
			// or a character.
			$text = (string) preg_replace_callback('/\\\\([0-7]{1,3}|\r\n|\r|\n|.)/s', static fn (array $escape): string => match (true) {
				preg_match('/^[0-7]+$/', $escape[1]) === 1       => chr(octdec($escape[1]) & 0xFF),
				in_array($escape[1], ["\r\n", "\r", "\n"], true) => '',
				default                                          => ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f"][$escape[1]] ?? $escape[1]
			}, substr($token, 1, -1));
		} elseif ($token[0] === '<') {
			$hex  = (string) preg_replace('/[^0-9A-Fa-f]/', '', $token);
			$text = (string) hex2bin(strlen($hex) % 2 === 1 ? "{$hex}0" : $hex);
		} else {
			return '';
		}

		if (str_starts_with($text, "\xFE\xFF")) {
			return (string) mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16BE');
		}

		return str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text;
	}

	/**
	 * A PDF date, `D:20260814110500-05'00'`, in the metadata's form.
	 */
	private static function date(string $text): string
	{
		if (preg_match("/^(?:D:)?(\d{4,14})(Z|[+-]\d{2}'?\d{2}'?)?/", trim($text), $parts) !== 1) {
			return '';
		}

		return EmbeddedMetadata::date($parts[1], str_replace("'", '', $parts[2] ?? ''));
	}

	/**
	 * A whole number, or `null`.
	 */
	private static function number(?string $value): ?int
	{
		return $value !== null && ctype_digit($value) ? (int) $value : null;
	}
}
