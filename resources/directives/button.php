<?php

/**
 * Button directive (`Directive\Button`): a link styled as a button,
 * with an optional icon before or after its text, or only an icon (named
 * by the label).
 *
 *     ::button[Get started]{url=/start icon=arrow-right iconPosition=end}
 *     ::button[Share]{url=/share icon=share-2 iconOnly variant=secondary}
 *
 * @var Blush\View\Template     $template
 * @var Blush\Directive\Button  $directive
 */

declare(strict_types=1);

?>
<a <?= $directive->attributes() ?> href="<?= url($directive->url) ?>"><?php if ($directive->showsIconBefore()) : ?><?= $template->icon($directive->icon) ?><?php endif ?><?php if ($directive->showsText()) : ?><span class="directive-button__text"><?= raw($directive->text()) ?></span><?php endif ?><?php if ($directive->showsIconAfter()) : ?><?= $template->icon($directive->icon) ?><?php endif ?></a>
