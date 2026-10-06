<script setup lang="ts">
/**
 * A plugin's details (D-385, the extensions sketch's), at
 * `/plugins/{vendor}/{name}`: its switch in the header, why it can't run
 * when it can't, a Details panel and a Requires panel of equal weight
 * (each requirement checked against the site; a required extension of
 * any kind, and one that requires it, linked, D-431), a Conflicts panel
 * when its manifest has `conflict` (D-435), a Replaces panel when it
 * has `replace` (D-436), a Provides panel when it has `provide`
 * (D-439), a Suggests panel
 * when its manifest suggests anything (D-434), then **Delete
 * plugin** for a folder plugin that's off. A Composer plugin says how
 * it's removed instead, and one that's on says to turn it off first.
 */

import { computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AbandonedNotice from '../components/AbandonedNotice.vue';
import AdminIcon from '../components/AdminIcon.vue';
import DangerZone from '../components/DangerZone.vue';
import ExtensionFacts from '../components/ExtensionFacts.vue';
import ExtensionPackagePanels from '../components/ExtensionPackagePanels.vue';
import PreviousVersion from '../components/PreviousVersion.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
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
		<RouterLink class="page-back" :to="{ name: 'plugins' }"><AdminIcon name="chevron-left" />All plugins</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ plugin?.label ?? 'Plugin' }}</h1>
			<p v-if="plugin" class="page-header__hint">{{ plugin.description || 'This plugin has no description.' }}</p>
		</div>
		<div v-if="plugin" class="page-header__actions">
			<span v-if="blocked" class="pill pill--warn">Can't turn on</span>
			<ToggleSwitch
				:checked="plugin.running"
				:label="plugin.label"
				:locked="blocked !== null || !canActivate"
				:busy="busy === plugin.name"
				:reason="blocked ?? (canActivate ? null : 'Your role can\'t turn plugins on and off.')"
				@change="toggle(plugin, $event)"
			/>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="plugin && answer">
		<div class="plugin-detail">
			<AbandonedNotice noun="plugin" :abandoned="plugin.abandoned" :replacement="plugin.replacement" />
			<p v-if="blocked" class="notice notice--small notice--warn">
				<AdminIcon name="triangle-alert" /><span>{{ blocked }} {{ plugin.enabled ? 'It\'s turned on, but nothing it adds runs until that\'s fixed.' : 'It can\'t be turned on until that\'s fixed.' }}</span>
			</p>

			<div class="plugin-detail__columns">
				<section class="panel" aria-labelledby="details-heading">
					<header class="panel__header"><h2 id="details-heading">Details</h2></header>
					<div class="panel__body">
						<ExtensionFacts :extension="plugin" :installed-by="plugin.source === 'composer' ? 'Composer' : 'A folder in extensions/'" :folder="plugin.path" />
					</div>
				</section>

				<ExtensionPackagePanels :extension="plugin" replaces-hint="It doesn't run" part="requires" />
			</div>

			<ExtensionPackagePanels :extension="plugin" replaces-hint="It doesn't run" part="others" />

			<PreviousVersion kind="plugin" :extension="plugin" :live="plugin.running" @changed="load" />
			<p v-if="plugin.source === 'composer'" class="notice">
				<span>Composer manages this plugin, so it can't be deleted here. Remove it from the project with <code>composer remove {{ plugin.name }}</code>, and it leaves this list.</span>
			</p>
			<p v-else-if="plugin.folder && plugin.running" class="notice">
				<span>It's on, so it can't be deleted. Turn it off first.</span>
			</p>
			<p v-else-if="plugin.folder && !plugin.deletable" class="notice">
				<span><code>config/plugins.php</code> turns it on by name, so it can't be deleted until it's taken out of that file's <code>enabled</code> list.</span>
			</p>
			<DangerZone v-else-if="canDelete && plugin.deletable">
				Deleting removes the folder from the server.<template v-if="needs.length"> {{ needs.length === 1 ? '1 extension requires' : `${needs.length} extensions require` }} it.</template>
				<template #action><button type="button" class="button button--danger" @click="remove"><AdminIcon name="trash-2" />Delete Plugin</button></template>
			</DangerZone>
		</div>
	</template>

	<p v-else-if="answer" class="notice notice--warn" role="alert">
		<span>No plugin named <span class="mono">{{ name }}</span> is installed.</span>
	</p>

	<div v-else-if="!error" class="plugin-detail" aria-hidden="true">
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--title" /><span class="skeleton" /><span class="skeleton skeleton--half" /></div></div>
	</div>
</template>

<style scoped>
.plugin-detail {
	display: grid;
	gap: var(--s-5);
}

/* Two panels of equal weight: what a plugin's manifest holds. */
.plugin-detail__columns {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	align-items: start;
	gap: var(--s-5);
}

.skeleton--title {
	width: 40%;
	height: 14px;
}

.skeleton--half {
	width: 64%;
}

@media (width <= 1180px) {
	.plugin-detail__columns {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
