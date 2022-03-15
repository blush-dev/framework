<?php
/**
 * Markdown parser contract.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Markdown;

interface Parser
{
	/**
	 * Converts Markdown to HTML.
	 *
	 * @since 1.0.0
	 */
        public function convert( string $content ): self;

	/**
	 * Returns Markdown HTML.
	 *
	 * @since 1.0.0
	 */
        public function content(): string;

	/**
	 * Returns YAML front matter.
	 *
	 * @since 1.0.0
	 */
        public function frontMatter(): array;
}
