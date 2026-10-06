<?php

/**
 * Documents with embedded metadata, for tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Media;

/**
 * Builds small PDFs with document information and a page tree, written
 * the way PDFs write them, so the PDF reader can be tested without
 * binary fixtures. None has real pages.
 */
final class TaggedDocument
{
	/**
	 * A PDF 1.7 of twelve Letter pages (the size on the page tree), with
	 * its information in literal, escaped, and UTF-16 hex strings; or
	 * encrypted, so its information can't be read.
	 */
	public static function pdf(bool $encrypted = false): string
	{
		$info = '<< /Title (Meridian Style Guide) /Author <FEFF004E0061006400690061> /Subject (How we write \(and why\)) '
			. "/Keywords (style, brand; voice) /Creator (Word) /Producer (Adobe PDF Library 17.0) /CreationDate (D:20260814110500-05'00') >>";

		return "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n"
			. self::object(1, '<< /Type /Catalog /Pages 2 0 R >>')
			. self::object(2, '<< /Type /Pages /Kids [3 0 R] /Count 12 /MediaBox [0 0 612 792] >>')
			. self::object(3, '<< /Type /Page /Parent 2 0 R >>')
			. self::object(4, $info)
			. 'trailer << /Size 5 /Root 1 0 R /Info 4 0 R' . ($encrypted ? ' /Encrypt 9 0 R' : '') . " >>\n%%EOF\n";
	}

	/**
	 * A PDF 1.5 with its page tree and information in a compressed object
	 * stream: three A4 pages (the size on the page itself), and a title in
	 * an object of its own.
	 */
	public static function packedPdf(): string
	{
		$pages  = '<< /Type /Pages /Kids [3 0 R] /Count 3 >>';
		$info   = '<< /Title 5 0 R /Author (Ana) >>';
		$header = '2 0 4 ' . (strlen($pages) + 1) . ' ';
		$stream = (string) gzcompress($header . $pages . ' ' . $info);

		return "%PDF-1.5\n"
			. self::object(1, '<< /Type /Catalog /Pages 2 0 R >>')
			. self::object(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] >>')
			. self::object(5, '(Packed \(title\))')
			. self::object(6, '<< /Type /ObjStm /N 2 /First ' . strlen($header) . ' /Filter /FlateDecode /Length ' . strlen($stream) . " >>\nstream\n{$stream}\nendstream")
			. "trailer << /Size 7 /Root 1 0 R /Info 4 0 R >>\n%%EOF\n";
	}

	private static function object(int $number, string $body): string
	{
		return "{$number} 0 obj\n{$body}\nendobj\n";
	}
}
