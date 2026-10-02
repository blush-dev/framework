<script setup lang="ts">
/**
 * The site's plugins (the design direction's Addons, D-308; one kind of
 * extension, D-378): every installed one, on or off, with what each
 * adds: content types (linked to their screens), components, icons,
 * admin actions, and commands.
 *
 * Read-only: plugins are installed in `user/plugins` or with Composer
 * and turned off in `config/plugins.php` (D-039), so a notice says
 * where, and a plugin that's off says how to turn it back on.
 * Installing from the admin is planned (D-378); its button is a
 * placeholder.
 */

import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import { ApiError, request, type PluginSummary } from '../api';
import { plural } from '../format';

const plugins = ref<PluginSummary[] | null>(null);
const error   = ref('');

request<{ plugins: PluginSummary[] }>('GET', '/plugins').then((answer) => {
	plugins.value = answer.plugins;
}).catch((caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The plugins couldn\'t be loaded.';
});

const on = computed(() => (plugins.value ?? []).filter((plugin) => plugin.enabled).length);

// What a plugin adds, as labeled groups, leaving out the empty ones.
function groups(plugin: PluginSummary): { label: string; items: string[] }[] {
	const { components, icons, actions, commands } = plugin.adds;

	return [
		{ label: 'Components', items: components },
		{ label: 'Icons', items: icons.map((namespace) => `${namespace}/…`) },
		{ label: 'Dashboard actions', items: actions },
		{ label: 'Commands', items: commands }
	].filter((group) => group.items.length > 0);
}

function adds(plugin: PluginSummary): boolean {
	return plugin.adds.types.length > 0 || groups(plugin).length > 0;
}

function requirements(plugin: PluginSummary): string {
	return Object.entries(plugin.requires).map(([name, constraint]) => `${name} ${constraint}`).join(', ');
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Plugins</h1>
			<p class="page-header__hint">Code that adds content types, components, icons, and actions to the site.</p>
		</div>
		<div class="page-header__actions">
			<button type="button" class="button" disabled aria-describedby="install-note"><AdminIcon name="upload" />Install Plugin</button>
		</div>
	</header>

	<p id="install-note" class="notice"><span>Installing from here is coming. For now, put plugins in <code>user/plugins</code> or install them with Composer. Every installed plugin is on unless <code>config/plugins.php</code> turns it off. What one adds appears in the screens it belongs to.</span></p>
	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<section v-if="!error" class="panel" aria-labelledby="plugins-heading" :aria-busy="plugins === null">
		<header class="panel__header">
			<h2 id="plugins-heading">Installed</h2>
			<p v-if="plugins?.length" class="panel__hint">{{ plural(plugins.length, 'plugin') }}, {{ on }} on</p>
		</header>

		<div v-if="plugins === null" class="panel__body" aria-hidden="true">
			<span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" />
		</div>

		<div v-else-if="plugins.length === 0" class="empty">
			<AdminIcon name="plug" />
			<h3 class="empty__heading">No Plugins Yet</h3>
			<p class="empty__text">Put one in <code>user/plugins</code>, or install one with Composer (package type <code>blush-plugin</code>).</p>
		</div>

		<ul v-else class="packages">
			<li v-for="plugin in plugins" :key="plugin.name" class="package" :class="{ 'package--off': !plugin.enabled }">
				<span class="package__mark"><AdminIcon name="plug" /></span>
				<div class="package__main">
					<p class="package__title">
						<span class="package__name">{{ plugin.label }}</span>
						<span class="package__fact mono">{{ plugin.name }}</span>
						<span class="package__fact mono">{{ plugin.version }}</span>
						<span class="package__fact" :class="{ mono: plugin.source === 'local' }">{{ plugin.source === 'local' ? plugin.path : 'Composer' }}</span>
					</p>
					<p v-if="plugin.description" class="package__description">{{ plugin.description }}</p>
					<dl v-if="plugin.enabled && adds(plugin)" class="adds">
						<div v-if="plugin.adds.types.length">
							<dt>Content types</dt>
							<dd>
								<ul class="adds__chips">
									<li v-for="type in plugin.adds.types" :key="type.name">
										<RouterLink :to="{ name: 'content-type', params: { name: type.name } }">{{ type.label }}</RouterLink>
										<span v-if="type.overridden" class="adds__note">redefined in <code>config/content.php</code></span>
									</li>
								</ul>
							</dd>
						</div>
						<div v-for="group in groups(plugin)" :key="group.label">
							<dt>{{ group.label }}</dt>
							<dd>
								<ul class="adds__chips">
									<li v-for="item in group.items" :key="item"><span class="mono">{{ item }}</span></li>
								</ul>
							</dd>
						</div>
					</dl>
					<p v-else-if="plugin.enabled" class="package__description">Nothing the admin lists; it may add routes, fields, or code that runs.</p>
					<p v-else class="package__description">Off in <code>config/plugins.php</code>. Remove it from <code>disabled</code> (or add it to <code>enabled</code>) to turn it on.</p>
					<p v-if="requirements(plugin)" class="package__fact">Requires <span class="mono">{{ requirements(plugin) }}</span></p>
				</div>
				<div class="package__end">
					<span class="pill" :class="{ 'pill--good': plugin.enabled }">{{ plugin.enabled ? 'On' : 'Off' }}</span>
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
