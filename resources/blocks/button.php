<?php

/**
 * Button block (`Block\Button`): a link styled as a button,
 * with an optional icon before or after its text, or only an icon (named
 * by the label).
 *
 *     ::button[Get started]{url=/start icon=arrow-right iconPosition=end}
 *     ::button[Share]{url=/share icon=share-2 iconOnly variant=secondary}
 *
 * @var Blush\View\Template     $template
 * @var Blush\Block\Button  $block
 */

declare(strict_types=1);

?>
<a <?= $block->attributes() ?> href="<?= url($block->url) ?>"><?php if ($block->showsIconBefore()) : ?><?= $template->icon($block->icon) ?><?php endif ?><?php if ($block->showsText()) : ?><span class="<?= attr($block->elementClass('text')) ?>"><?= raw($block->text()) ?></span><?php endif ?><?php if ($block->showsIconAfter()) : ?><?= $template->icon($block->icon) ?><?php endif ?></a>
