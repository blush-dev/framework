<?php

/**
 * Table of contents component (`Component\Toc`): the entry's
 * headings, nested by level, each linking to its heading. The label is
 * shown as a title and names the navigation.
 *
 *     ::toc[On this page]{max=4}
 *
 * @var Blush\View\Template                                                  $template
 * @var list<array{text: string, id: string, children: list<mixed>}> $items
 * @var string                                                               $slot
 * @var array<string, mixed>                                                 $props
 */

declare(strict_types=1);

$title = is_string($props['label'] ?? null) ? $props['label'] : '';

/**
 * @param list<array{text: string, id: string, children: list<mixed>}> $items
 */
$list = static function (array $items) use (&$list): string {
	$html = '<ol class="component-toc__list">';

	foreach ($items as $item) {
		$html .= '<li class="component-toc__item"><a class="component-toc__link" href="#' . attr($item['id']) . '">' . e($item['text']) . '</a>';
		$html .= $item['children'] === [] ? '' : $list($item['children']);
		$html .= '</li>';
	}

	return $html . '</ol>';
};

?>
<nav class="component-toc" aria-label="<?= attr($title !== '' ? $title : $template->t('toc.label')) ?>">
	<?php if ($title !== '') : ?>
		<p class="component-toc__title"><?= e($title) ?></p>
	<?php endif ?>
	<?= $list($items) ?>
</nav>
