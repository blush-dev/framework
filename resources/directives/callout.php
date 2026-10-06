<?php

/**
 * Callout directive (`Directive\Callout`): a note set apart from the
 * text, with an optional title (the label). Its variants are `info`,
 * `tip`, `warning`, and `danger`; Default is a plain note.
 *
 *     :::callout[Heads up]{variant=warning}
 *     Back up your site first.
 *     :::
 *
 * @var Blush\View\Template      $template
 * @var Blush\Directive\Callout  $directive
 */

declare(strict_types=1);

?>
<aside <?= $directive->attributes() ?>>
	<?php if ($directive->heading() !== '') : ?>
		<p class="directive-callout__title"><?= raw($directive->heading()) ?></p>
	<?php endif ?>
	<?= raw($directive->content()) ?>
</aside>
