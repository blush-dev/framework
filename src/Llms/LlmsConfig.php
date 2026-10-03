<?php

/**
 * Markdown pages and llms.txt config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Llms;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;

/**
 * The site's settings for agents and language models, from
 * `config/llms.php` (D-395):
 *
 *     return new LlmsConfig(full: true);
 *
 * - `enabled` serves a Markdown version of every public page (its URL
 *   with `.md`) and `/llms.txt`, a Markdown map of the site. The summary
 *   under the site's name in `llms.txt` is the site's description
 *   (`AppConfig::$description`, D-398).
 * - `full` also serves `/llms-full.txt` (D-402): every page `llms.txt`
 *   lists, in full, in one file. Off by default, since it's large.
 */
final readonly class LlmsConfig implements Config
{
	public function __construct(
		public bool $enabled = true,
		public bool $full = false
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['enabled', 'full']);

		return new static(
			enabled: $values->bool('enabled', true),
			full: $values->bool('full', false)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['enabled' => $this->enabled, 'full' => $this->full];
	}
}
