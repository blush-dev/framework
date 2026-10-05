<?php

/**
 * Footnote options.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;

/**
 * How footnotes look (`MarkdownConfig::$footnotes`): the classes on the
 * list at the end of the body (`container`), on each reference to a note
 * in the text (`reference`), on each note (`note`), and on each note's
 * link back (`backReference`), and whether a rule goes above the list.
 */
final readonly class FootnoteOptions
{
	public function __construct(
		public string $container = 'footnotes',
		public string $reference = 'footnote-ref',
		public string $note = 'footnote',
		public string $backReference = 'footnote-backref',
		public bool $rule = true
	) {}

	/**
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidConfig
	 */
	public static function fromArray(array $data): self
	{
		$values = new ConfigValues($data, 'FootnoteOptions');
		$values->assertKnownKeys(['container', 'reference', 'note', 'backReference', 'rule']);

		return new self(
			container: $values->string('container', 'footnotes'),
			reference: $values->string('reference', 'footnote-ref'),
			note: $values->string('note', 'footnote'),
			backReference: $values->string('backReference', 'footnote-backref'),
			rule: $values->bool('rule', true)
		);
	}

	/**
	 * @return array{container: string, reference: string, note: string, backReference: string, rule: bool}
	 */
	public function toArray(): array
	{
		return [
			'container'     => $this->container,
			'reference'     => $this->reference,
			'note'          => $this->note,
			'backReference' => $this->backReference,
			'rule'          => $this->rule
		];
	}
}
