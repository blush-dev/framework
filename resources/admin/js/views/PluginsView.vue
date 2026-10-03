<script setup lang="ts">
/**
 * Plugins (the design direction's Addons, D-308; drawn as the extensions
 * sketch in D-385): every installed plugin as a row, by label, with a
 * switch. Rows, not cards: a plugin has nothing to look at, and the row
 * holds only what its manifest says. What a plugin registers shows on
 * the screens it belongs to, not here.
 *
 * The switch turns a plugin on or off at once (`usePlugins()`), saved in
 * `user/data/settings.json` over `config/plugins.php`, and a toast says
 * so, naming the plugins that started or stopped with it. By default a
 * Composer plugin is on and a local one only when config names it; once
 * a list is saved here, it names every plugin that's on (D-391), so a
 * Composer plugin it leaves out says why it's off. A plugin whose
 * requirements aren't met can't be turned on, and says why. **Delete**
 * removes a folder plugin that's off. A plugin's name, and **Plugin
 * details** in its menu, open its details screen (`PluginView`).
 *
 * Installing from the admin is planned (D-378): **Install Plugin** says
 * how to install one for now.
 */

import { computed, ref } from 'vue';
import AdminIcon from '../components/AdminIcon.vue';
import AdminModal from '../components/AdminModal.vue';
import MenuButton from '../components/MenuButton.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import type { PluginSummary } from '../api';
import { pluginRoute, usePlugins } from '../plugins';
import { copy } from '../themes';
import { can } from '../session';

const { answer, error, busy, plugins, load, toggle, useConfig, remove: removePlugin } = usePlugins();

// What the account may do here (D-389).
const canActivate = can('extensions.plugins.activate');
const canDelete   = can('extensions.plugins.delete');
const installing = ref(false);

void load();

const on = computed(() => plugins.value.filter((plugin) => plugin.running).length);

// Why a plugin that isn't running can't be turned on, or `null`.
function blocked(plugin: PluginSummary): string | null {
	return plugin.running ? null : plugin.blocked;
}

async function remove(plugin: PluginSummary): Promise<void> {
	if (await removePlugin(plugin)) {
		await load();
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Plugins</h1>
			<p class="page-header__hint">Code that adds content types, components, icons, and actions to the site.</p>
		</div>
		<div class="page-header__actions">
			<button type="button" class="button button--primary" @click="installing = true"><AdminIcon name="upload" />Install Plugin</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div class="count-row">
		<span>{{ answer ? `${plugins.length} ${plugins.length === 1 ? 'plugin' : 'plugins'} · ${on} on` : 'Loading plugins' }}</span>
		<span class="count-row__rule" />
	</div>

	<template v-if="answer">
		<div v-if="plugins.length === 0" class="panel">
			<div class="empty">
				<AdminIcon name="plug" />
				<h3 class="empty__heading">No Plugins Yet</h3>
				<p class="empty__text">Put one in <code>user/plugins</code>, or install one with Composer (package type <code>blush-plugin</code>).</p>
			</div>
		</div>

		<ul v-else class="plugins">
			<li v-for="plugin in plugins" :key="plugin.name" class="plugin" :class="{ 'is-off': !plugin.running }">
				<span class="plugin__mark" aria-hidden="true"><AdminIcon name="plug" /></span>
				<div class="plugin__main">
					<p class="plugin__name">
						<RouterLink class="plugin__label" :to="pluginRoute(plugin.name)">{{ plugin.label }}</RouterLink>
						<span v-if="blocked(plugin)" class="pill pill--warn">Can't turn on</span>
						<span class="plugin__package mono">{{ plugin.name }} {{ plugin.version }}</span>
					</p>
					<p v-if="plugin.description" class="plugin__description">{{ plugin.description }}</p>
					<p v-if="plugin.source === 'composer' && !plugin.enabled" class="plugin__description">Installed by Composer. It's off because the list of plugins turned on here doesn't name it.</p>
					<p v-if="blocked(plugin)" class="plugin__message">
						<AdminIcon name="triangle-alert" /><span>{{ blocked(plugin) }}</span>
					</p>
				</div>
				<div class="plugin__end">
					<ToggleSwitch
						:checked="plugin.running"
						:label="plugin.label"
						:locked="blocked(plugin) !== null || !canActivate"
						:busy="busy === plugin.name"
						:reason="blocked(plugin) ?? (canActivate ? null : 'Your role can\'t turn plugins on and off.')"
						@change="toggle(plugin, $event)"
					/>
					<MenuButton button-class="button button--ghost button--small button--icon" :label="`More actions for ${plugin.label}`" floating>
						<template #button>
							<AdminIcon name="ellipsis" />
						</template>
						<RouterLink class="menu-item" :to="pluginRoute(plugin.name)"><AdminIcon name="info" />Plugin details</RouterLink>
						<button type="button" class="menu-item" @click="copy(plugin.path, 'the folder path')"><AdminIcon name="copy" />Copy folder path</button>
						<template v-if="canDelete && plugin.deletable">
							<hr class="menu-rule">
							<button type="button" class="menu-item menu-item--danger" @click="remove(plugin)"><AdminIcon name="trash-2" />Delete plugin</button>
						</template>
					</MenuButton>
				</div>
			</li>
		</ul>
	</template>

	<ul v-else-if="!error" class="plugins" aria-hidden="true">
		<li v-for="row in 4" :key="row" class="plugin">
			<span class="plugin__mark" />
			<div class="plugin__main">
				<span class="skeleton skeleton--title" />
				<span class="skeleton skeleton--wide" />
			</div>
		</li>
	</ul>

	<p v-if="answer" class="notice plugins__note">
		<span>
			Plugins live in <code>user/plugins</code> or come from Composer.
			<template v-if="answer.saved">
				Which are on was set here, and is saved in <code>user/data/settings.json</code> over <code>config/plugins.php</code>.
				<button v-if="canActivate" type="button" class="link-button" @click="useConfig">Use <code>config/plugins.php</code>'s list</button>
			</template>
			<template v-else>A plugin in <code>user/plugins</code> is off until <code>config/plugins.php</code> names it in <code>enabled</code><template v-if="!answer.config"> (there's no such file yet)</template>; Composer's are on. Turning one on or off here saves the list of every plugin that's on in <code>user/data/settings.json</code>, over that file; from then on, a plugin it doesn't name is off, even one Composer installs later.</template>
			What a plugin adds shows on the screens it belongs to, not here.
		</span>
	</p>

	<AdminModal :open="installing" title="Install Plugin" @close="installing = false">
		<p>A plugin is a folder in <code>user/plugins/</code>. Put one there and it shows up in this list.</p>
		<p>A plugin published as a package is installed with <code>composer require vendor/plugin</code> instead. Composer keeps it up to date, and it can't be deleted from this screen.</p>
		<div class="install-drop">
			<AdminIcon name="upload" />
			<span>Uploading a plugin's <strong>.zip</strong> is coming.</span>
			<span class="install-drop__hint">It will be unpacked into <span class="mono">user/plugins/</span>, and stay off until you turn it on.</span>
		</div>
		<template #footer>
			<button type="button" class="button" autofocus @click="installing = false">Close</button>
			<button type="button" class="button button--primary" disabled>Upload</button>
		</template>
	</AdminModal>
</template>

<style scoped>
.count-row {
	display: flex;
	align-items: center;
	gap: var(--s-3);
	margin-bottom: var(--s-4);
	color: var(--fg-3);
	font-size: var(--text-2xs);
	font-weight: 600;
	letter-spacing: .06em;
	text-transform: uppercase;
}

.count-row__rule {
	flex: 1;
	height: 1px;
	background: var(--border);
}

.plugins {
	margin: 0;
	padding: 0;
	overflow: hidden;
	border: 1px solid var(--border);
	border-radius: var(--r-3);
	background: var(--surface);
	list-style: none;
}

.plugin {
	display: grid;
	grid-template-columns: 38px minmax(0, 1fr) auto;
	align-items: start;
	gap: var(--s-4);
	padding: var(--s-4) var(--pad-x);
	border-top: 1px solid var(--border);
	transition: background .14s ease-out;
}

.plugin:first-child {
	border-top: 0;
}

.plugin:hover {
	background: var(--surface-2);
}

.plugin__mark {
	display: grid;
	place-items: center;
	width: 38px;
	height: 38px;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface-2);
	color: var(--fg-2);
}

.plugin:hover .plugin__mark {
	background: var(--surface-3);
}

.plugin.is-off .plugin__mark,
.plugin.is-off .plugin__label,
.plugin.is-off .plugin__description {
	opacity: .62;
}

.plugin__main {
	display: flex;
	flex-direction: column;
	gap: var(--s-2);
	min-width: 0;
}

.plugin__main > * {
	margin: 0;
}

.plugin__name {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
}

.plugin__label {
	color: var(--fg);
	font-family: var(--font-title);
	font-size: var(--title-size);
	font-weight: 600;
	letter-spacing: var(--title-track);
	text-decoration: none;
	overflow-wrap: anywhere;
}

.plugin__label:hover {
	color: var(--accent);
}

.plugin__package {
	color: var(--fg-3);
	font-size: var(--text-2xs);
}

.plugin__description {
	max-width: 70ch;
	color: var(--fg-2);
	font-size: var(--text-sm);
	line-height: 1.5;
}

/* A problem is said where the switch is, saying what's needed. */
.plugin__message {
	display: flex;
	align-items: flex-start;
	gap: var(--s-2);
	max-width: 70ch;
	padding: var(--s-3);
	border-radius: var(--r-1);
	background: var(--warn-soft);
	color: var(--warn);
	font-size: var(--text-xs);
	line-height: 1.45;
}

.plugin__message .icon {
	flex: none;
	width: 14px;
	height: 14px;
	margin-top: 1px;
}

.plugin__end {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	padding-top: 2px;
}

.menu-rule {
	margin: 5px -1px;
	border: 0;
	border-top: 1px solid var(--border);
}

.skeleton--title {
	width: 34%;
	height: 13px;
}

.skeleton--wide {
	width: 70%;
}

.plugins__note {
	margin-top: var(--s-6);
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.link-button {
	padding: 0;
	border: 0;
	background: none;
	color: var(--accent);
	font: inherit;
	text-decoration: underline;
	text-underline-offset: .15em;
	cursor: pointer;
}

.install-drop {
	display: grid;
	justify-items: center;
	gap: var(--s-1);
	margin-top: var(--s-4);
	padding: var(--s-5);
	border: 1px dashed var(--border-strong);
	border-radius: var(--r-2);
	color: var(--fg-3);
	font-size: var(--text-sm);
	text-align: center;
}

.install-drop__hint {
	font-size: var(--text-xs);
}

@media (width <= 640px) {
	.plugin {
		grid-template-columns: 30px minmax(0, 1fr);
	}

	.plugin__mark {
		width: 30px;
		height: 30px;
	}

	.plugin__end {
		grid-column: 2;
	}
}
</style>
