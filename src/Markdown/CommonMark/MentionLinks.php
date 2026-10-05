<?php

/**
 * Mention links.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark;

use Override;
use League\CommonMark\Extension\Mention\Generator\MentionGeneratorInterface;
use League\CommonMark\Extension\Mention\Mention;
use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Inline\Text;
use Blush\Markdown\MentionResolver;

/**
 * Links a mention, `@name`, where the `MentionResolver` says (D-493),
 * keeping the text as written and adding the `mention` class. The `@` and
 * the name are spans of their own (`mention__at`, `mention__name`), so a
 * theme can style them apart:
 *
 *     <a class="mention" href="…"><span class="mention__at">@</span><span class="mention__name">jane</span></a>
 *
 * A name that isn't anyone's stays text.
 */
final readonly class MentionLinks implements MentionGeneratorInterface
{
	public function __construct(private MentionResolver $resolver)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function generateMention(Mention $mention): ?AbstractInline
	{
		$url = $this->resolver->url($mention->getIdentifier());

		if ($url === null) {
			return null;
		}

		$mention->setUrl($url);
		$mention->data->append('attributes/class', 'mention');
		$mention->detachChildren();

		foreach (['at' => $mention->getPrefix(), 'name' => $mention->getIdentifier()] as $part => $text) {
			$span = new BracketedSpan();
			$span->data->set('attributes', ['class' => "mention__{$part}"]);
			$span->appendChild(new Text($text));
			$mention->appendChild($span);
		}

		return $mention;
	}
}
