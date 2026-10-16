<?php

/**
 * Tool input check tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Data\DataLoader;
use Blush\Tool\InputCheck;
use Blush\Translation\CurrentLocale;
use Blush\Translation\Messages;
use Blush\Translation\Translator;

#[CoversClass(InputCheck::class)]
final class InputCheckTest extends TestCase
{
	/**
	 * @param array<string, mixed> $schema
	 */
	private function problem(mixed $value, array $schema, string $locale = 'en_US'): ?string
	{
		$current  = new CurrentLocale($locale);
		$messages = new Messages(translator: new Translator(new DataLoader(), $locale, [Translator::CORE => [dirname(__DIR__, 2) . '/resources/lang']]), locale: $current);
		$problem  = new InputCheck($messages)->problem($value, $schema);

		return $problem === null ? null : (string) $problem;
	}

	public function testSaysEachTypeInAWholeSentence(): void
	{
		$schema = ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'count' => ['type' => ['integer', 'null']], 'either' => ['type' => ['string', 'integer']]]];

		$this->assertSame('title must be text.', $this->problem(['title' => 3], $schema));
		$this->assertSame('count must be a whole number, or left empty.', $this->problem(['count' => 'many'], $schema), 'A type beside null is its own sentence (D-877).');
		$this->assertSame('either must be one of these JSON types: string, integer.', $this->problem(['either' => true], $schema), 'Other lists of types are named by their JSON names.');
		$this->assertNull($this->problem(['title' => 'Hello', 'count' => null], $schema));
	}

	public function testSaysLimitsAndNames(): void
	{
		$schema = ['type' => 'object', 'required' => ['title'], 'additionalProperties' => false, 'properties' => ['title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 3], 'tags' => ['type' => 'array', 'maxItems' => 1]]];

		$this->assertSame('input needs "title".', $this->problem([], $schema));
		$this->assertSame('title must be at most 3 characters.', $this->problem(['title' => 'Long'], $schema));
		$this->assertSame('title must be at least 1 character.', $this->problem(['title' => ''], $schema));
		$this->assertSame('tags must have at most 1 item.', $this->problem(['title' => 'Hi', 'tags' => ['a', 'b']], $schema));
		$this->assertSame('input has no "color"; it takes "title", "tags".', $this->problem(['title' => 'Hi', 'color' => 'red'], $schema));
	}

	public function testIsTranslated(): void
	{
		$this->assertSame('titleはテキストでなければなりません。', $this->problem(['title' => 3], ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]], 'ja'));
	}
}
