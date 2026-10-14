<?php

/**
 * The welcome page's content: the next steps, and, outside production,
 * any setup problems (`Blush\Setup\Welcome`). A theme's `welcome` view
 * can include it inside its own markup.
 *
 * @var Blush\View\Site                $site
 * @var Blush\Translation\Messages     $messages
 * @var Blush\Content\Http\ContentPage $page
 */

declare(strict_types=1);

use Blush\Support\Bem;

$bem = new Bem('block-callout');

$welcome = $page->welcome;

$code = static fn(string $text): Blush\View\SafeHtml => raw('<code>' . e($text) . '</code>');
$link = $welcome?->admin === null ? '' : raw('<a href="' . url($welcome->admin) . '">' . e($welcome->admin) . '</a>');

?>
<p><?= e($messages->t('welcome.running', generator: $site->generator)) ?></p>

<?php if ($welcome !== null) : ?>
	<h2><?= e($messages->t('welcome.next')) ?></h2>

	<ol class="welcome-steps">
		<?php if ($welcome->homepage === null) : ?>
			<li><?= e($messages->t('welcome.start_admin')) ?></li>
		<?php else : ?>
			<li><?= e($messages->t('welcome.start', path: $code($welcome->homepage))) ?></li>
		<?php endif ?>

		<?php if ($welcome->admin === null) : ?>
			<li><?= e($messages->t('welcome.admin.off', path: $code('config/admin.php'))) ?></li>
		<?php elseif (! $welcome->accounts) : ?>
			<li><?= e($messages->t('welcome.admin.account', command: $code("{$welcome->binary} account:add"), admin: $link)) ?></li>
		<?php else : ?>
			<li><?= e($messages->t('welcome.admin.sign_in', admin: $link)) ?></li>
		<?php endif ?>
	</ol>

	<?php if ($welcome->problems !== []) : ?>
		<aside class="<?= attr($bem->modifier('warning')->with('welcome-problems')) ?>">
			<p class="<?= attr($bem->element('title')) ?>"><?= e($messages->t('welcome.problems.title')) ?></p>
			<ul>
				<?php foreach ($welcome->problems as $problem) : ?>
					<li><?= $code((string) $problem->label) ?>: <?= e(trim("{$problem->message} {$problem->hint}")) ?></li>
				<?php endforeach ?>
			</ul>
			<p><?= e($messages->t('welcome.problems.doctor', command: $code("{$welcome->binary} doctor"))) ?></p>
		</aside>
	<?php endif ?>
<?php else : ?>
	<p><?= e($messages->t('welcome.start', path: $code('user/content/index.md'))) ?></p>
<?php endif ?>
