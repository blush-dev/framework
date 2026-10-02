<?php

/**
 * Abbreviation component (`Component\Inline\Abbr`): the label, with its
 * expansion as `title`.
 *
 *     :abbr[CMS]{title="content management system"}
 *
 * @var Blush\View\Template          $template
 * @var Blush\Component\Inline\Abbr  $component
 */

declare(strict_types=1);

?>
<abbr <?= $component->attributes() ?>><?= raw($component->text()) ?></abbr>
