<?php

/**
 * File component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component\Media;

use NumberFormatter;
use Override;
use Blush\Core\AppConfig;
use Blush\Media\MediaResolver;
use Blush\View\Component\Component;
use Blush\View\Component\ComponentContent;
use Blush\View\Component\MediaProp;

/**
 * A download link for a file (D-175, D-179):
 * `::file[The annual report]{src=report.pdf}`. `src` is resolved like an
 * image's; the label is the link text, or the file's name without one.
 * The template gets the file's `$format` (its extension, such as `PDF`)
 * and, for a file in the media folder or the entry's bundle, its `$size`
 * (such as `1.2 MB`, in the site's number format).
 *
 * Only the media types the site allows are served (`MediaConfig`), so a
 * local file of another type has no size and won't download.
 */
final class File extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Text;

	/**
	 * The file's name, from the end of its URL.
	 */
	public readonly string $name;

	/**
	 * The file's extension in capitals, or `''`.
	 */
	public readonly string $format;

	/**
	 * The file's size for people, or `''` when it isn't local media.
	 */
	public readonly string $size;

	public function __construct(
		MediaResolver $media,
		AppConfig $app,
		#[MediaProp] public readonly string $src = ''
	) {
		$path = (string) preg_replace('/[?#].*$/s', '', $src);
		$file = $src === '' ? null : $media->resolve($src);

		$this->name   = rawurldecode(basename($path));
		$this->format = strtoupper(pathinfo($this->name, PATHINFO_EXTENSION));
		$this->size   = $file === null ? '' : self::bytes($file->size, $app->locale);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function shouldRender(): bool
	{
		return $this->src !== '';
	}

	/**
	 * Returns a byte count in the largest unit that keeps it at 1 or
	 * more (1,024 to a unit), with at most one decimal place.
	 */
	private static function bytes(int $bytes, string $locale): string
	{
		$units  = ['B', 'KB', 'MB', 'GB', 'TB'];
		$unit   = 0;
		$amount = (float) $bytes;

		while ($amount >= 1024 && $unit < count($units) - 1) {
			$amount /= 1024;
			$unit++;
		}

		$formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
		$formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $unit === 0 ? 0 : 1);

		return ($formatter->format($amount) ?: (string) round($amount, 1)) . ' ' . $units[$unit];
	}
}
