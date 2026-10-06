<?php

/**
 * Definition directive (`Directive\Inline\Dfn`): the term a sentence defines, with
 * `title` when the text isn't the term as written.
 *
 *     :dfn[Blush] is a flat-file CMS.
 *
 * @var Blush\View\Template         $template
 * @var Blush\Directive\Inline\Dfn  $directive
 */

declare(strict_types=1);

?>
<dfn <?= $directive->attributes() ?>><?= raw($directive->text()) ?></dfn>
