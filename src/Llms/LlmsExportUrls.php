<?php

/**
 * Markdown page and llms.txt export URLs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Llms;

use Override;
use Blush\Export\ExportUrl;
use Blush\Export\UrlSource;

/**
 * Lists `/llms.txt` and every Markdown page for static export, when
 * they're on.
 */
final readonly class LlmsExportUrls implements UrlSource
{
	public function __construct(
		private MarkdownPages $pages,
		private LlmsConfig $config
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function urls(): iterable
	{
		if (! $this->config->enabled) {
			return;
		}

		yield new ExportUrl('/llms.txt');

		foreach ($this->pages->entries() as $entry) {
			$path = $this->pages->url($entry);

			if ($path !== null) {
				yield new ExportUrl($path);
			}
		}
	}
}
