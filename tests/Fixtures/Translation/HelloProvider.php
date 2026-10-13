<?php

/**
 * Hello provider fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Translation;

use Override;
use Blush\Core\ServiceProvider;
use Blush\View\EntryTemplate;
use Blush\View\EntryTemplateRegistry;

/**
 * Declares a template with text to translate, as a plugin's or theme's
 * provider would.
 */
final class HelloProvider extends ServiceProvider
{
	#[Override]
	public function boot(): void
	{
		$this->container->make(EntryTemplateRegistry::class)->register(new EntryTemplate(
			'single',
			label: $this->messages->t('templates.hello.label', 'Hello Template'),
			description: $this->messages->t('templates.hello.description', 'For {count, plural, one {# type} other {# types}}.', count: 2)
		));
	}
}
