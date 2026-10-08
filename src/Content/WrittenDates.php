<?php

/**
 * Written dates.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use DateMalformedStringException;
use DateTimeImmutable;
use Blush\Content\Parser\DocumentParser;
use Blush\Content\Parser\FrontMatter;
use Blush\Content\Parser\InvalidDocument;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\UnreadableSource;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;

/**
 * Reads the date an entry's file and folders are named by (D-512,
 * D-629): its publish date as its file writes it, in the offset written
 * there (`2013-02-09 00:00:00 -5` is the 9th, wherever the site is), or
 * in the site's time zone without one; without a publish date, its
 * `updated` date the same way.
 */
final readonly class WrittenDates
{
	public function __construct(
		private ContentTypes $types,
		private AppConfig $app,
		private ContentSource $source,
		private DocumentParser $parser
	) {}

	/**
	 * Returns an entry's publish date, else its updated date, as its file
	 * writes it. A date that isn't on the calendar (a `2007-00-00`
	 * placeholder, which PHP rolls over) is returned as written, so the
	 * caller can leave the file alone. `null` when it has neither, or the
	 * file can't be read.
	 */
	public function of(string $path, string $type): DateTimeImmutable|string|null
	{
		try {
			$contents    = $this->source->read($path);
			$frontMatter = $this->parser->parse($contents)->frontMatter;
		} catch (InvalidDocument | UnreadableSource) {
			return null;
		}

		$schema = $this->types->schema($type);

		foreach (['published', 'updated'] as $name) {
			$field = $schema->field($name);

			foreach ([$name, ...$field->aliases ?? []] as $key) {
				// As written: YAML hands dates over already rolled.
				$value = FrontMatter::written($contents, $key) ?? $frontMatter[$key] ?? null;

				if (is_string($value) && preg_match('/^\s*(\d{4})-(\d{2})-(\d{2})/', $value, $day) === 1) {
					if (! checkdate((int) $day[2], (int) $day[3], (int) $day[1])) {
						return "{$day[1]}-{$day[2]}-{$day[3]}";
					}

					try {
						return new DateTimeImmutable(trim($value), $this->app->timezone());
					} catch (DateMalformedStringException) {
						continue;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Returns an entry's written date, else a timestamp (its `updated`)
	 * in the site's time zone, or the date as written when it isn't on
	 * the calendar.
	 */
	public function orUpdated(string $path, string $type, int $updated): DateTimeImmutable|string
	{
		return $this->of($path, $type) ?? DateTimeImmutable::createFromTimestamp($updated)->setTimezone($this->app->timezone());
	}
}
