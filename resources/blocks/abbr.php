<?php

/**
 * Abbreviation block (`Block\Inline\Abbr`): the label, with its
 * expansion as `title`.
 *
 *     :abbr[CMS]{title="content management system"}
 *
 * @var Blush\Block\Inline\Abbr $block
 */

declare(strict_types=1);

?>
<abbr <?= $block->attributes() ?>><?= raw($block->text()) ?></abbr>
