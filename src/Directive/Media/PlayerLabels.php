<?php

/**
 * Player labels.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive\Media;

/**
 * The labels of a directive's player (`<blush-audio-player>` and
 * `<blush-video-player>`, D-573), in the page's language: each
 * `label-{name}` attribute from the `player.{name}` message (a dash is
 * an underscore there), which a theme can reword.
 */
trait PlayerLabels
{
	/**
	 * Returns the player's label attributes, escaped.
	 */
	public function labelAttributes(): string
	{
		$attributes = [];

		foreach (self::LABELS as $name) {
			$attributes["label-{$name}"] = $this->t('player.' . str_replace('-', '_', $name));
		}

		return self::html($attributes);
	}
}
