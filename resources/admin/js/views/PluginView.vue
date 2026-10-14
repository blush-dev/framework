<script setup lang="ts">
/**
 * A plugin's details (D-385; drawn as the extensions sketch's in D-565),
 * at `/plugins/{vendor}/{name}`: its label with Can't turn on beside it
 * when it can't, its switch, why it can't run when it can't, then its
 * Details (`ExtensionFacts`) beside its Dependencies
 * (`ExtensionDependencies`: what it requires, checked against the site,
 * D-431, its conflicts, replaces, provides, and suggests, D-434 to
 * D-439, and those on the other side, D-440), then **Delete plugin** for
 * a folder plugin that's off. A Composer plugin says how it's removed
 * instead, and one that's on says to turn it off first.
 */

import { computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AbandonedNotice from '../components/AbandonedNotice.vue';
import AdminIcon from '../components/AdminIcon.vue';
import DangerZone from '../components/DangerZone.vue';
import ExtensionDependencies from '../components/ExtensionDependencies.vue';
import ExtensionFacts from '../components/ExtensionFacts.vue';
import PreviousVersion from '../components/PreviousVersion.vue';
import MessageText from '../components/MessageText.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import { t } from '../messages';
import { usePlugins } from '../plugins';
import { screenTitle } from '../screen';
import { can } from '../session';

const route  = useRoute();
const router = useRouter();

const { answer, error, busy, load, find, requiredBy, toggle, remove: removePlugin } = usePlugins();

// What the account may do here (D-389).
const canActivate = can('extensions.plugins.activate');
const canDelete   = can('extensions.plugins.delete');

void load();

const name   = computed(() => `${String(route.params.vendor)}/${String(route.params.name)}`);
const plugin = computed(() => find(name.value));
const needs  = computed(() => plugin.value ? requiredBy(plugin.value) : []);

// Why it can't be turned on, when it isn't running, or `null`.
const blocked = computed(() => plugin.value && !plugin.value.running ? plugin.value.blocked : null);

watch(plugin, (value) => {
	screenTitle.value = value?.label ?? null;
}, { immediate: true });

async function remove(): Promise<void> {
	if (plugin.value && await removePlugin(plugin.value)) {
		await router.push({ name: 'plugins' });
	}
}
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'plugins' }"><AdminIcon name="chevron-left" />{{ t('plugin_view.all_plugins', 'All plugins') }}</RouterLink>
		<div class="page-header__text">
			<div class="page-header__title">
				<h1 tabindex="-1">{{ plugin?.label ?? t('plugin_view.plugin', 'Plugin') }}</h1>
				<span v-if="blocked" class="pill pill--warn">{{ t('plugin_view.cant_turn_on', "Can't turn on") }}</span>
			</div>
			<p v-if="plugin" class="page-header__hint">{{ plugin.description || t('plugin_view.no_description', 'This plugin has no description.') }}</p>
		</div>
		<div v-if="plugin" class="page-header__actions">
			<ToggleSwitch
				:checked="plugin.running"
				:label="plugin.label"
				:locked="blocked !== null || !canActivate"
				:busy="busy === plugin.name"
				:reason="blocked ?? (canActivate ? null : t('plugin_view.role_cant_toggle', 'Your role can\'t turn plugins on and off.'))"
				@change="toggle(plugin, $event)"
			/>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="plugin && answer">
		<div class="extension-detail">
			<AbandonedNotice :noun="t('plugin_view.noun', 'plugin')" :abandoned="plugin.abandoned" :replacement="plugin.replacement" />
			<p v-if="blocked" class="notice notice--small notice--warn">
				<AdminIcon name="triangle-alert" /><span>{{ blocked }} {{ plugin.enabled ? t('plugin_view.blocked_on', 'It\'s turned on, but nothing it adds runs until that\'s fixed.') : t('plugin_view.blocked_off', 'It can\'t be turned on until that\'s fixed.') }}</span>
			</p>
			<p v-for="clash in plugin.clashes" :key="clash" class="notice notice--small notice--warn">
				<AdminIcon name="triangle-alert" /><span>{{ t('plugin_view.clash', '{clash} Turn one of them off, or ask its author to rename it.', { clash }) }}</span>
			</p>

			<div class="extension-detail__columns">
				<ExtensionFacts :extension="plugin" :installed-by="plugin.source === 'composer' ? 'Composer' : t('plugin_view.installed_by_folder', 'A folder in extensions/')" :folder="plugin.path" />
				<ExtensionDependencies :extension="plugin" :noun="t('plugin_view.noun', 'plugin')" :replaces-hint="t('plugin_view.replaces_hint', 'It doesn\'t run')" />
			</div>

			<PreviousVersion kind="plugin" :extension="plugin" :live="plugin.running" @changed="load" />
			<p v-if="plugin.source === 'composer'" class="notice">
				<span><MessageText :text="t('plugin_view.composer_managed', 'Composer manages this plugin, so it can\'t be deleted here. Remove it from the project with {command}, and it leaves this list.')"><template #command><code>composer remove {{ plugin.name }}</code></template></MessageText></span>
			</p>
			<p v-else-if="plugin.folder && plugin.running" class="notice">
				<span>{{ t('plugin_view.on_cant_delete', "It's on, so it can't be deleted. Turn it off first.") }}</span>
			</p>
			<p v-else-if="plugin.folder && !plugin.deletable" class="notice">
				<span><MessageText :text="t('plugin_view.config_enabled', '{file} turns it on by name, so it can\'t be deleted until it\'s taken out of that file\'s {list} list.')"><template #file><code>config/plugins.php</code></template><template #list><code>enabled</code></template></MessageText></span>
			</p>
			<DangerZone v-else-if="canDelete && plugin.deletable">
				{{ t('plugin_view.delete_warning', 'Deleting removes the folder from the server.{count, plural, =0 {} one { # extension requires it.} other { # extensions require it.} }', { count: needs.length }) }}
				<template #action><button type="button" class="button button--danger" @click="remove"><AdminIcon name="trash-2" />{{ t('plugin_view.delete_plugin', 'Delete Plugin') }}</button></template>
			</DangerZone>
		</div>
	</template>

	<p v-else-if="answer" class="notice notice--warn" role="alert">
		<span><MessageText :text="t('plugin_view.not_installed', 'No plugin named {name} is installed.')"><template #name><span class="mono">{{ name }}</span></template></MessageText></span>
	</p>

	<div v-else-if="!error" class="extension-detail" aria-hidden="true">
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--title" /><span class="skeleton" /><span class="skeleton skeleton--half" /></div></div>
	</div>
</template>

<style scoped>
.skeleton--title {
	width: 40%;
	height: 14px;
}

.skeleton--half {
	width: 64%;
}
</style>
