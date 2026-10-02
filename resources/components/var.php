<?php

/**
 * Variable component (`Component\Inline\Variable`): a variable in math or code.
 *
 *     Solve for :var[x].
 *
 * @var Blush\View\Template              $template
 * @var Blush\Component\Inline\Variable  $component
 */

declare(strict_types=1);

?>
<var <?= $component->attributes() ?>><?= raw($component->text()) ?></var>
