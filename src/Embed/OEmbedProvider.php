<?php

/**
 * oEmbed provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

/**
 * A provider defined in `config/embed.php` (D-184): its name, label,
 * URL schemes, and endpoint, with the base behavior (the iframe in its
 * HTML).
 *
 *     new OEmbedProvider('streamable', 'Streamable', ['https://streamable.com/*'], 'https://api.streamable.com/oembed.json')
 */
final class OEmbedProvider extends EmbedProvider
{
	/**
	 * @param  list<string> $schemes
	 * @throws EmbedException When the endpoint isn't HTTPS.
	 */
	public function __construct(string $name, string $label, array $schemes, string $endpoint)
	{
		parent::__construct($name, $label, $schemes, $endpoint);
	}

	/**
	 * Returns the provider as config data.
	 *
	 * @return array{name: string, label: string, schemes: list<string>, endpoint: string}
	 */
	public function toArray(): array
	{
		return ['name' => $this->name, 'label' => $this->label, 'schemes' => $this->schemes, 'endpoint' => $this->endpoint ?? ''];
	}
}
