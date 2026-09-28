<?php

/**
 * Abbreviation component: the label, with its expansion as `title`.
 *
 *     :abbr[CMS]{title="content management system"}
 *
 * @var Blush\View\Template  $template
 * @var string               $slot
 * @var array<string, mixed> $props
 */

declare(strict_types=1);

$title = is_string($props['title'] ?? null) ? $props['title'] : '';

?>
<abbr class="component-abbr"<?php if ($title !== '') : ?> title="<?= attr($title) ?>"<?php endif ?>><?= raw($slot) ?></abbr>
