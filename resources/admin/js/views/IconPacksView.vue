<script setup lang="ts">
/**
 * Icon Packs (D-378; drawn as the extensions sketch in D-385): every
 * installed pack as a card, by label, then Blush's own core set, then
 * broken packs. Cards, because a pack's icons are its preview: each shows
 * the same two rows of six, padded with empty cells when a pack is short
 * and ending in a count when it's long. Only packs are listed, not the
 * icons themes and plugins carry.
 *
 * A pack's switch turns it on or off at once (`useIconPacks()`), saved in
 * `user/data/settings.json` over `config/icons.php`; a pack that's off
 * adds no icons. As with plugins (D-391), a Composer pack is on and a
 * local one only when config names it, until a list is saved here; then
 * the list names every pack that's on. A pack whose requirements aren't
 * met can't be turned on, or, when it's on, adds no icons, and says why
 * (D-431). The core set is always on. **Delete** removes a folder
 * pack (or a broken one) from `extensions/`. A pack's name, and **Icon
 * pack details** in its menu, open its details screen (`IconPackView`).
 *
 * Installing from the admin is planned (packs are data, so they're the
 * first kind it will take): **Install Icon Pack** says how for now.
 *
 * Filters over the list (D-565, the extensions sketch's): words matching
 * a pack's label, name, description, or keywords, its status (on, off, or
 * needing attention: can't load, broken, or abandoned), and its source;
 * and Cards or a Compact list of rows (`extensionView`), each marked with
 * its first icon, green while its icons load.
 */

import { computed } from 'vue';
import AdminIcon from '../components/AdminIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import ExtensionCard from '../components/ExtensionCard.vue';
import ExtensionFilters from '../components/ExtensionFilters.vue';
import ExtensionMenu from '../components/ExtensionMenu.vue';
import ExtensionRow from '../components/ExtensionRow.vue';
import InstallModal from '../components/InstallModal.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import type { CoreIcons, IconPackSummary, PackIcon } from '../api';
import { extensionView } from '../density';
import { extensionRoute, folderName, useExtensionFilter } from '../extensions';
import { packIconMask, useIconPacks } from '../icon-packs';
import { useInstall } from '../install';
import { can } from '../session';
import { t } from '../messages';
import MessageText from '../components/MessageText.vue';

const { answer, error, busy, load, toggle: togglePack, useConfig, remove: removePack } = useIconPacks();

// What the account may do here (D-389).
const canActivate = can('extensions.icon-packs.activate');
const canDelete   = can('extensions.icon-packs.delete');

// What a pack the Install modal installed is, and whether it can be turned on.
const { installing, canInstall, afterInstall, hop, installState, next } = useInstall('icon-pack', load, {
	find: (name) => packs.value.find((item) => item.name === name) ?? null,
	state: (pack) => ({
		words: pack?.enabled ? t('icon_packs_view.turned_on', 'turned on') : t('icon_packs_view.turned_off', 'turned off'),
		next: pack !== null && !pack.enabled && blocked(pack) === null && canActivate,
		live: pack?.enabled ?? false
	}),
	start: (pack) => toggle(pack, true)
});

void load();

// The cells a card shows.
const CELLS = 12;

const packs   = computed(() => answer.value?.packs ?? []);
const count   = computed(() => packs.value.length + (answer.value?.invalid.length ?? 0) + (answer.value ? 1 : 0));
const showing = computed(() => (answer.value?.core.count ?? 0) + packs.value.filter((pack) => pack.running).reduce((total, pack) => total + pack.count, 0));

const { query, status, source, filtered, matches, clear } = useExtensionFilter();

// The packs, the core set, and broken ones the filters leave.
const shownPacks = computed(() => packs.value.filter((pack) => matches({
	on: pack.running,
	attention: blocked(pack) !== null || pack.abandoned !== false,
	source: pack.source,
	words: [pack.label, pack.name, pack.namespace, pack.description, ...pack.keywords]
})));
const showCore = computed(() => answer.value !== null && matches({ on: true, attention: false, source: 'framework', words: [answer.value.core.label, 'core', 'Blush'] }));
const shownBroken = computed(() => (answer.value?.invalid ?? []).filter((pack) => matches({
	on: false,
	attention: true,
	source: pack.where.startsWith('extensions/') ? 'local' : 'composer',
	words: [pack.where]
})));
const shown = computed(() => shownPacks.value.length + (showCore.value ? 1 : 0) + shownBroken.value.length);

// Rows, not cards, and the slot each draws its controls in.
const list = computed(() => extensionView.value === 'list');
const foot = computed(() => list.value ? 'end' : 'foot');

// Why a pack can't be turned on, when it isn't running, or `null` (D-431).
function blocked(pack: IconPackSummary): string | null {
	return pack.running ? null : pack.blocked;
}

// A card's cells: its first icons, the rest as a count, then blanks.
function cells(pack: IconPackSummary | CoreIcons): { icons: PackIcon[]; more: number; blanks: number } {
	const over  = pack.count > CELLS;
	const icons = pack.icons.slice(0, over ? CELLS - 1 : CELLS);

	return { icons, more: over ? pack.count - icons.length : 0, blanks: CELLS - icons.length - (over ? 1 : 0) };
}

async function toggle(pack: IconPackSummary, on: boolean): Promise<void> {
	if (await togglePack(pack, on, () => void load())) {
		await load();
	}
}

async function remove(label: string, folder: string, pack: IconPackSummary | null = null): Promise<void> {
	if (await removePack(label, folder, pack)) {
		await load();
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ t('icon_packs_view.heading', 'Icon Packs') }}</h1>
			<p class="page-header__hint">{{ t('icon_packs_view.hint', 'Sets of icons for content, menus, and buttons, each in a namespace of its own.') }}</p>
		</div>
		<div class="page-header__actions">
			<button v-if="canInstall" type="button" class="button button--primary" @click="installing = true"><AdminIcon name="upload" />{{ t('icon_packs_view.install', 'Install Icon Pack') }}</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="answer">
		<ExtensionFilters v-model:query="query" v-model:status="status" v-model:source="source" :noun="t('icon_packs_view.noun', 'icon packs')" :on="t('icon_packs_view.on', 'On')" :off="t('icon_packs_view.off', 'Off')" :sources="['composer', 'local', 'framework']" :shown="shown" :total="count" :summary="t('icon_packs_view.summary', '{count} icons available', { count: showing })" :filtered="filtered" views @clear="clear" />

		<div v-if="shown === 0" class="panel">
			<EmptyState icon="search" :heading="t('icon_packs_view.no_matches', 'No Icon Pack Matches')" :text="t('icon_packs_view.no_matches_text', 'Nothing here fits the filters in force. Clearing them brings the other icon packs back.')">
				<template #actions><button type="button" class="button" @click="clear">{{ t('icon_packs_view.clear_filters', 'Clear Filters') }}</button></template>
			</EmptyState>
		</div>

		<component :is="list ? 'ul' : 'div'" v-else :class="list ? 'extension-rows' : 'extension-cards'">
			<component :is="list ? ExtensionRow : ExtensionCard" v-for="pack in shownPacks" :key="pack.name" :class="{ 'is-off': !pack.running, 'is-on': pack.running }" :label="pack.label" :to="extensionRoute('icon-pack', pack.name)" :version="pack.version" :description="list ? undefined : pack.description">
				<template v-if="list" #mark>
					<span v-if="pack.icons[0]?.svg" class="glyphs__glyph" :style="{ maskImage: packIconMask(pack.icons[0]) }" />
					<AdminIcon v-else name="shapes" />
				</template>
				<template v-else #media>
					<div class="glyphs" aria-hidden="true">
						<span v-for="icon in cells(pack).icons" :key="icon.name"><span v-if="icon.svg" class="glyphs__glyph" :style="{ maskImage: packIconMask(icon) }" /></span>
						<span v-if="cells(pack).more"><span class="glyphs__more">+{{ cells(pack).more }}</span></span>
						<span v-for="blank in cells(pack).blanks" :key="`blank-${blank}`" />
					</div>
				</template>
				<template #pills>
					<span v-if="blocked(pack)" class="pill pill--warn">{{ t('icon_packs_view.cant_turn_on', "Can't turn on") }}</span>
					<span v-if="pack.abandoned !== false" class="pill pill--warn">{{ t('icon_packs_view.abandoned', 'Abandoned') }}</span>
				</template>
				<p v-if="blocked(pack)" class="notice notice--small notice--warn extension__message">
					<AdminIcon name="triangle-alert" /><span>{{ pack.enabled ? t('icon_packs_view.blocked_on', '{reason} It\'s turned on, but its icons aren\'t available until that\'s fixed.', { reason: blocked(pack) }) : t('icon_packs_view.blocked_off', '{reason} It can\'t be turned on until that\'s fixed.', { reason: blocked(pack) }) }}</span>
				</p>
				<p v-else-if="pack.source === 'composer' && !pack.enabled" class="extension__description">{{ t('icon_packs_view.composer_off', "Installed by Composer. It's off because the list of packs turned on here doesn't name it.") }}</p>
				<ul class="extension__facts" :class="{ 'extension__facts--inline': list }">
					<li v-if="pack.source === 'composer'"><AdminIcon name="package" /><span>Composer · <span class="mono">{{ pack.name }}</span></span></li>
					<li v-else><AdminIcon name="folder" /><span class="mono">{{ pack.path }}</span></li>
					<li><AdminIcon name="shapes" /><span><MessageText :text="t('icon_packs_view.icons_in', '{count, plural, one {# icon} other {# icons} } in {namespace}', { count: pack.count })"><template #namespace><span class="mono">{{ pack.namespace }}/</span></template></MessageText></span></li>
				</ul>
				<template #[foot]>
					<ToggleSwitch :checked="pack.running" :label="pack.label" :locked="blocked(pack) !== null || !canActivate" :busy="busy === pack.name" :reason="blocked(pack) ?? (canActivate ? null : t('icon_packs_view.locked_role', 'Your role can\'t turn icon packs on and off.'))" @change="toggle(pack, $event)" />
					<ExtensionMenu :label="pack.label" :details="extensionRoute('icon-pack', pack.name)" :details-label="t('icon_packs_view.details', 'Icon pack details')" :copy="pack.path" :delete-label="canDelete && pack.deletable && pack.folder ? t('icon_packs_view.delete', 'Delete icon pack') : undefined" @delete="remove(pack.label, pack.folder ?? '', pack)" />
				</template>
			</component>

			<component :is="list ? ExtensionRow : ExtensionCard" v-if="showCore" class="is-on" :label="answer.core.label" :to="{ name: 'icon-pack-core' }" :version="answer.core.version" :description="list ? undefined : t('icon_packs_view.core_description', 'Blush\'s own icons: always on, and always available to content.')">
				<template v-if="list" #mark>
					<span v-if="answer.core.icons[0]?.svg" class="glyphs__glyph" :style="{ maskImage: packIconMask(answer.core.icons[0]) }" />
					<AdminIcon v-else name="shapes" />
				</template>
				<template v-else #media>
					<div class="glyphs" aria-hidden="true">
						<span v-for="icon in cells(answer.core).icons" :key="icon.name"><span v-if="icon.svg" class="glyphs__glyph" :style="{ maskImage: packIconMask(icon) }" /></span>
						<span v-if="cells(answer.core).more"><span class="glyphs__more">+{{ cells(answer.core).more }}</span></span>
						<span v-for="blank in cells(answer.core).blanks" :key="`blank-${blank}`" />
					</div>
				</template>
				<template #pills><span class="pill">{{ t('icon_packs_view.built_in', 'Built in') }}</span></template>
				<ul class="extension__facts" :class="{ 'extension__facts--inline': list }">
					<li><AdminIcon name="package" />{{ t('icon_packs_view.ships_with', 'Ships with Blush') }}</li>
					<li><AdminIcon name="shapes" /><span>{{ t('icon_packs_view.core_count', '{count} icons, by name alone', { count: answer.core.count }) }}</span></li>
				</ul>
				<template #[foot]>
					<ToggleSwitch :checked="true" :label="answer.core.label" locked :reason="t('icon_packs_view.core_locked', 'The core set is always on.')" />
					<span v-if="list" class="extension__menu-slot" />
					<RouterLink v-else class="button button--ghost button--small extension__menu" :to="{ name: 'icon-pack-core' }">{{ t('icon_packs_view.details_link', 'Details') }}</RouterLink>
				</template>
			</component>

			<component :is="list ? ExtensionRow : ExtensionCard" v-for="pack in shownBroken" :key="pack.where" class="is-off" :label="pack.where">
				<template v-if="list" #mark><AdminIcon name="triangle-alert" /></template>
				<template v-else #media>
					<div class="glyphs glyphs--broken"><AdminIcon name="triangle-alert" /><span>{{ t('icon_packs_view.no_icons', 'No icons') }}</span></div>
				</template>
				<template #pills><span class="pill pill--warn">{{ t('icon_packs_view.cant_be_used', "Can't be used") }}</span></template>
				<p class="notice notice--small notice--warn extension__message">
					<AdminIcon name="triangle-alert" /><span>{{ t('icon_packs_view.broken', '{reason} Its icons can\'t be used until that\'s fixed.', { reason: pack.reason }) }}</span>
				</p>
				<template v-if="canDelete && pack.deletable" #[foot]>
					<ExtensionMenu :label="pack.where" :copy="pack.where" :delete-label="t('icon_packs_view.delete', 'Delete icon pack')" @delete="remove(folderName(pack.where), pack.where)" />
				</template>
			</component>
		</component>
	</template>

	<ul v-else-if="!error && list" class="extension-rows" aria-hidden="true">
		<li v-for="row in 3" :key="row" class="extension-row">
			<span class="extension-row__mark" />
			<div class="extension-row__main">
				<span class="skeleton skeleton--title" />
				<span class="skeleton skeleton--wide" />
			</div>
		</li>
	</ul>

	<div v-else-if="!error" class="extension-cards" aria-hidden="true">
		<div v-for="card in 3" :key="card" class="extension-card">
			<div class="glyphs glyphs--placeholder" />
			<div class="extension-card__body">
				<span class="skeleton skeleton--title" />
				<span class="skeleton skeleton--wide" />
				<span class="skeleton skeleton--half" />
			</div>
		</div>
	</div>

	<p v-if="answer" class="notice extension__note">
		<span>
			<MessageText :text="t('icon_packs_view.note', 'Icon packs live in {extensions} or come from Composer. An icon is used as {usage} wherever content, a menu, or a button takes one; the namespace is what lets two packs use the same name.')"><template #extensions><code>extensions/</code></template><template #usage><code>pack/name</code></template></MessageText>
			<template v-if="answer.saved">
				<MessageText :text="t('icon_packs_view.note_saved', 'Which are on was set here, and is saved in {settings} over {config}.')"><template #settings><code>user/data/settings.json</code></template><template #config><code>config/icons.php</code></template></MessageText>
				<button v-if="canActivate" type="button" class="link-button" @click="useConfig"><MessageText :text="t('icon_packs_view.use_config', 'Use {config}\'s list')"><template #config><code>config/icons.php</code></template></MessageText></button>
			</template>
			<template v-else><MessageText :text="t('icon_packs_view.note_config', 'A pack in {extensions} is off until {config} names it in {enabled}{missing}; Composer\'s are on. Turning one on or off here saves the list of every pack that\'s on in {settings}, over that file; from then on, a pack it doesn\'t name is off, even one Composer installs later.')"><template #extensions><code>extensions/</code></template><template #config><code>config/icons.php</code></template><template #enabled><code>enabled</code></template><template #missing><template v-if="!answer.config">{{ t('icon_packs_view.no_file', ' (there\'s no such file yet)') }}</template></template><template #settings><code>user/data/settings.json</code></template></MessageText></template>
		</span>
	</p>

	<InstallModal kind="icon-pack" :open="installing" :upload="answer?.upload ?? null" :state="installState" @close="installing = false" @installed="afterInstall" @next="next" @hop="hop" />
</template>

<style scoped>
/* The same two rows of six on every card. */
.glyphs {
	display: grid;
	grid-template-columns: repeat(6, 1fr);
	gap: 1px;
	background: var(--border-color);
}

.glyphs > span {
	display: grid;
	place-items: center;
	aspect-ratio: 1;
	background: var(--color-surface-subtle);
	color: var(--color-text-secondary);
}

.is-off .glyphs > span {
	color: var(--color-text-muted);
}

.glyphs__glyph {
	display: block;
	width: 20px;
	height: 20px;
	background: currentColor;
	mask-position: center;
	mask-repeat: no-repeat;
	mask-size: contain;
}

/* A row's mark is its first icon. */
.extension-row__mark .glyphs__glyph {
	width: 16px;
	height: 16px;
}

.glyphs__more {
	color: var(--color-text-muted);
	font-family: var(--font-mono);
	font-size: var(--font-size-xxsmall);
}

.glyphs--placeholder,
.glyphs--broken {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: var(--s-2);
	aspect-ratio: 3 / 1;
	background: var(--color-surface-subtle);
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}

.glyphs--broken .icon {
	width: 16px;
	height: 16px;
}

.skeleton--title {
	width: 48%;
	height: 14px;
}

.skeleton--wide {
	width: 88%;
}

.skeleton--half {
	width: 64%;
}

@media (width <= 640px) {
	.glyphs {
		grid-template-columns: repeat(4, 1fr);
	}
}
</style>
