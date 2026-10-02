<script setup lang="ts">
/**
 * The site's icon packs (D-378): SVG icons in a namespace of their own,
 * one kind of extension, with no code. Every installed pack is on, so
 * each row shows what it is, how many icons it has, and the first of
 * their names; broken packs follow, with the reason.
 *
 * Read-only: packs are installed in `user/icons` or with Composer.
 * Installing from the admin is planned (packs are data, so they're the
 * first kind it will take); its button is a placeholder.
 */

import { ref } from 'vue';
import AdminIcon from '../components/AdminIcon.vue';
import { ApiError, request, type IconPacks } from '../api';
import { plural } from '../format';

const packs = ref<IconPacks | null>(null);
const error = ref('');

request<IconPacks>('GET', '/icon-packs').then((answer) => {
	packs.value = answer;
}).catch((caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The icon packs couldn\'t be loaded.';
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Icon Packs</h1>
			<p class="page-header__hint">Sets of icons for content, menus, and buttons, each in a namespace of its own.</p>
		</div>
		<div class="page-header__actions">
			<button type="button" class="button" disabled aria-describedby="install-note"><AdminIcon name="upload" />Install Icon Pack</button>
		</div>
	</header>

	<p id="install-note" class="notice"><span>Installing from here is coming. For now, put icon packs in <code>user/icons</code> or install them with Composer. Every installed pack is on, and its icons appear in the icon inserter.</span></p>
	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="!error">
		<section class="panel" aria-labelledby="packs-heading" :aria-busy="packs === null">
			<header class="panel__header">
				<h2 id="packs-heading">Installed</h2>
				<p v-if="packs?.packs.length" class="panel__hint">{{ plural(packs.packs.length, 'icon pack') }}</p>
			</header>

			<div v-if="packs === null" class="panel__body" aria-hidden="true">
				<span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" />
			</div>

			<div v-else-if="packs.packs.length === 0" class="empty">
				<AdminIcon name="shapes" />
				<h3 class="empty__heading">No Icon Packs Yet</h3>
				<p class="empty__text">Put one in <code>user/icons</code>, with an <code>icons.json</code>, or install one with Composer (package type <code>blush-icons</code>).</p>
			</div>

			<ul v-else class="packages">
				<li v-for="pack in packs.packs" :key="pack.name" class="package">
					<span class="package__mark"><AdminIcon name="shapes" /></span>
					<div class="package__main">
						<p class="package__title">
							<span class="package__name">{{ pack.label }}</span>
							<span class="package__fact mono">{{ pack.name }}</span>
							<span v-if="pack.version" class="package__fact mono">{{ pack.version }}</span>
							<span class="package__fact" :class="{ mono: pack.source === 'local' }">{{ pack.source === 'local' ? pack.path : 'Composer' }}</span>
						</p>
						<p v-if="pack.description" class="package__description">{{ pack.description }}</p>
						<p class="package__description">{{ plural(pack.count, 'icon') }} in <span class="mono">{{ pack.namespace }}/…</span></p>
						<ul v-if="pack.icons.length" class="pack__icons">
							<li v-for="icon in pack.icons" :key="icon"><span class="mono">{{ icon }}</span></li>
							<li v-if="pack.count > pack.icons.length" class="pack__more">and {{ pack.count - pack.icons.length }} more</li>
						</ul>
					</div>
					<div class="package__end">
						<span class="pill pill--good">On</span>
					</div>
				</li>
			</ul>
		</section>

		<section v-if="packs?.invalid.length" class="panel" aria-labelledby="invalid-heading">
			<header class="panel__header">
				<h2 id="invalid-heading">Can't Be Used</h2>
				<p class="panel__hint">Installed, with a broken manifest</p>
			</header>
			<ul class="packages">
				<li v-for="pack in packs.invalid" :key="pack.where" class="package">
					<span class="package__mark"><AdminIcon name="triangle-alert" /></span>
					<div class="package__main">
						<p class="package__title"><span class="package__name mono">{{ pack.where }}</span></p>
						<p class="package__description">{{ pack.reason }}</p>
					</div>
				</li>
			</ul>
		</section>
	</template>
</template>

<style scoped>
/* Widths as classes: the admin's CSP blocks inline style attributes. */
.skeleton--heading {
	width: 40%;
}

.panel + .panel {
	margin-top: var(--s-4);
}

.pack__icons {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.pack__icons > li {
	padding: 2px 10px;
	border: 1px solid var(--border);
	border-radius: 99px;
	background: var(--surface-2);
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.pack__icons > .pack__more {
	border-color: transparent;
	background: none;
	color: var(--fg-3);
}
</style>
