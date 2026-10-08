<?php

/**
 * The welcome page's content: the next steps, and, outside production,
 * any setup problems (`Blush\Setup\Welcome`). A theme's `welcome` view
 * can include it inside its own markup.
 *
 * @var Blush\View\Template            $template
 * @var Blush\View\Site                $site
 * @var Blush\Content\Http\ContentPage $page
 */

declare(strict_types=1);

$welcome = $page->welcome;

$code = static fn(string $text): Blush\View\SafeHtml => raw('<code>' . e($text) . '</code>');
$link = $welcome?->admin === null ? '' : raw('<a href="' . url($welcome->admin) . '">' . e($welcome->admin) . '</a>');

?>
<p><?= e($template->t('welcome.running', generator: $site->generator)) ?></p>

<?php if ($welcome !== null) : ?>
	<h2><?= e($template->t('welcome.next')) ?></h2>

	<ol class="welcome-steps">
		<li><?= e($template->t('welcome.start', path: $code($welcome->homepage))) ?></li>

		<?php if ($welcome->admin === null) : ?>
			<li><?= e($template->t('welcome.admin.off', path: $code('config/admin.php'))) ?></li>
		<?php elseif (! $welcome->accounts) : ?>
			<li><?= e($template->t('welcome.admin.account', command: $code("{$welcome->binary} account:add"), admin: $link)) ?></li>
		<?php else : ?>
			<li><?= e($template->t('welcome.admin.sign_in', admin: $link)) ?></li>
		<?php endif ?>
	</ol>

	<?php if ($welcome->problems !== []) : ?>
		<aside class="directive-callout directive-callout--warning welcome-problems">
			<p class="directive-callout__title"><?= e($template->t('welcome.problems.title')) ?></p>
			<ul>
				<?php foreach ($welcome->problems as $problem) : ?>
					<li><?= $code($problem->label) ?>: <?= e(trim("{$problem->message} {$problem->hint}")) ?></li>
				<?php endforeach ?>
			</ul>
			<p><?= e($template->t('welcome.problems.doctor', command: $code("{$welcome->binary} doctor"))) ?></p>
		</aside>
	<?php endif ?>
<?php else : ?>
	<p><?= e($template->t('welcome.start', path: $code('user/content/index.md'))) ?></p>
<?php endif ?>
