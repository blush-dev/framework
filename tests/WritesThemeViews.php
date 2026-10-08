<?php

/**
 * Writes views into a scratch site's theme.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests;

/**
 * Writes views into `test/site`, a child of the default theme that this
 * makes the active one. The site has no views of its own (D-617), so a
 * test changing a template does it as a child theme would.
 */
trait WritesThemeViews
{
	use TemporaryDirectory;

	/**
	 * Writes a view, such as `single.php` or `directives/blush-callout.php`,
	 * into the test theme, and returns its path.
	 */
	private function themeView(string $view, string $contents): string
	{
		$this->writeTemporaryFile('extensions/test/site/theme.json', '{"name": "test/site", "label": "Test Site", "namespace": "site", "parent": "blush/default"}');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'test/site');\n");

		return $this->writeTemporaryFile("extensions/test/site/views/{$view}", $contents);
	}
}
