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

namespace Blush\Component\Media;

use NumberFormatter;
use Override;
use Blush\Component\Component;
use Blush\Component\ComponentContent;
use Blush\Component\ComponentView;
use Blush\Component\MediaProp;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Media\MediaResolver;

/**
 * A download link for a file (D-175, D-179):
 * `::file[The annual report]{src=report.pdf}`. `src` is resolved like an
 * image's; the label is the link text, or the file's name without one.
 * It knows the file's `format` (its extension, such as `PDF`) and, for a
 * file in the media folder, its `size` (such as
 * `1.2 MB`, in the page's number format); `details()` joins them.
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

	// phpcs:disable -- PHPCS 4.0 doesn't tokenize property hooks yet.
	/**
	 * The file's size for people, or `''` when it isn't local media.
	 */
	public string $size {
		get => $this->bytes === null ? '' : self::bytes($this->bytes, $this->locale($this->app->locale));
	}
	// phpcs:enable

	/**
	 * The file's size in bytes, or `null` when it isn't local media.
	 */
	private readonly ?int $bytes;

	public function __construct(
		MediaResolver $media,
		private readonly AppConfig $app,
		#[MediaProp] public readonly string $src = '',
		public readonly string $label = ''
	) {
		$path = (string) preg_replace('/[?#].*$/s', '', $src);
		$file = $src === '' ? null : $media->resolve($src);

		$this->name   = rawurldecode(basename($path));
		$this->format = strtoupper(pathinfo($this->name, PATHINFO_EXTENSION));
		$this->bytes  = $file?->size;
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
	 * Returns the link's text, as HTML: the content, else the label, else
	 * the file's name.
	 */
	public function text(): string
	{
		return $this->contentOr($this->label !== '' ? $this->label : $this->name);
	}

	/**
	 * Returns the file's format and size for people ("PDF, 1.2 MB", from
	 * the theme's `media.file_details` text), either one alone, or `''`.
	 */
	public function details(): string
	{
		return match (true) {
			$this->format !== '' && $this->size !== '' => $this->t('media.file_details', format: $this->format, size: $this->size),
			default                                    => $this->format . $this->size
		};
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

	/**
	 * Renders the framework's template for it, `resources/components/file.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): ComponentView
	{
		return $this->view(Framework::path('resources/components/file.php'));
	}
}
