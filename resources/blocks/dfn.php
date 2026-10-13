<?php

/**
 * Definition block (`Block\Inline\Dfn`): the term a sentence defines, with
 * `title` when the text isn't the term as written.
 *
 *     :dfn[Blush] is a flat-file CMS.
 *
 * @var Blush\Block\Inline\Dfn $block
 */

declare(strict_types=1);

?>
<dfn <?= $block->attributes() ?>><?= raw($block->text()) ?></dfn>
