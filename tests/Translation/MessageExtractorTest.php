<?php

/**
 * Message extractor tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Translation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Tests\TemporaryDirectory;
use Blush\Translation\ExtractedMessage;
use Blush\Translation\MessageExtractor;
use Blush\Translation\SourceCatalog;

#[CoversClass(ExtractedMessage::class)]
#[CoversClass(MessageExtractor::class)]
#[CoversClass(SourceCatalog::class)]
final class MessageExtractorTest extends TestCase
{
	use TemporaryDirectory;

	private function extension(): string
	{
		$this->writeTemporaryFile('acme/views/single.php', <<<'PHP'
			<?php $template->layout('base') ?>
			<p><?= e($template->t('entry.by', 'By {author}', author: $name)) ?></p>
			<p><?= e($template->t('comments', '{count, plural, one {# comment} other {# comments}}', count: $n, context: 'Under a post')) ?></p>
			<p><?= e($template->t(key: 'named.key', text: "Named \"text\"")) ?></p>
			<p><?= e($template->t('keyed.only', minutes: 5)) ?></p>
			<p><?= e($template->t("status.{$status}", 'A status')) ?></p>
			<p><?= e($template->t('broken', '{count, plural, one {#}')) ?></p>
			PHP);
		$this->writeTemporaryFile('acme/src/Provider.php', "<?php\n\$x = \$this?->t('entry.by', 'Written by {author}');\n\$y = \$this->t('tools.export', 'Export');\n");
		$this->writeTemporaryFile('acme/vendor/lib/thing.php', "<?php\n\$this->t('vendor.key', 'Never read');\n");
		$this->writeTemporaryFile('acme/views/page.blade.php', "{{ \$template->t('blade.key', 'Not PHP') }}");

		return $this->temporaryDirectory() . '/acme';
	}

	public function testCollectsCallsWithTextAndReportsWhatCantBe(): void
	{
		[$messages, $problems] = new MessageExtractor()->extract($this->extension());

		$this->assertEqualsCanonicalizing(['entry.by', 'comments', 'named.key', 'tools.export'], array_keys($messages));
		$this->assertSame('By {author}', $messages['entry.by']->text);
		$this->assertSame('Under a post', $messages['comments']->context);
		$this->assertSame('Named "text"', $messages['named.key']->text);
		$this->assertSame('views/single.php:2', $messages['entry.by']->where());

		$found = array_map(static fn (Violation $problem): string => "{$problem->field} {$problem->message}", $problems);

		$this->assertCount(3, $found);
		$this->assertContainsEquals('views/single.php:6 Its key isn\'t a plain string, so it can\'t be collected; write it out, or choose with an ICU select.', $found);
		$this->assertStringContainsString('"broken" isn\'t a valid ICU message', implode("\n", $found));
		$this->assertStringContainsString('"entry.by" has other text at views/single.php:2', implode("\n", $found));
		$this->assertTrue(array_all($problems, static fn (Violation $problem): bool => $problem->severity === Severity::Warning));
	}

	public function testTheSourceCatalogIsWrittenAndCompared(): void
	{
		$root                  = $this->extension();
		[$messages]            = new MessageExtractor()->extract($root);
		$catalog               = new SourceCatalog();
		$file                  = SourceCatalog::path("{$root}/lang");

		$this->assertSame(Severity::Notice, $catalog->compare($messages, $file)[0]->severity ?? null, 'No catalog yet.');

		$this->writeTemporaryFile('acme/lang/en.json', "{\n\t\"@@locale\": \"en\",\n\t\"@@domain\": \"acme/hello\",\n\t\"keyed\": {\n\t\t\"only\": \"{minutes} min\"\n\t},\n\t\"entry\": {\n\t\t\"by\": \"Old text\"\n\t},\n\t\"empty\": {}\n}\n");

		$this->assertCount(4, $catalog->compare($messages, $file), 'Three missing and one changed.');

		$result = $catalog->write($messages, $file, 'acme/hello');

		$this->assertEqualsCanonicalizing(['comments', 'named.key', 'tools.export'], $result['added']);
		$this->assertSame(['entry.by'], $result['changed']);
		$this->assertSame([], $catalog->compare($messages, $file));

		$written = (string) file_get_contents($file);

		$this->assertStringContainsString("\t\"keyed\": {\n\t\t\"only\": \"{minutes} min\"\n\t}", $written, 'What\'s there is kept, in tabs.');
		$this->assertStringContainsString('"empty": {}', $written, 'An empty group stays an object.');
		$this->assertStringContainsString('"@comments": {', $written);
		$this->assertStringContainsString('"context": "Under a post"', $written);
		$this->assertSame(['added' => [], 'changed' => [], 'conflicts' => []], $catalog->write($messages, $file, 'acme/hello'), 'Nothing new the second time.');
	}
}
