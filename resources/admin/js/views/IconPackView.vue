<script setup lang="ts">
/**
 * An icon pack's details (D-385, the extensions sketch's), at
 * `/icon-packs/{vendor}/{name}`, and the core set's at
 * `/icon-packs/core`: its switch in the header (the core set's locked
 * on), every icon at a size to judge, with a filter, each one copying its
 * reference when clicked (`weather/sun`, or a core icon's name), then a
 * Details panel, a Requires panel (each requirement checked against the
 * site, as a plugin's are, D-431; one that isn't met keeps the pack from
 * turning on, or its icons from loading), a Conflicts panel when it
 * has `conflict` (D-435), a Replaces panel when it has `replace`
 * (D-436), a Provides panel when it has `provide` (D-439), a
 * Suggests panel when it
 * suggests anything (D-434), and **Delete icon pack** for a
 * folder pack. A Composer pack says how it's removed instead.
 */

import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AbandonedNotice from '../components/AbandonedNotice.vue';
import AdminIcon from '../components/AdminIcon.vue';
import ExtensionDependents from '../components/ExtensionDependents.vue';
import ExtensionLinks from '../components/ExtensionLinks.vue';
import ExtensionProvides from '../components/ExtensionProvides.vue';
import ExtensionRequirements from '../components/ExtensionRequirements.vue';
import ExtensionSuggestions from '../components/ExtensionSuggestions.vue';
import LicenseLinks from '../components/LicenseLinks.vue';
import PreviousVersion from '../components/PreviousVersion.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import { ApiError, request, type CoreIcons, type IconPackSummary } from '../api';
import { packIconMask, useIconPacks } from '../icon-packs';
import { screenTitle } from '../screen';
import { copy } from '../themes';
import { can } from '../session';

const route  = useRoute();
const router = useRouter();

const { busy, toggle: togglePack, remove: removePack } = useIconPacks();

// What the account may do here (D-389).
const canActivate = can('extensions.icon-packs.activate');
const canDelete   = can('extensions.icon-packs.delete');

const pack    = ref<IconPackSummary | null>(null);
const core    = ref<CoreIcons | null>(null);
const missing = ref(false);
const error   = ref('');
const query   = ref('');

const isCore = computed(() => route.name === 'icon-pack-core');
const name   = computed(() => `${String(route.params.vendor)}/${String(route.params.name)}`);
const shown  = computed(() => isCore.value ? core.value : pack.value);
const label  = computed(() => shown.value?.label ?? null);

const icons = computed(() => {
	const words = query.value.trim().toLowerCase();

	return (shown.value?.icons ?? []).filter((icon) => icon.name.toLowerCase().includes(words));
});

async function load(): Promise<void> {
	pack.value    = null;
	core.value    = null;
	missing.value = false;
	error.value   = '';
	query.value   = '';

	try {
		if (isCore.value) {
			core.value = (await request<{ core: CoreIcons }>('GET', '/icon-packs/core')).core;
		} else {
			pack.value = (await request<{ pack: IconPackSummary }>('GET', `/icon-packs/${name.value}`)).pack;
		}
	} catch (caught) {
		if (caught instanceof ApiError && caught.status === 404) {
			missing.value = true;
		} else {
			error.value = caught instanceof ApiError ? caught.message : 'The icon pack couldn\'t be loaded.';
		}
	}
}

// Another pack's address reuses the screen, so it loads again.
watch(() => route.fullPath, () => void load(), { immediate: true });

watch(label, (value) => {
	screenTitle.value = value;
}, { immediate: true });

async function toggle(on: boolean): Promise<void> {
	if (pack.value && await togglePack(pack.value, on, () => void refresh())) {
		await refresh();
	}
}

// Fetches the pack again after a switch, without clearing the screen, if
// it's still the pack on screen: whether it runs, and its requirements.
async function refresh(): Promise<void> {
	const current = pack.value?.name;

	try {
		const fresh = (await request<{ pack: IconPackSummary }>('GET', `/icon-packs/${current ?? ''}`)).pack;

		if (pack.value?.name === fresh.name) {
			pack.value = fresh;
		}
	} catch {
		// The switch saved; the screen shows it on the next load.
	}
}

// Why it can't be turned on, when it isn't running, or `null` (D-431).
const blocked = computed(() => pack.value && !pack.value.running ? pack.value.blocked : null);

async function remove(): Promise<void> {
	const value = pack.value;

	if (value?.folder && await removePack(value.label, value.folder, value)) {
		await router.push({ name: 'icon-packs' });
	}
}
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'icon-packs' }"><AdminIcon name="chevron-left" />All icon packs</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ label ?? 'Icon Pack' }}</h1>
			<p v-if="pack" class="page-header__hint">{{ pack.description || 'This icon pack has no description.' }}</p>
			<p v-else-if="core" class="page-header__hint">Blush's own icons: always on, and always available to content.</p>
		</div>
		<div v-if="pack" class="page-header__actions">
			<span v-if="blocked" class="pill pill--warn">Can't turn on</span>
			<ToggleSwitch :checked="pack.running" :label="pack.label" :locked="blocked !== null || !canActivate" :busy="busy === pack.name" :reason="blocked ?? (canActivate ? null : 'Your role can\'t turn icon packs on and off.')" @change="toggle" />
		</div>
		<div v-else-if="core" class="page-header__actions">
			<span class="pill">Built in</span>
			<ToggleSwitch :checked="true" :label="core.label" locked reason="The core set is always on." />
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div v-if="shown" class="pack-detail">
		<AbandonedNotice v-if="pack" noun="icon pack" :abandoned="pack.abandoned" :replacement="pack.replacement" />
		<p v-if="pack && blocked" class="notice notice--warn">
			<span>{{ blocked }} {{ pack.enabled ? 'It\'s turned on, but its icons aren\'t available until that\'s fixed, and anywhere one is used shows nothing.' : 'It can\'t be turned on until that\'s fixed.' }}</span>
		</p>
		<p v-else-if="pack && !pack.enabled" class="notice notice--warn">
			<span>This pack is off, so its icons aren't available, and anywhere one is used shows nothing.</span>
		</p>

		<div class="pack-detail__columns">
			<section class="panel" aria-labelledby="icons-heading">
				<header class="panel__header">
					<h2 id="icons-heading">Icons</h2>
					<p class="panel__hint">Click one to copy how it's used</p>
				</header>
				<div class="panel__body">
					<div class="browse-bar">
						<label class="browse-bar__field">
							<AdminIcon name="search" />
							<span class="visually-hidden">Filter icons</span>
							<input v-model="query" type="search" placeholder="Filter icons">
						</label>
						<span class="browse-bar__count" aria-live="polite">{{ icons.length === shown.icons.length ? `${shown.count} icons` : `${icons.length} of ${shown.count}` }}</span>
					</div>
					<div v-if="icons.length" class="browse">
						<button v-for="icon in icons" :key="icon.name" type="button" class="browse__icon" @click="copy(icon.name, icon.name)">
							<span v-if="icon.svg" class="browse__glyph" :style="{ maskImage: packIconMask(icon) }" aria-hidden="true" />
							<AdminIcon v-else name="image-off" />
							<code>{{ icon.name }}</code>
						</button>
					</div>
					<p v-else class="field__help">No icon in this {{ core ? 'set' : 'pack' }} matches “{{ query }}”.</p>
				</div>
			</section>

			<section class="panel" aria-labelledby="details-heading">
				<header class="panel__header"><h2 id="details-heading">Details</h2></header>
				<div class="panel__body">
					<dl v-if="pack" class="pack-facts">
						<dt>Name</dt>
						<dd class="mono">{{ pack.name }}</dd>
						<dt>{{ pack.authors.length > 1 ? 'Authors' : 'Author' }}</dt>
						<dd>
							<template v-if="pack.authors.length === 0">—</template>
							<span v-for="author in pack.authors" :key="author.name" class="pack-facts__author">
								<a v-if="author.homepage" :href="author.homepage" target="_blank" rel="noopener">{{ author.name }}<span class="visually-hidden"> (new tab)</span></a>
								<template v-else>{{ author.name }}</template>
								<span v-if="author.role" class="pack-facts__role">{{ author.role }}</span>
							</span>
						</dd>
						<dt>Version</dt>
						<dd :class="{ mono: pack.version }">{{ pack.version || '—' }}</dd>
						<dt>License</dt>
						<dd :class="{ mono: pack.licenses.length }"><LicenseLinks :parts="pack.licenses" /></dd>
						<ExtensionLinks :links="pack.links" :funding="pack.funding" />
						<dt>Namespace</dt>
						<dd class="mono">{{ pack.namespace }}/</dd>
						<ExtensionDependents :dependents="pack.requiredBy" />
						<ExtensionDependents :dependents="pack.conflictedBy" label="Conflicts with it" />
						<ExtensionDependents :dependents="pack.replacedBy" label="Replaced by" />
						<ExtensionDependents :dependents="pack.providedBy" label="Also provided by" />
						<dt>Icons</dt>
						<dd>{{ pack.count }}</dd>
						<dt>Installed by</dt>
						<dd>{{ pack.source === 'composer' ? 'Composer' : 'A folder in extensions/' }}</dd>
						<dt>Folder</dt>
						<dd>
							<span class="mono">{{ pack.path }}</span>
							<button type="button" class="button button--ghost button--small button--icon pack-facts__copy" :aria-label="`Copy ${pack.path}`" @click="copy(pack.path, 'the folder path')"><AdminIcon name="copy" /></button>
						</dd>
					</dl>
					<dl v-else-if="core" class="pack-facts">
						<dt>Version</dt>
						<dd class="mono">{{ core.version }}</dd>
						<dt>Used as</dt>
						<dd>Each icon's name alone, such as <code>{{ core.icons[0]?.name ?? 'house' }}</code></dd>
						<dt>Icons</dt>
						<dd>{{ core.count }}</dd>
						<dt>Installed by</dt>
						<dd>Blush</dd>
						<dt>Drawn from</dt>
						<dd><a href="https://lucide.dev" target="_blank" rel="noopener">Lucide<span class="visually-hidden"> (new tab)</span></a></dd>
					</dl>
				</div>
			</section>
		</div>

		<section v-if="pack" class="panel" aria-labelledby="requires-heading">
			<header class="panel__header">
				<h2 id="requires-heading">Requires</h2>
				<p class="panel__hint">Checked against this site</p>
			</header>
			<div class="panel__body">
				<ExtensionRequirements :requirements="pack.requirements" />
			</div>
		</section>
		<section v-if="pack && pack.conflicts.length" class="panel" aria-labelledby="conflicts-heading">
			<header class="panel__header">
				<h2 id="conflicts-heading">Conflicts</h2>
				<p class="panel__hint">Checked against what's on</p>
			</header>
			<div class="panel__body">
				<ExtensionRequirements :requirements="pack.conflicts" list="conflicts" />
			</div>
		</section>

		<section v-if="pack && pack.replaces.length" class="panel" aria-labelledby="replaces-heading">
			<header class="panel__header">
				<h2 id="replaces-heading">Replaces</h2>
				<p class="panel__hint">It adds no icons while one of these is on</p>
			</header>
			<div class="panel__body">
				<ExtensionRequirements :requirements="pack.replaces" list="replaces" />
			</div>
		</section>

		<section v-if="pack && pack.provides.length" class="panel" aria-labelledby="provides-heading">
			<header class="panel__header">
				<h2 id="provides-heading">Provides</h2>
				<p class="panel__hint">Meets a requirement of any of these while it runs</p>
			</header>
			<div class="panel__body">
				<ExtensionProvides :provides="pack.provides" />
			</div>
		</section>

		<section v-if="pack && pack.suggests.length" class="panel" aria-labelledby="suggests-heading">
			<header class="panel__header">
				<h2 id="suggests-heading">Suggests</h2>
				<p class="panel__hint">Works well with these; none is needed</p>
			</header>
			<div class="panel__body">
				<ExtensionSuggestions :suggestions="pack.suggests" />
			</div>
		</section>

		<template v-if="pack">
			<PreviousVersion kind="icon-pack" :extension="pack" :live="pack.enabled" @changed="load" />
			<p v-if="pack.source === 'composer'" class="notice">
				<span>Composer manages this icon pack, so it can't be deleted here. Remove it from the project with <code>composer remove {{ pack.name }}</code>, and it leaves this list.</span>
			</p>
			<div v-else-if="canDelete && pack.deletable" class="danger-zone">
				<p>Deleting removes the folder from the server, and its icons stop working wherever they're used.</p>
				<button type="button" class="button button--danger" @click="remove"><AdminIcon name="trash-2" />Delete icon pack</button>
			</div>
		</template>
	</div>

	<p v-else-if="missing" class="notice notice--warn" role="alert">
		<span>No icon pack named <span class="mono">{{ name }}</span> is installed.</span>
	</p>

	<div v-else-if="!error" class="pack-detail" aria-hidden="true">
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--title" /><span class="skeleton" /><span class="skeleton skeleton--half" /></div></div>
	</div>
</template>

<style scoped>
.pack-detail {
	display: grid;
	gap: var(--s-5);
}

.pack-detail__columns {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 380px;
	align-items: start;
	gap: var(--s-5);
}

.browse-bar {
	display: flex;
	align-items: center;
	gap: var(--s-3);
	margin-bottom: var(--s-4);
}

.browse-bar__field {
	display: flex;
	flex: 1;
	align-items: center;
	gap: var(--s-2);
	max-width: 320px;
	height: var(--ctl);
	padding: 0 var(--s-3);
	border: 1px solid var(--border-strong);
	border-radius: var(--r-1);
	background: var(--surface);
	color: var(--fg-3);
}

.browse-bar__field:focus-within {
	border-color: var(--accent);
}

.browse-bar__field .icon {
	flex: none;
	width: 14px;
	height: 14px;
}

.browse-bar__field input {
	width: 100%;
	border: 0;
	outline: none;
	background: none;
	color: var(--fg);
	font: inherit;
	font-size: var(--text-sm);
}

.browse-bar__count {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

/* Every icon, at a size to judge. */
.browse {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
	gap: var(--s-2);
}

.browse__icon {
	display: grid;
	justify-items: center;
	gap: var(--s-2);
	min-width: 0;
	padding: var(--s-4) var(--s-2);
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	color: var(--fg-2);
	font: inherit;
	text-align: center;
	cursor: pointer;
}

.browse__icon:hover {
	border-color: var(--border-strong);
	background: var(--surface-2);
	color: var(--fg);
}

.browse__icon:focus-visible {
	outline: 2px solid var(--accent);
	outline-offset: 2px;
}

.browse__glyph,
.browse__icon .icon {
	width: 24px;
	height: 24px;
}

.browse__glyph {
	background: currentColor;
	mask-position: center;
	mask-repeat: no-repeat;
	mask-size: contain;
}

.browse__icon code {
	max-width: 100%;
	padding: 0;
	overflow: hidden;
	border: 0;
	background: none;
	color: var(--fg-3);
	font-size: var(--text-2xs);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.browse__icon:hover code {
	color: var(--fg-2);
}

.pack-facts {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr);
	align-items: baseline;
	gap: var(--s-3) var(--s-4);
	margin: 0;
	font-size: var(--text-sm);
}

.pack-facts dt {
	color: var(--fg-3);
}

.pack-facts dd {
	margin: 0;
	min-width: 0;
	overflow-wrap: anywhere;
}

.pack-facts__author {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	gap: 0 var(--s-2);
}

.pack-facts__author + .pack-facts__author {
	margin-top: var(--s-1);
}

.pack-facts__role {
	color: var(--fg-3);
}

.pack-facts__copy {
	margin-block: -6px;
	vertical-align: middle;
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
	.pack-detail__columns {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
