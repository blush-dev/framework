<?php

/**
 * Sample output component (`Component\Inline\Samp`): what a program prints.
 *
 *     It prints :samp[File not found.]
 *
 * @var Blush\View\Template          $template
 * @var Blush\Component\Inline\Samp  $component
 */

declare(strict_types=1);

?>
<samp <?= $component->attributes() ?>><?= raw($component->text()) ?></samp>
