<?php

/**
 * Symfony YAML parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Parser;

use DateTimeInterface;
use Override;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Blush\Data\InvalidData;

/**
 * The temporary `YamlParser` adapter over symfony/yaml (D-045, D-080).
 * Objects are refused, and timestamps are turned back into strings.
 *
 * Symfony parses a timestamp into a `DateTimeImmutable`. One written
 * without an offset gets the zone named `UTC`, while an explicit `Z` or
 * `+00:00` gets a zone named after it, so the two can be told apart and the
 * missing offset left out of the string.
 */
final readonly class SymfonyYamlParser implements YamlParser
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parse(string $yaml): mixed
	{
		try {
			$data = Yaml::parse($yaml, Yaml::PARSE_DATETIME | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
		} catch (ParseException $e) {
			throw new InvalidData(sprintf('Invalid YAML: %s', $e->getMessage()), previous: $e);
		}

		return $this->plain($data);
	}

	/**
	 * Replaces the timestamps in parsed data with strings.
	 */
	private function plain(mixed $value): mixed
	{
		if ($value instanceof DateTimeInterface) {
			return $value->getTimezone()->getName() === 'UTC'
				? $value->format('Y-m-d\TH:i:s')
				: $value->format(DateTimeInterface::ATOM);
		}

		if (is_array($value)) {
			return array_map($this->plain(...), $value);
		}

		if (is_object($value)) {
			throw new InvalidData(sprintf('YAML may not contain objects; found %s.', $value::class));
		}

		return $value;
	}
}
