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
 * Filters over the rows (D-565, the extensions sketch's): words matching
 * a plugin's label, name, description, or keywords, its status (on, off,
 * or needing attention: can't run, broken, or abandoned), and its source.
 * Each row's mark is green while the plugin runs.
 *
 * Installing from the admin is planned (D-378): **Install Plugin** says
 * how to install one for now.
 */

import { computed } from 'vue';
import AdminIcon from '../components/AdminIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import ExtensionFilters from '../components/ExtensionFilters.vue';
import ExtensionMenu from '../components/ExtensionMenu.vue';
import ExtensionRow from '../components/ExtensionRow.vue';
import InstallModal from '../components/InstallModal.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import type { BrokenPluginSummary, PluginSummary } from '../api';
import { extensionRoute, useExtensionFilter } from '../extensions';
import { useInstall } from '../install';
import { usePlugins } from '../plugins';
import { can } from '../session';
import { t } from '../messages';
import MessageText from '../components/MessageText.vue';

const { answer, error, busy, plugins, broken, load, find, toggle, useConfig, remove: removePlugin, removeBroken: removeBrokenPlugin } = usePlugins();

// What the account may do here (D-389).
const canActivate = can('extensions.plugins.activate');
const canDelete   = can('extensions.plugins.delete');

// What a plugin the Install modal installed is, and whether it can be turned on.
const { installing, canInstall, afterInstall, hop, installState, next } = useInstall('plugin', load, {
	find,
	state: (plugin) => ({
		words: plugin?.running ? t('plugins_view.turned_on', 'turned on') : t('plugins_view.turned_off', 'turned off'),
		next: plugin !== null && !plugin.running && blocked(plugin) === null && canActivate,
		live: plugin?.running ?? false
	}),
	start: (plugin) => toggle(plugin, true)
});

void load();

const on    = computed(() => plugins.value.filter((plugin) => plugin.running).length);
const total = computed(() => plugins.value.length + broken.value.length);

const { query, status, source, filtered, matches, clear } = useExtensionFilter();

// The plugins and broken ones the filters leave.
const shownPlugins = computed(() => plugins.value.filter((plugin) => matches({
	on: plugin.running,
	attention: blocked(plugin) !== null || plugin.abandoned !== false || plugin.clashes.length > 0,
	source: plugin.source,
	words: [plugin.label, plugin.name, plugin.description, ...plugin.keywords]
})));
const shownBroken = computed(() => broken.value.filter((plugin) => matches({
	on: false,
	attention: true,
	source: plugin.where.startsWith('extensions/') ? 'local' : 'composer',
	words: [plugin.where, plugin.name]
})));
const shown = computed(() => shownPlugins.value.length + shownBroken.value.length);

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
			<h1 tabindex="-1">{{ t('plugins_view.title', 'Plugins') }}</h1>
			<p class="page-header__hint">{{ t('plugins_view.hint', 'Code that adds content types, blocks, icons, and actions to the site.') }}</p>
		</div>
		<div class="page-header__actions">
			<button v-if="canInstall" type="button" class="button button--primary" @click="installing = true"><AdminIcon name="upload" />{{ t('plugins_view.install', 'Install Plugin') }}</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="answer">
		<div v-if="total === 0" class="panel">
			<EmptyState icon="plug" :heading="t('plugins_view.empty', 'No Plugins Yet')" tag="h3">
				<MessageText :text="t('plugins_view.empty_text', 'Put one in {folder}, or install one with Composer (package type {type}).')">
					<template #folder><code>extensions/</code></template>
					<template #type><code>blush-plugin</code></template>
				</MessageText>
			</EmptyState>
		</div>

		<template v-else>
			<ExtensionFilters v-model:query="query" v-model:status="status" v-model:source="source" :noun="t('plugins_view.noun', 'plugins')" :on="t('plugins_view.on', 'On')" :off="t('plugins_view.off', 'Off')" :sources="['composer', 'local']" :shown="shown" :total="total" :summary="t('plugins_view.summary', '{count} on', { count: on })" :filtered="filtered" @clear="clear" />

			<div v-if="shown === 0" class="panel">
				<EmptyState icon="search" :heading="t('plugins_view.no_matches', 'No Plugin Matches')" :text="t('plugins_view.no_matches_text', 'Nothing here fits the filters in force. Clearing them brings the other plugins back.')">
					<template #actions><button type="button" class="button" @click="clear">{{ t('plugins_view.clear_filters', 'Clear Filters') }}</button></template>
				</EmptyState>
			</div>

			<ul v-else class="extension-rows">
				<ExtensionRow v-for="plugin in shownPlugins" :key="plugin.name" :class="{ 'is-off': !plugin.running, 'is-on': plugin.running }" :label="plugin.label" :to="extensionRoute('plugin', plugin.name)" :version="`${plugin.name} ${plugin.version}`" :description="plugin.description">
					<template #mark><AdminIcon name="plug" /></template>
					<template #pills>
						<span v-if="blocked(plugin)" class="pill pill--warn">{{ t('plugins_view.cant_turn_on', 'Can\'t turn on') }}</span>
						<span v-if="plugin.abandoned !== false" class="pill pill--warn">{{ t('plugins_view.abandoned', 'Abandoned') }}</span>
						<span v-if="plugin.clashes.length" class="pill pill--warn">{{ t('plugins_view.name_clash', 'Name Clash') }}</span>
					</template>
					<p v-if="plugin.source === 'composer' && !plugin.enabled" class="extension__description">{{ t('plugins_view.composer_off', 'Installed by Composer. It\'s off because the list of plugins turned on here doesn\'t name it.') }}</p>
					<p v-if="blocked(plugin)" class="notice notice--small notice--warn extension__message">
						<AdminIcon name="triangle-alert" /><span>{{ blocked(plugin) }}</span>
					</p>
					<p v-for="clash in plugin.clashes" :key="clash" class="notice notice--small notice--warn extension__message">
						<AdminIcon name="triangle-alert" /><span>{{ clash }}</span>
					</p>
					<template #end>
						<ToggleSwitch
							:checked="plugin.running"
							:label="plugin.label"
							:locked="blocked(plugin) !== null || !canActivate"
							:busy="busy === plugin.name"
							:reason="blocked(plugin) ?? (canActivate ? null : t('plugins_view.role_cant', 'Your role can\'t turn plugins on and off.'))"
							@change="toggle(plugin, $event)"
						/>
						<ExtensionMenu :label="plugin.label" :details="extensionRoute('plugin', plugin.name)" :details-label="t('plugins_view.details', 'Plugin details')" :copy="plugin.path" :delete-label="canDelete && plugin.deletable ? t('plugins_view.delete', 'Delete plugin') : undefined" @delete="remove(plugin)" />
					</template>
				</ExtensionRow>
				<ExtensionRow v-for="plugin in shownBroken" :key="plugin.where" class="is-off" :label="plugin.where" :version="plugin.name && plugin.name !== plugin.where ? plugin.name : undefined">
					<template #mark><AdminIcon name="plug" /></template>
					<template #pills><span class="pill pill--warn">{{ t('plugins_view.cant_turn_on', 'Can\'t turn on') }}</span></template>
					<p class="notice notice--small notice--warn extension__message">
						<AdminIcon name="triangle-alert" /><span>{{ plugin.enabled ? t('plugins_view.broken_on', '{reason} It\'s turned on, but can\'t run until that\'s fixed.', { reason: plugin.reason }) : t('plugins_view.broken_off', '{reason} It can\'t be turned on until that\'s fixed.', { reason: plugin.reason }) }}</span>
					</p>
					<template #end>
						<ToggleSwitch :checked="false" :label="plugin.where" locked :reason="t('plugins_view.manifest_unreadable', 'Its manifest can\'t be read.')" />
						<ExtensionMenu :label="plugin.where" :copy="plugin.where" v-bind="plugin.where.startsWith('extensions/') ? {} : { copyLabel: t('plugins_view.copy_package_name', 'Copy package name'), copied: t('plugins_view.copied_package_name', 'Copied the package name') }" :delete-label="canDelete && plugin.deletable ? t('plugins_view.delete', 'Delete plugin') : undefined" @delete="removeBroken(plugin)" />
					</template>
				</ExtensionRow>
			</ul>
		</template>
	</template>

	<ul v-else-if="!error" class="extension-rows" aria-hidden="true">
		<li v-for="row in 4" :key="row" class="extension-row">
			<span class="extension-row__mark" />
			<div class="extension-row__main">
				<span class="skeleton skeleton--title" />
				<span class="skeleton skeleton--wide" />
			</div>
		</li>
	</ul>

	<p v-if="answer" class="notice extension__note">
		<span>
			<MessageText :text="t('plugins_view.note.where', 'Plugins live in {folder} or come from Composer.')"><template #folder><code>extensions/</code></template></MessageText>
			<template v-if="answer.saved">
				<MessageText :text="t('plugins_view.note.saved', 'Which are on was set here, and is saved in {settings} over {config}.')">
					<template #settings><code>user/data/settings.json</code></template>
					<template #config><code>config/plugins.php</code></template>
				</MessageText>
				<button v-if="canActivate" type="button" class="link-button" @click="useConfig"><MessageText :text="t('plugins_view.note.use_config', 'Use {config}\'s list')"><template #config><code>config/plugins.php</code></template></MessageText></button>
			</template>
			<MessageText v-else :text="t('plugins_view.note.default', 'A plugin in {folder} is off until {config} names it in {enabled}{no_file}; Composer\'s are on. Turning one on or off here saves the list of every plugin that\'s on in {settings}, over that file; from then on, a plugin it doesn\'t name is off, even one Composer installs later.')">
				<template #folder><code>extensions/</code></template>
				<template #config><code>config/plugins.php</code></template>
				<template #enabled><code>enabled</code></template>
				<template #no_file><template v-if="!answer.config"> {{ t('plugins_view.note.no_file', '(there\'s no such file yet)') }}</template></template>
				<template #settings><code>user/data/settings.json</code></template>
			</MessageText>
			{{ t('plugins_view.note.screens', 'What a plugin adds shows on the screens it belongs to, not here.') }}
		</span>
	</p>

	<InstallModal kind="plugin" :open="installing" :upload="answer?.upload ?? null" :state="installState" @close="installing = false" @installed="afterInstall" @next="next" @hop="hop" />
</template>

<style scoped>
.skeleton--title {
	width: 34%;
	height: 13px;
}

.skeleton--wide {
	width: 70%;
}
</style>
