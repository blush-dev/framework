<?php

/**
 * Meter component (`Component\Meter`): a `<meter>` gauge named by
 * its label (the `<label>` around it), with its value as text.
 *
 *     ::meter[Battery]{value=62 low=20 high=80 optimum=100}
 *
 * @var Blush\View\Template    $template
 * @var Blush\Component\Meter  $component
 */

declare(strict_types=1);

?>
<label <?= $component->attributes() ?>>
	<?php if ($component->label !== '') : ?>
		<span class="component-meter__label"><?= e($component->label) ?></span>
	<?php endif ?>
	<meter <?= $component->gaugeAttributes() ?>><?= e($component->text()) ?></meter>
	<span class="component-meter__value"><?= e($component->text()) ?></span>
</label>
