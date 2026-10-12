<?php

/**
 * Avatar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

/**
 * A profile's avatar, as the admin draws it (D-536): its picture when it
 * has one (D-809), else a square box with up to two initials of its
 * name, as an inline SVG. It's gray by default, and the theme does the
 * design: `--avatar-background` and `--avatar-color` set its colors, its
 * letters take the page's font, and `border-radius` on `.avatar` sets the
 * shape.
 */
final readonly class Avatar
{
	/**
	 * The box's ground and letters, unless the theme sets
	 * `--avatar-background` and `--avatar-color` (the admin's
	 * `--surface-3` and `--fg-2`).
	 */
	private const string BACKGROUND = '#e7eaf0';

	private const string COLOR = '#535b6b';

	/**
	 * @param string $name  The profile's name.
	 * @param int    $size  Its width and height, in pixels.
	 * @param string $image Its picture's URL, or `''` for its initials.
	 */
	public function __construct(
		public string $name,
		public int $size = 48,
		public string $image = ''
	) {}

	/**
	 * Returns up to two initials of the name, the same as the admin's
	 * `initials()`: "Jane Doe" is JD, and a name with none is `?`.
	 */
	public function initials(): string
	{
		$parts    = array_slice(preg_split('/\s+/u', $this->name, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 2);
		$initials = implode('', array_map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)), $parts));

		return $initials === '' ? '?' : $initials;
	}

	/**
	 * Returns the avatar: its picture as an `<img>`, cropped square, or
	 * its initials as an inline SVG. With a label, it's an image named by
	 * it; without one, it's hidden from assistive technology, for when the
	 * name is printed beside it.
	 */
	public function html(string $label = ''): string
	{
		$size = max(1, $this->size);

		if ($this->image !== '') {
			return sprintf(
				'<img class="avatar" src="%1$s" width="%2$d" height="%2$d" alt="%3$s" loading="lazy" decoding="async" style="object-fit: cover">',
				Escaper::url($this->image),
				$size,
				Escaper::attr($label)
			);
		}

		$a11y = $label === ''
			? 'aria-hidden="true"'
			: 'role="img" aria-label="' . Escaper::attr($label) . '"';

		return sprintf(
			'<svg class="avatar" width="%1$d" height="%1$d" viewBox="0 0 100 100" %2$s focusable="false">'
			. '<rect width="100" height="100" style="fill: var(--avatar-background, %3$s)"/>'
			. '<text x="50" y="50" text-anchor="middle" dominant-baseline="central" style="fill: var(--avatar-color, %4$s); font-family: inherit; font-size: 40px; font-weight: 600">%5$s</text>'
			. '</svg>',
			$size,
			$a11y,
			self::BACKGROUND,
			self::COLOR,
			Escaper::html($this->initials())
		);
	}
}
