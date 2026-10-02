<?php

/**
 * Definition component (`Component\Inline\Dfn`): the term a sentence defines, with
 * `title` when the text isn't the term as written.
 *
 *     :dfn[Blush] is a flat-file CMS.
 *
 * @var Blush\View\Template         $template
 * @var Blush\Component\Inline\Dfn  $component
 */

declare(strict_types=1);

?>
<dfn <?= $component->attributes() ?>><?= raw($component->text()) ?></dfn>
