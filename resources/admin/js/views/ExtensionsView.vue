<script setup lang="ts">
/**
 * The site's extensions (the design direction's Addons, D-308): every
 * installed one, on or off, with what each adds: content types (linked
 * to their screens), components, icons, admin actions, and commands.
 *
 * Read-only: extensions are installed in `user/extensions` or with
 * Composer and turned off in `config/extensions.php` (D-039), so a
 * notice says where, and an extension that's off says how to turn it
 * back on.
 */

import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import { ApiError, request, type ExtensionSummary } from '../api';
import { plural } from '../format';

const extensions = ref<ExtensionSummary[] | null>(null);
const error      = ref('');

request<{ extensions: ExtensionSummary[] }>('GET', '/extensions').then((answer) => {
	extensions.value = answer.extensions;
}).catch((caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The extensions couldn\'t be loaded.';
});

const on = computed(() => (extensions.value ?? []).filter((extension) => extension.enabled).length);

// What an extension adds, as labeled groups, leaving out the empty ones.
function groups(extension: ExtensionSummary): { label: string; items: string[] }[] {
	const { components, icons, actions, commands } = extension.adds;

	return [
		{ label: 'Components', items: components },
		{ label: 'Icons', items: icons.map((namespace) => `${namespace}/…`) },
		{ label: 'Dashboard actions', items: actions },
		{ label: 'Commands', items: commands }
	].filter((group) => group.items.length > 0);
}

function adds(extension: ExtensionSummary): boolean {
	return extension.adds.types.length > 0 || groups(extension).length > 0;
}

function requirements(extension: ExtensionSummary): string {
	return Object.entries(extension.requires).map(([name, constraint]) => `${name} ${constraint}`).join(', ');
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Extensions</h1>
			<p class="page-header__hint">Code that adds content types, components, icons, and actions to the site.</p>
		</div>
	</header>

	<p class="notice"><span>Install extensions in <code>user/extensions</code> or with Composer. Every installed extension is on unless <code>config/extensions.php</code> turns it off. What one adds appears in the screens it belongs to.</span></p>
	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<section v-if="!error" class="panel" aria-labelledby="extensions-heading" :aria-busy="extensions === null">
		<header class="panel__header">
			<h2 id="extensions-heading">Installed</h2>
			<p v-if="extensions?.length" class="panel__hint">{{ plural(extensions.length, 'extension') }}, {{ on }} on</p>
		</header>

		<div v-if="extensions === null" class="panel__body" aria-hidden="true">
			<span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" />
		</div>

		<div v-else-if="extensions.length === 0" class="empty">
			<AdminIcon name="plug" />
			<h3 class="empty__heading">No Extensions Yet</h3>
			<p class="empty__text">Put one in <code>user/extensions</code>, or install one with Composer (package type <code>blush-extension</code>).</p>
		</div>

		<ul v-else class="packages">
			<li v-for="extension in extensions" :key="extension.name" class="package" :class="{ 'package--off': !extension.enabled }">
				<span class="package__mark"><AdminIcon name="plug" /></span>
				<div class="package__main">
					<p class="package__title">
						<span class="package__name">{{ extension.name }}</span>
						<span class="package__fact mono">{{ extension.version }}</span>
						<span class="package__fact" :class="{ mono: extension.source === 'local' }">{{ extension.source === 'local' ? extension.path : 'Composer' }}</span>
					</p>
					<p v-if="extension.description" class="package__description">{{ extension.description }}</p>
					<dl v-if="extension.enabled && adds(extension)" class="adds">
						<div v-if="extension.adds.types.length">
							<dt>Content types</dt>
							<dd>
								<ul class="adds__chips">
									<li v-for="type in extension.adds.types" :key="type.name">
										<RouterLink :to="{ name: 'content-type', params: { name: type.name } }">{{ type.label }}</RouterLink>
										<span v-if="type.overridden" class="adds__note">redefined in <code>config/content.php</code></span>
									</li>
								</ul>
							</dd>
						</div>
						<div v-for="group in groups(extension)" :key="group.label">
							<dt>{{ group.label }}</dt>
							<dd>
								<ul class="adds__chips">
									<li v-for="item in group.items" :key="item"><span class="mono">{{ item }}</span></li>
								</ul>
							</dd>
						</div>
					</dl>
					<p v-else-if="extension.enabled" class="package__description">Nothing the admin lists; it may add routes, fields, or code that runs.</p>
					<p v-else class="package__description">Off in <code>config/extensions.php</code>. Remove it from <code>disabled</code> (or add it to <code>enabled</code>) to turn it on.</p>
					<p v-if="requirements(extension)" class="package__fact">Requires <span class="mono">{{ requirements(extension) }}</span></p>
				</div>
				<div class="package__end">
					<span class="pill" :class="{ 'pill--good': extension.enabled }">{{ extension.enabled ? 'On' : 'Off' }}</span>
				</div>
			</li>
		</ul>
	</section>
</template>

<style scoped>
/* Widths as classes: the admin's CSP blocks inline style attributes. */
.skeleton--heading {
	width: 40%;
}

.adds {
	display: grid;
	gap: var(--s-2);
	margin: 0;
}

.adds > div {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	gap: 6px var(--s-3);
}

.adds dt {
	min-width: 9em;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.adds dd {
	flex: 1;
	min-width: 0;
	margin: 0;
}

.adds__chips {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.adds__chips > li {
	display: inline-flex;
	align-items: baseline;
	gap: 6px;
	padding: 2px 10px;
	border: 1px solid var(--border);
	border-radius: 99px;
	background: var(--surface-2);
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.adds__chips a {
	color: inherit;
	text-decoration: none;
}

.adds__chips a:hover {
	color: var(--fg);
	text-decoration: underline;
}

.adds__note {
	color: var(--fg-3);
	font-size: var(--text-xs);
}
</style>
