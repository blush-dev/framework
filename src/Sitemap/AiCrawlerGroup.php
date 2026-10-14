<?php

/**
 * AI crawler groups.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Sitemap;

use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The kinds of AI crawler a site may ask to stay away in `robots.txt`
 * (D-398), each with the user agents it covers. The three serve different
 * ends, so sites often treat them differently:
 *
 * - `Training`: collect pages to train models. `Google-Extended` and
 *   `Applebot-Extended` aren't crawlers but tokens that keep pages out of
 *   Google's and Apple's AI training while their search crawlers still
 *   come.
 * - `Search`: index pages so AI answers can cite and link them.
 * - `Fetchers`: fetch a page because a person asked their assistant
 *   about it; blocking them blocks readers' own tools.
 *
 * The lists follow the vendors' documentation and are kept up to date
 * with releases; a new crawler joins its group.
 */
enum AiCrawlerGroup: string
{
	case Training = 'training';
	case Search   = 'search';
	case Fetchers = 'fetchers';

	/**
	 * Returns the group's name, as the admin shows it.
	 */
	public function label(Messages $messages): Text
	{
		return match ($this) {
			self::Training => $messages->t('sitemap.ai_crawler_group.training.label', 'Training crawlers'),
			self::Search   => $messages->t('sitemap.ai_crawler_group.search.label', 'AI search crawlers'),
			self::Fetchers => $messages->t('sitemap.ai_crawler_group.fetchers.label', 'Fetchers acting for a person')
		};
	}

	/**
	 * Returns what the group's crawlers do, in a sentence, as the admin
	 * shows it under the name.
	 */
	public function description(Messages $messages): Text
	{
		return match ($this) {
			self::Training => $messages->t('sitemap.ai_crawler_group.training.description', 'Collect pages to train models.'),
			self::Search   => $messages->t('sitemap.ai_crawler_group.search.description', 'Index pages so an answer can cite and link them.'),
			self::Fetchers => $messages->t('sitemap.ai_crawler_group.fetchers.description', 'Get one page because a reader asked their assistant about it, so turning these away turns away a reader\'s own tools.')
		};
	}

	/**
	 * Returns the user agents the group covers, as `robots.txt` names
	 * them.
	 *
	 * @return list<string>
	 */
	public function agents(): array
	{
		return match ($this) {
			self::Training => ['GPTBot', 'ClaudeBot', 'CCBot', 'Google-Extended', 'Applebot-Extended', 'Bytespider', 'meta-externalagent'],
			self::Search   => ['OAI-SearchBot', 'Claude-SearchBot', 'PerplexityBot'],
			self::Fetchers => ['ChatGPT-User', 'Claude-User', 'Perplexity-User']
		};
	}
}
