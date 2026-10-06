<?php

/**
 * Meter directive (`Directive\Meter`): a `<meter>` gauge named by
 * its label (the `<label>` around it), with its value as text.
 *
 *     ::meter[Battery]{value=62 low=20 high=80 optimum=100}
 *
 * @var Blush\View\Template    $template
 * @var Blush\Directive\Meter  $directive
 */

declare(strict_types=1);

?>
<label <?= $directive->attributes() ?>>
	<?php if ($directive->label !== '') : ?>
		<span class="directive-meter__label"><?= e($directive->label) ?></span>
	<?php endif ?>
	<meter <?= $directive->gaugeAttributes() ?>><?= e($directive->text()) ?></meter>
	<span class="directive-meter__value"><?= e($directive->text()) ?></span>
</label>
