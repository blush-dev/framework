<?php

/**
 * Fixture: a view engine for `.tpl` files.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\View;

use Override;
use Blush\View\Engine\ViewEngine;
use Blush\View\Template;

/**
 * Replaces `{name}` with escaped data, and `{layout:x}`, `{include:x}`,
 * `{section:x}`, `{component:x}`, and `{set:x}` with what `Template` does.
 */
final class TokenEngine implements ViewEngine
{
	#[Override]
	public function render(string $file, array $data, Template $template): string
	{
		return (string) preg_replace_callback(
			'#\{(\w+)(?::([\w/-]+))?\}#',
			static function (array $match) use ($data, $template): string {
				$argument = $match[2] ?? '';

				switch ($match[1]) {
					case 'layout':
						$template->layout($argument);

						return '';
					case 'set':
						$template->setSection($argument, '<b>set</b>');

						return '';
				}

				return match ($match[1]) {
					'include'   => $template->include($argument),
					'section'   => $template->section($argument),
					'component' => (string) $template->component($argument),
					default     => e(is_scalar($data[$match[1]] ?? null) ? (string) $data[$match[1]] : '')
				};
			},
			(string) file_get_contents($file)
		);
	}
}
