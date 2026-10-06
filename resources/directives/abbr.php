<?php

/**
 * Abbreviation directive (`Directive\Inline\Abbr`): the label, with its
 * expansion as `title`.
 *
 *     :abbr[CMS]{title="content management system"}
 *
 * @var Blush\View\Template          $template
 * @var Blush\Directive\Inline\Abbr  $directive
 */

declare(strict_types=1);

?>
<abbr <?= $directive->attributes() ?>><?= raw($directive->text()) ?></abbr>
