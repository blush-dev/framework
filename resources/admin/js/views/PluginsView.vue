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
 * requirements aren't met can't be turned on, and says why; so can't a
 * broken one, listed by its folder with what's wrong (D-394). **Delete**
 * removes a folder plugin that's off, or a broken one. A plugin's name, and **Plugin
 * details** in its menu, open its details screen (`PluginView`).
 *
 * Installing from the admin is planned (D-378): **Install Plugin** says
 * how to install one for now.
 */

import { computed } from 'vue';
import AdminIcon from '../components/AdminIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import ExtensionMenu from '../components/ExtensionMenu.vue';
import InstallModal from '../components/InstallModal.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import type { BrokenPluginSummary, PluginSummary } from '../api';
import { extensionRoute } from '../extensions';
import { useInstall } from '../install';
import { usePlugins } from '../plugins';
import { can } from '../session';

const { answer, error, busy, plugins, broken, load, find, toggle, useConfig, remove: removePlugin, removeBroken: removeBrokenPlugin } = usePlugins();

// What the account may do here (D-389).
const canActivate = can('extensions.plugins.activate');
const canDelete   = can('extensions.plugins.delete');

// What a plugin the Install modal installed is, and whether it can be turned on.
const { installing, canInstall, afterInstall, hop, installState, next } = useInstall('plugin', load, {
	find,
	state: (plugin) => ({
		words: plugin?.running ? 'turned on' : 'turned off',
		next: plugin !== null && !plugin.running && blocked(plugin) === null && canActivate,
		live: plugin?.running ?? false
	}),
	start: (plugin) => toggle(plugin, true)
});

void load();

const on    = computed(() => plugins.value.filter((plugin) => plugin.running).length);
const total = computed(() => plugins.value.length + broken.value.length);

// Why a plugin that isn't running can't be turned on, or `null`.
function blocked(plugin: PluginSummary): string | null {
	return plugin.running ? null : plugin.blocked;
}

async function remove(plugin: PluginSummary): Promise<void> {
	if (await removePlugin(plugin)) {
		await load();
	}
}

async function removeBroken(plugin: BrokenPluginSummary): Promise<void> {
	if (await removeBrokenPlugin(plugin)) {
		await load();
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Plugins</h1>
			<p class="page-header__hint">Code that adds content types, blocks, icons, and actions to the site.</p>
		</div>
		<div class="page-header__actions">
			<button v-if="canInstall" type="button" class="button button--primary" @click="installing = true"><AdminIcon name="upload" />Install Plugin</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div class="count-row">
		<span>{{ answer ? `${total} ${total === 1 ? 'plugin' : 'plugins'} · ${on} on` : 'Loading plugins' }}</span>
		<span class="count-row__rule" />
	</div>

	<template v-if="answer">
		<div v-if="total === 0" class="panel">
			<EmptyState icon="plug" heading="No Plugins Yet" tag="h3">
				Put one in <code>extensions/</code>, or install one with Composer (package type <code>blush-plugin</code>).
			</EmptyState>
		</div>

		<ul v-else class="plugins">
			<li v-for="plugin in plugins" :key="plugin.name" class="plugin" :class="{ 'is-off': !plugin.running }">
				<span class="plugin__mark" aria-hidden="true"><AdminIcon name="plug" /></span>
				<div class="plugin__main">
					<p class="extension__name">
						<RouterLink class="extension__label" :to="extensionRoute('plugin', plugin.name)">{{ plugin.label }}</RouterLink>
						<span v-if="blocked(plugin)" class="pill pill--warn">Can't turn on</span>
						<span v-if="plugin.abandoned !== false" class="pill pill--warn">Abandoned</span>
						<span class="extension__version mono">{{ plugin.name }} {{ plugin.version }}</span>
					</p>
					<p v-if="plugin.description" class="extension__description">{{ plugin.description }}</p>
					<p v-if="plugin.source === 'composer' && !plugin.enabled" class="extension__description">Installed by Composer. It's off because the list of plugins turned on here doesn't name it.</p>
					<p v-if="blocked(plugin)" class="notice notice--small notice--warn extension__message">
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
					<ExtensionMenu :label="plugin.label" :details="extensionRoute('plugin', plugin.name)" details-label="Plugin details" :copy="plugin.path" :delete-label="canDelete && plugin.deletable ? 'Delete plugin' : undefined" @delete="remove(plugin)" />
				</div>
			</li>
			<li v-for="plugin in broken" :key="plugin.where" class="plugin is-off">
				<span class="plugin__mark" aria-hidden="true"><AdminIcon name="plug" /></span>
				<div class="plugin__main">
					<p class="extension__name">
						<span class="extension__label mono">{{ plugin.where }}</span>
						<span class="pill pill--warn">Can't turn on</span>
						<span v-if="plugin.name && plugin.name !== plugin.where" class="extension__version mono">{{ plugin.name }}</span>
					</p>
					<p class="notice notice--small notice--warn extension__message">
						<AdminIcon name="triangle-alert" /><span>{{ plugin.reason }} {{ plugin.enabled ? 'It\'s turned on, but can\'t run until that\'s fixed.' : 'It can\'t be turned on until that\'s fixed.' }}</span>
					</p>
				</div>
				<div class="plugin__end">
					<ToggleSwitch :checked="false" :label="plugin.where" locked reason="Its manifest can't be read." />
					<ExtensionMenu :label="plugin.where" :copy="plugin.where" v-bind="plugin.where.startsWith('user/') ? {} : { copyWhat: 'the package name', copyLabel: 'Copy package name' }" :delete-label="canDelete && plugin.deletable ? 'Delete plugin' : undefined" @delete="removeBroken(plugin)" />
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

	<p v-if="answer" class="notice extension__note">
		<span>
			Plugins live in <code>extensions/</code> or come from Composer.
			<template v-if="answer.saved">
				Which are on was set here, and is saved in <code>user/data/settings.json</code> over <code>config/plugins.php</code>.
				<button v-if="canActivate" type="button" class="link-button" @click="useConfig">Use <code>config/plugins.php</code>'s list</button>
			</template>
			<template v-else>A plugin in <code>extensions/</code> is off until <code>config/plugins.php</code> names it in <code>enabled</code><template v-if="!answer.config"> (there's no such file yet)</template>; Composer's are on. Turning one on or off here saves the list of every plugin that's on in <code>user/data/settings.json</code>, over that file; from then on, a plugin it doesn't name is off, even one Composer installs later.</template>
			What a plugin adds shows on the screens it belongs to, not here.
		</span>
	</p>

	<InstallModal kind="plugin" :open="installing" :upload="answer?.upload ?? null" :state="installState" @close="installing = false" @installed="afterInstall" @next="next" @hop="hop" />
</template>

<style scoped>
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

.plugin.is-off .plugin__mark {
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

.plugin__end {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	padding-top: 2px;
}

.skeleton--title {
	width: 34%;
	height: 13px;
}

.skeleton--wide {
	width: 70%;
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
