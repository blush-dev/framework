<?php

/**
 * Sample output directive (`Directive\Inline\Samp`): what a program prints.
 *
 *     It prints :samp[File not found.]
 *
 * @var Blush\View\Template          $template
 * @var Blush\Directive\Inline\Samp  $directive
 */

declare(strict_types=1);

?>
<samp <?= $directive->attributes() ?>><?= raw($directive->text()) ?></samp>
