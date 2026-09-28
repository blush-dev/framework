<?php

/**
 * Meter component (`Component\Meter`): a `<meter>` gauge named by
 * its label (the `<label>` around it), with its value as text.
 *
 *     ::meter[Battery]{value=62 low=20 high=80 optimum=100}
 *
 * @var Blush\View\Template         $template
 * @var Blush\Component\Meter $component
 * @var string                       $valueAttribute
 * @var string                       $minAttribute
 * @var string                       $maxAttribute
 * @var ?string                      $lowAttribute
 * @var ?string                      $highAttribute
 * @var ?string                      $optimumAttribute
 * @var string                       $percent
 * @var string                       $valueText
 * @var string                       $maxText
 * @var string                       $label
 */

declare(strict_types=1);

$text  = $component->isPercent() ? $percent : $template->t('measure.value', value: $valueText, max: $maxText);
$range = ['low' => $lowAttribute, 'high' => $highAttribute, 'optimum' => $optimumAttribute];

?>
<label class="component-meter">
	<?php if ($label !== '') : ?>
		<span class="component-meter__label"><?= e($label) ?></span>
	<?php endif ?>
	<meter class="component-meter__gauge" value="<?= attr($valueAttribute) ?>" min="<?= attr($minAttribute) ?>" max="<?= attr($maxAttribute) ?>"<?php foreach ($range as $attribute => $number) : ?><?php if ($number !== null) : ?> <?= e($attribute) ?>="<?= attr($number) ?>"<?php endif ?><?php endforeach ?><?php if ($label === '') : ?> aria-label="<?= attr($template->t('meter.label')) ?>"<?php endif ?>><?= e($text) ?></meter>
	<span class="component-meter__value"><?= e($text) ?></span>
</label>
