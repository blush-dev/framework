<script setup lang="ts">
/**
 * An icon pack's details (D-385; drawn as the extensions sketch's in
 * D-565), at `/icon-packs/{vendor}/{name}`, and the core set's at
 * `/icon-packs/core`: its label with Can't turn on beside it when it
 * can't, its switch (the core set's locked on), every icon at a size to
 * judge, with a filter, each one copying its reference when clicked
 * (`weather/sun`, or a core icon's name), then its Details
 * (`ExtensionFacts`, with its namespace and how many icons) beside its
 * Dependencies (`ExtensionDependencies`: what it requires, checked
 * against the site as a plugin's are, D-431, so one that isn't met keeps
 * the pack from turning on, or its icons from loading; its conflicts,
 * replaces, provides, and suggests, D-434 to D-439; and those on the
 * other side, D-440), and **Delete icon pack** for a folder pack. A
 * Composer pack says how it's removed instead.
 */

import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AbandonedNotice from '../components/AbandonedNotice.vue';
import AdminIcon from '../components/AdminIcon.vue';
import DangerZone from '../components/DangerZone.vue';
import ExtensionDependencies from '../components/ExtensionDependencies.vue';
import ExtensionFacts from '../components/ExtensionFacts.vue';
import PreviousVersion from '../components/PreviousVersion.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import { ApiError, errorMessage, request, type CoreIcons, type IconPackSummary } from '../api';
import { packIconMask, useIconPacks } from '../icon-packs';
import { screenTitle } from '../screen';
import { can } from '../session';
import { copyText } from '../toast';
import { t } from '../messages';
import MessageText from '../components/MessageText.vue';

const route  = useRoute();
const router = useRouter();

// What deleting the pack refused, said with the screen's own failures.
const { busy, error: failure, toggle: togglePack, remove: removePack } = useIconPacks();

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
			error.value = errorMessage(caught, t('icon_pack_view.load_failed', 'The icon pack couldn\'t be loaded.'));
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
		<RouterLink class="page-back" :to="{ name: 'icon-packs' }"><AdminIcon name="chevron-left" />{{ t('icon_pack_view.back', 'All icon packs') }}</RouterLink>
		<div class="page-header__text">
			<div class="page-header__title">
				<h1 tabindex="-1">{{ label ?? t('icon_pack_view.title', 'Icon Pack') }}</h1>
				<span v-if="blocked" class="pill pill--warn">{{ t('icon_pack_view.cant_turn_on', "Can't turn on") }}</span>
				<span v-else-if="core" class="pill">{{ t('icon_pack_view.built_in', 'Built in') }}</span>
			</div>
			<p v-if="pack" class="page-header__hint">{{ pack.description || t('icon_pack_view.no_description', 'This icon pack has no description.') }}</p>
			<p v-else-if="core" class="page-header__hint">{{ t('icon_pack_view.core_hint', "Blush's own icons: always on, and always available to content.") }}</p>
		</div>
		<div v-if="pack" class="page-header__actions">
			<ToggleSwitch :checked="pack.running" :label="pack.label" :locked="blocked !== null || !canActivate" :busy="busy === pack.name" :reason="blocked ?? (canActivate ? null : t('icon_pack_view.no_capability', 'Your role can\'t turn icon packs on and off.'))" @change="toggle" />
		</div>
		<div v-else-if="core" class="page-header__actions">
			<ToggleSwitch :checked="true" :label="core.label" locked :reason="t('icon_pack_view.core_always_on', 'The core set is always on.')" />
		</div>
	</header>

	<p v-if="error || failure" class="notice notice--error" role="alert">{{ error || failure }}</p>

	<div v-if="shown" class="extension-detail">
		<AbandonedNotice v-if="pack" :noun="t('icon_pack_view.noun', 'icon pack')" :abandoned="pack.abandoned" :replacement="pack.replacement" />
		<p v-if="pack && blocked" class="notice notice--warn">
			<span>{{ blocked }} {{ pack.enabled ? t('icon_pack_view.blocked_on', 'It\'s turned on, but its icons aren\'t available until that\'s fixed, and anywhere one is used shows nothing.') : t('icon_pack_view.blocked_off', 'It can\'t be turned on until that\'s fixed.') }}</span>
		</p>
		<p v-else-if="pack && !pack.enabled" class="notice notice--warn">
			<span>{{ t('icon_pack_view.off', 'This pack is off, so its icons aren\'t available, and anywhere one is used shows nothing.') }}</span>
		</p>

		<section class="panel" aria-labelledby="icons-heading">
			<header class="panel__header">
				<h2 id="icons-heading">{{ t('icon_pack_view.icons_heading', 'Icons') }}</h2>
				<p class="panel__hint">{{ t('icon_pack_view.icons_hint', "Click one to copy how it's used") }}</p>
			</header>
			<div class="panel__body">
				<div class="browse-bar">
					<label class="search-field browse-bar__field">
						<AdminIcon name="search" />
						<span class="visually-hidden">{{ t('icon_pack_view.filter', 'Filter icons') }}</span>
						<input v-model="query" type="search" :placeholder="t('icon_pack_view.filter', 'Filter icons')">
					</label>
					<span class="browse-bar__count" aria-live="polite">{{ icons.length === shown.icons.length ? t('icon_pack_view.count', '{count} icons', { count: shown.count }) : t('icon_pack_view.count_filtered', '{shown} of {count}', { shown: icons.length, count: shown.count }) }}</span>
				</div>
				<div v-if="icons.length" class="browse">
					<button v-for="icon in icons" :key="icon.name" type="button" class="browse__icon" @click="copyText(icon.name, t('icon_pack_view.copy_what', 'the icon\'s name'), icon.name)">
						<span v-if="icon.svg" class="browse__glyph" :style="{ maskImage: packIconMask(icon) }" aria-hidden="true" />
						<AdminIcon v-else name="image-off" />
						<code>{{ icon.name }}</code>
					</button>
				</div>
				<p v-else class="field__help">{{ core ? t('icon_pack_view.no_match_set', 'No icon in this set matches “{query}”.', { query }) : t('icon_pack_view.no_match_pack', 'No icon in this pack matches “{query}”.', { query }) }}</p>
			</div>
		</section>

		<div class="extension-detail__columns">
			<template v-if="pack">
				<ExtensionFacts :extension="pack" :installed-by="pack.source === 'composer' ? 'Composer' : t('icon_pack_view.installed_folder', 'A folder in extensions/')" :folder="pack.path" :namespace="`${pack.namespace}/`">
					<template #kind>
						<dt>{{ t('icon_pack_view.fact_icons', 'Icons') }}</dt>
						<dd>{{ pack.count }}</dd>
					</template>
				</ExtensionFacts>
				<ExtensionDependencies :extension="pack" :noun="t('icon_pack_view.noun', 'icon pack')" :replaces-hint="t('icon_pack_view.replaces_hint', 'It adds no icons')" />
			</template>
			<section v-else-if="core" class="panel" aria-labelledby="details-heading">
				<header class="panel__header"><h2 id="details-heading">{{ t('icon_pack_view.details', 'Details') }}</h2></header>
				<div class="panel__section">
					<dl class="facts facts--grid">
						<dt>{{ t('icon_pack_view.fact_version', 'Version') }}</dt>
						<dd class="mono">{{ core.version }}</dd>
						<dt>{{ t('icon_pack_view.fact_used_as', 'Used as') }}</dt>
						<dd><MessageText :text="t('icon_pack_view.used_as', 'Each icon\'s name alone, such as {example}')"><template #example><code>{{ core.icons[0]?.name ?? 'house' }}</code></template></MessageText></dd>
						<dt>{{ t('icon_pack_view.fact_icons', 'Icons') }}</dt>
						<dd>{{ core.count }}</dd>
						<dt>{{ t('icon_pack_view.fact_installed_by', 'Installed by') }}</dt>
						<dd>Blush</dd>
						<dt>{{ t('icon_pack_view.fact_drawn_from', 'Drawn from') }}</dt>
						<dd><a href="https://lucide.dev" target="_blank" rel="noopener">Lucide<span class="visually-hidden">{{ t('icon_pack_view.new_tab', ' (new tab)') }}</span></a></dd>
					</dl>
				</div>
			</section>
		</div>

		<template v-if="pack">
			<PreviousVersion kind="icon-pack" :extension="pack" :live="pack.enabled" @changed="load" />
			<p v-if="pack.source === 'composer'" class="notice">
				<span><MessageText :text="t('icon_pack_view.composer', 'Composer manages this icon pack, so it can\'t be deleted here. Remove it from the project with {command}, and it leaves this list.')"><template #command><code>composer remove {{ pack.name }}</code></template></MessageText></span>
			</p>
			<DangerZone v-else-if="canDelete && pack.deletable">
				{{ t('icon_pack_view.delete_body', 'Deleting removes the folder from the server, and its icons stop working wherever they\'re used.') }}
				<template #action><button type="button" class="button button--danger" @click="remove"><AdminIcon name="trash-2" />{{ t('icon_pack_view.delete', 'Delete Icon Pack') }}</button></template>
			</DangerZone>
		</template>
	</div>

	<p v-else-if="missing" class="notice notice--warn" role="alert">
		<span><MessageText :text="t('icon_pack_view.missing', 'No icon pack named {name} is installed.')"><template #name><span class="mono">{{ name }}</span></template></MessageText></span>
	</p>

	<div v-else-if="!error" class="extension-detail" aria-hidden="true">
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--title" /><span class="skeleton" /><span class="skeleton skeleton--half" /></div></div>
	</div>
</template>

<style scoped>
.browse-bar {
	display: flex;
	align-items: center;
	gap: var(--s-3);
	margin-bottom: var(--s-4);
}

/* The admin's search field, narrower, at the panel's smaller size. */
.browse-bar__field {
	max-width: 320px;
}

.browse-bar__field .icon {
	width: 14px;
	height: 14px;
}

.browse-bar__field input {
	font-size: var(--font-size-small);
}

.browse-bar__count {
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
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
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-medium);
	background: var(--color-surface);
	color: var(--color-text-secondary);
	font: inherit;
	text-align: center;
	cursor: pointer;
}

.browse__icon:hover {
	border-color: var(--border-color-strong);
	background: var(--color-surface-subtle);
	color: var(--color-text);
}

.browse__icon:focus-visible {
	outline: 2px solid var(--color-accent);
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
	color: var(--color-text-muted);
	font-size: var(--font-size-xxsmall);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.browse__icon:hover code {
	color: var(--color-text-secondary);
}

.skeleton--title {
	width: 40%;
	height: 14px;
}

.skeleton--half {
	width: 64%;
}
</style>
