<?php

/**
 * Variable directive (`Directive\Inline\Variable`): a variable in math or code.
 *
 *     Solve for :var[x].
 *
 * @var Blush\View\Template              $template
 * @var Blush\Directive\Inline\Variable  $directive
 */

declare(strict_types=1);

?>
<var <?= $directive->attributes() ?>><?= raw($directive->text()) ?></var>
