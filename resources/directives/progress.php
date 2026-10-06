<?php

/**
 * Progress directive (`Directive\Progress`): a `<progress>` bar
 * named by its label (the `<label>` around it), with its value as text.
 * Without a value, the bar is indeterminate.
 *
 *     ::progress[Reading challenge]{value=12 max=50}
 *
 * @var Blush\View\Template       $template
 * @var Blush\Directive\Progress  $directive
 */

declare(strict_types=1);

?>
<label <?= $directive->attributes() ?>>
	<?php if ($directive->label !== '') : ?>
		<span class="directive-progress__label"><?= e($directive->label) ?></span>
	<?php endif ?>
	<progress <?= $directive->barAttributes() ?>><?= e($directive->text()) ?></progress>
	<?php if ($directive->text() !== '') : ?>
		<span class="directive-progress__value"><?= e($directive->text()) ?></span>
	<?php endif ?>
</label>
