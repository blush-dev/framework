<?php

/**
 * Button component (`Component\Button`): a link styled as a button,
 * with an optional icon before or after its text, or only an icon (named
 * by the label).
 *
 *     ::button[Get started]{url=/start icon=arrow-right iconPosition=end}
 *     ::button[Share]{url=/share icon=share-2 iconOnly variant=secondary}
 *
 * @var Blush\View\Template     $template
 * @var Blush\Component\Button  $component
 */

declare(strict_types=1);

?>
<a <?= $component->attributes() ?> href="<?= url($component->url) ?>"><?php if ($component->showsIconBefore()) : ?><?= $template->icon($component->icon) ?><?php endif ?><?php if ($component->showsText()) : ?><span class="component-button__text"><?= raw($component->text()) ?></span><?php endif ?><?php if ($component->showsIconAfter()) : ?><?= $template->icon($component->icon) ?><?php endif ?></a>
