<?php

/**
 * Citation component (`Component\Inline\Cite`): the title of a work.
 *
 *     From :cite[The Hobbit].
 *
 * @var Blush\View\Template          $template
 * @var Blush\Component\Inline\Cite  $component
 */

declare(strict_types=1);

?>
<cite <?= $component->attributes() ?>><?= raw($component->text()) ?></cite>
