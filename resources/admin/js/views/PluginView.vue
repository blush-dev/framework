<script setup lang="ts">
/**
 * A plugin's details (D-385, the extensions sketch's), at
 * `/plugins/{vendor}/{name}`: its switch in the header, why it can't run
 * when it can't, a Details panel and a Requires panel of equal weight
 * (each requirement checked against the site, a required plugin linked),
 * then **Delete plugin** for a folder plugin that's off. A Composer
 * plugin says how it's removed instead, and one that's on says to turn it
 * off first.
 */

import { computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import PreviousVersion from '../components/PreviousVersion.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import { pluginRoute, requirementText, usePlugins } from '../plugins';
import { screenTitle } from '../screen';
import { copy } from '../themes';
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
			<p v-if="blocked" class="plugin-message">
				<AdminIcon name="triangle-alert" /><span>{{ blocked }} {{ plugin.enabled ? 'It\'s turned on, but nothing it adds runs until that\'s fixed.' : 'It can\'t be turned on until that\'s fixed.' }}</span>
			</p>

			<div class="plugin-detail__columns">
				<section class="panel" aria-labelledby="details-heading">
					<header class="panel__header"><h2 id="details-heading">Details</h2></header>
					<div class="panel__body">
						<dl class="plugin-facts">
							<dt>Name</dt>
							<dd class="mono">{{ plugin.name }}</dd>
							<dt>{{ plugin.authors.length > 1 ? 'Authors' : 'Author' }}</dt>
							<dd>
								<template v-if="plugin.authors.length === 0">—</template>
								<span v-for="author in plugin.authors" :key="author.name" class="plugin-facts__author">
									<a v-if="author.homepage" :href="author.homepage" target="_blank" rel="noopener">{{ author.name }}<span class="visually-hidden"> (new tab)</span></a>
									<template v-else>{{ author.name }}</template>
									<span v-if="author.role" class="plugin-facts__role">{{ author.role }}</span>
									<a v-if="author.email" class="plugin-facts__email" :href="`mailto:${author.email}`" :aria-label="`Email ${author.name}`"><AdminIcon name="mail" /></a>
								</span>
							</dd>
							<dt>Version</dt>
							<dd class="mono">{{ plugin.version }}</dd>
							<dt>License</dt>
							<dd :class="{ mono: plugin.license }">{{ plugin.license || '—' }}</dd>
							<dt>Installed by</dt>
							<dd>{{ plugin.source === 'composer' ? 'Composer' : 'A folder in extensions/' }}</dd>
							<dt>Folder</dt>
							<dd>
								<span class="mono">{{ plugin.path }}</span>
								<button type="button" class="button button--ghost button--small button--icon plugin-facts__copy" :aria-label="`Copy ${plugin.path}`" @click="copy(plugin.path, 'the folder path')"><AdminIcon name="copy" /></button>
							</dd>
							<dt>Namespace</dt>
							<dd class="mono">{{ plugin.namespace }}</dd>
							<template v-if="needs.length">
								<dt>Required by</dt>
								<dd>
									<template v-for="(other, index) in needs" :key="other.name">
										<RouterLink :to="pluginRoute(other.name)">{{ other.label }}</RouterLink><template v-if="index < needs.length - 1">, </template>
									</template>
								</dd>
							</template>
						</dl>
					</div>
				</section>

				<section class="panel" aria-labelledby="requires-heading">
					<header class="panel__header">
						<h2 id="requires-heading">Requires</h2>
						<p class="panel__hint">Checked against this site</p>
					</header>
					<div class="panel__body">
						<ul v-if="plugin.requirements.length" class="requirements">
							<li v-for="requirement in plugin.requirements" :key="requirement.name">
								<AdminIcon :name="requirement.met ? 'circle-check' : 'circle-x'" :class="requirement.met ? 'is-met' : 'is-unmet'" />
								<span class="visually-hidden">{{ requirement.met ? 'Met:' : 'Not met:' }}</span>
								<RouterLink v-if="requirement.kind === 'plugin' && requirement.label" :to="pluginRoute(requirement.name)">{{ requirementText(requirement) }}</RouterLink>
								<span v-else :class="{ mono: requirement.kind === 'unknown' }">{{ requirementText(requirement) }}</span>
								<span v-if="requirement.note" class="requirements__note" :class="{ 'is-unmet': !requirement.met }">{{ requirement.note }}</span>
							</li>
						</ul>
						<p v-else class="field__help">Nothing: its manifest has no <code>requires</code>.</p>
					</div>
				</section>
			</div>

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
			<div v-else-if="canDelete && plugin.deletable" class="danger-zone">
				<p>Deleting removes the folder from the server.<template v-if="needs.length"> {{ needs.length === 1 ? '1 plugin requires' : `${needs.length} plugins require` }} it.</template></p>
				<button type="button" class="button button--danger" @click="remove"><AdminIcon name="trash-2" />Delete plugin</button>
			</div>
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

.plugin-facts {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr);
	align-items: baseline;
	gap: var(--s-3) var(--s-4);
	margin: 0;
	font-size: var(--text-sm);
}

.plugin-facts dt {
	color: var(--fg-3);
}

.plugin-facts dd {
	margin: 0;
	min-width: 0;
	overflow-wrap: anywhere;
}

.plugin-facts__author {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	gap: 0 var(--s-2);
}

.plugin-facts__author + .plugin-facts__author {
	margin-top: var(--s-1);
}

.plugin-facts__role {
	color: var(--fg-3);
}

.plugin-facts__email {
	align-self: center;
	color: var(--fg-3);
}

.plugin-facts__email:hover {
	color: var(--accent);
}

.plugin-facts__email .icon {
	width: 14px;
	height: 14px;
}

.plugin-facts__copy {
	margin-block: -6px;
	vertical-align: middle;
}

.requirements {
	display: grid;
	gap: var(--s-2);
	margin: 0;
	padding: 0;
	font-size: var(--text-sm);
	list-style: none;
}

.requirements li {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
}

.requirements .icon {
	flex: none;
	width: 15px;
	height: 15px;
}

.requirements .is-met {
	color: var(--good);
}

.requirements .is-unmet {
	color: var(--danger);
}

.requirements__note {
	color: var(--fg-3);
}

.requirements__note.is-unmet {
	color: var(--warn);
}

.plugin-message {
	display: flex;
	align-items: flex-start;
	gap: var(--s-2);
	margin: 0;
	padding: var(--s-3);
	border-radius: var(--r-1);
	background: var(--warn-soft);
	color: var(--warn);
	font-size: var(--text-xs);
	line-height: 1.45;
}

.plugin-message .icon {
	flex: none;
	width: 14px;
	height: 14px;
	margin-top: 1px;
}

.danger-zone {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-4);
	padding: var(--s-4) var(--pad-x);
	border: 1px solid var(--border);
	border-radius: var(--r-3);
	background: var(--surface);
}

.danger-zone p {
	margin: 0;
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.danger-zone .button {
	flex: none;
	margin-left: auto;
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
