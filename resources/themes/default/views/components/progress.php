<?php

/**
 * Progress component (`Component\Progress`): a `<progress>` bar
 * named by its label (the `<label>` around it), with its value as text.
 * Without a value, the bar is indeterminate.
 *
 *     ::progress[Reading challenge]{value=12 max=50}
 *
 * @var Blush\View\Template       $template
 * @var Blush\Component\Progress  $component
 */

declare(strict_types=1);

?>
<label <?= $component->attributes() ?>>
	<?php if ($component->label !== '') : ?>
		<span class="component-progress__label"><?= e($component->label) ?></span>
	<?php endif ?>
	<progress <?= $component->barAttributes() ?>><?= e($component->text()) ?></progress>
	<?php if ($component->text() !== '') : ?>
		<span class="component-progress__value"><?= e($component->text()) ?></span>
	<?php endif ?>
</label>
