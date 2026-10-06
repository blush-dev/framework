<?php

/**
 * Citation directive (`Directive\Inline\Cite`): the title of a work.
 *
 *     From :cite[The Hobbit].
 *
 * @var Blush\View\Template          $template
 * @var Blush\Directive\Inline\Cite  $directive
 */

declare(strict_types=1);

?>
<cite <?= $directive->attributes() ?>><?= raw($directive->text()) ?></cite>
