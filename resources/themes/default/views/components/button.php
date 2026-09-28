<?php

/**
 * Button component (`View\Component\Button`): a link styled as a button,
 * with an optional icon before or after its text, or only an icon (named
 * by the label).
 *
 *     ::button[Get started]{url=/start icon=arrow-right iconPosition=end}
 *     ::button[Share]{url=/share icon=share-2 iconOnly variant=secondary}
 *
 * @var Blush\View\Template                       $template
 * @var Blush\View\Component\Button               $component
 * @var string                                    $url
 * @var string                                    $label
 * @var Blush\View\Component\ButtonVariant        $variant
 * @var string                                    $icon
 * @var Blush\View\Component\IconPosition         $iconPosition
 * @var bool                                      $hasIcon
 * @var array<string, mixed>                      $props
 */

declare(strict_types=1);

use Blush\View\Component\IconPosition;

$iconOnly = $component->isIconOnly();
$class    = implode(' ', array_filter([
	'component-button',
	"component-button--{$variant->value}",
	$iconOnly ? 'component-button--icon-only' : '',
	is_string($props['class'] ?? null) ? $props['class'] : ''
]));

?>
<a class="<?= attr($class) ?>" href="<?= url($url) ?>"<?php if ($iconOnly) : ?> aria-label="<?= attr($label) ?>" title="<?= attr($label) ?>"<?php endif ?>><?php if ($hasIcon && ($iconOnly || $iconPosition === IconPosition::Start)) : ?><?= $template->icon($icon) ?><?php endif ?><?php if (! $iconOnly) : ?><span class="component-button__text"><?= e($label) ?></span><?php endif ?><?php if ($hasIcon && ! $iconOnly && $iconPosition === IconPosition::End) : ?><?= $template->icon($icon) ?><?php endif ?></a>
