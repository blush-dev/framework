<?php

/**
 * Progress component (`Component\Progress`): a `<progress>` bar
 * named by its label (the `<label>` around it), with its value as text.
 * Without a value, the bar is indeterminate.
 *
 *     ::progress[Reading challenge]{value=12 max=50}
 *
 * @var Blush\View\Template            $template
 * @var Blush\Component\Progress $component
 * @var ?string                         $valueAttribute
 * @var string                          $maxAttribute
 * @var string                          $percent
 * @var string                          $valueText
 * @var string                          $maxText
 * @var string                          $label
 */

declare(strict_types=1);

$text = match (true) {
	$valueAttribute === null => '',
	$component->isPercent()  => $percent,
	default                  => $template->t('measure.value', value: $valueText, max: $maxText)
};

?>
<label class="component-progress">
	<?php if ($label !== '') : ?>
		<span class="component-progress__label"><?= e($label) ?></span>
	<?php endif ?>
	<progress class="component-progress__bar" max="<?= attr($maxAttribute) ?>"<?php if ($valueAttribute !== null) : ?> value="<?= attr($valueAttribute) ?>"<?php endif ?><?php if ($label === '') : ?> aria-label="<?= attr($template->t('progress.label')) ?>"<?php endif ?>><?= e($text) ?></progress>
	<?php if ($text !== '') : ?>
		<span class="component-progress__value"><?= e($text) ?></span>
	<?php endif ?>
</label>
