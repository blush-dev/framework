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
 */

import { computed } from 'vue';
import AdminIcon from '../components/AdminIcon.vue';
import ExtensionCard from '../components/ExtensionCard.vue';
import ExtensionMenu from '../components/ExtensionMenu.vue';
import InstallModal from '../components/InstallModal.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import type { CoreIcons, IconPackSummary, PackIcon } from '../api';
import { extensionRoute, folderName } from '../extensions';
import { packIconMask, useIconPacks } from '../icon-packs';
import { useInstall } from '../install';
import { can } from '../session';

const { answer, error, busy, load, toggle: togglePack, useConfig, remove: removePack } = useIconPacks();

// What the account may do here (D-389).
const canActivate = can('extensions.icon-packs.activate');
const canDelete   = can('extensions.icon-packs.delete');

// What a pack the Install modal installed is, and whether it can be turned on.
const { installing, canInstall, afterInstall, hop, installState, next } = useInstall('icon-pack', load, {
	find: (name) => packs.value.find((item) => item.name === name) ?? null,
	state: (pack) => ({
		words: pack?.enabled ? 'turned on' : 'turned off',
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
			<h1 tabindex="-1">Icon Packs</h1>
			<p class="page-header__hint">Sets of icons for content, menus, and buttons, each in a namespace of its own.</p>
		</div>
		<div class="page-header__actions">
			<button v-if="canInstall" type="button" class="button button--primary" @click="installing = true"><AdminIcon name="upload" />Install Icon Pack</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div class="count-row">
		<span>{{ answer ? `${count} ${count === 1 ? 'pack' : 'packs'} · ${showing} icons available` : 'Loading icon packs' }}</span>
		<span class="count-row__rule" />
	</div>

	<div v-if="answer" class="extension-cards">
		<ExtensionCard v-for="pack in packs" :key="pack.name" :class="{ 'is-off': !pack.running }" :label="pack.label" :to="extensionRoute('icon-pack', pack.name)" :version="pack.version" :description="pack.description">
			<template #media>
				<div class="glyphs" aria-hidden="true">
					<span v-for="icon in cells(pack).icons" :key="icon.name"><span v-if="icon.svg" class="glyphs__glyph" :style="{ maskImage: packIconMask(icon) }" /></span>
					<span v-if="cells(pack).more"><span class="glyphs__more">+{{ cells(pack).more }}</span></span>
					<span v-for="blank in cells(pack).blanks" :key="`blank-${blank}`" />
				</div>
			</template>
			<template #pills>
				<span v-if="blocked(pack)" class="pill pill--warn">Can't turn on</span>
				<span v-if="pack.abandoned !== false" class="pill pill--warn">Abandoned</span>
			</template>
			<p v-if="blocked(pack)" class="notice notice--small notice--warn extension__message">
				<AdminIcon name="triangle-alert" /><span>{{ blocked(pack) }} {{ pack.enabled ? 'It\'s turned on, but its icons aren\'t available until that\'s fixed.' : 'It can\'t be turned on until that\'s fixed.' }}</span>
			</p>
			<p v-else-if="pack.source === 'composer' && !pack.enabled" class="extension__description">Installed by Composer. It's off because the list of packs turned on here doesn't name it.</p>
			<ul class="extension__facts">
				<li v-if="pack.source === 'composer'"><AdminIcon name="package" /><span>Composer · <span class="mono">{{ pack.name }}</span></span></li>
				<li v-else><AdminIcon name="folder" /><span class="mono">{{ pack.path }}</span></li>
				<li><AdminIcon name="shapes" /><span>{{ pack.count }} {{ pack.count === 1 ? 'icon' : 'icons' }} in <span class="mono">{{ pack.namespace }}/</span></span></li>
			</ul>
			<template #foot>
				<ToggleSwitch :checked="pack.running" :label="pack.label" :locked="blocked(pack) !== null || !canActivate" :busy="busy === pack.name" :reason="blocked(pack) ?? (canActivate ? null : 'Your role can\'t turn icon packs on and off.')" @change="toggle(pack, $event)" />
				<ExtensionMenu :label="pack.label" :details="extensionRoute('icon-pack', pack.name)" details-label="Icon pack details" :copy="pack.path" :delete-label="canDelete && pack.deletable && pack.folder ? 'Delete icon pack' : undefined" @delete="remove(pack.label, pack.folder ?? '', pack)" />
			</template>
		</ExtensionCard>

		<ExtensionCard :label="answer.core.label" :to="{ name: 'icon-pack-core' }" :version="answer.core.version" description="Blush's own icons: always on, and always available to content.">
			<template #media>
				<div class="glyphs" aria-hidden="true">
					<span v-for="icon in cells(answer.core).icons" :key="icon.name"><span v-if="icon.svg" class="glyphs__glyph" :style="{ maskImage: packIconMask(icon) }" /></span>
					<span v-if="cells(answer.core).more"><span class="glyphs__more">+{{ cells(answer.core).more }}</span></span>
					<span v-for="blank in cells(answer.core).blanks" :key="`blank-${blank}`" />
				</div>
			</template>
			<template #pills><span class="pill">Built in</span></template>
			<ul class="extension__facts">
				<li><AdminIcon name="package" />Ships with Blush</li>
				<li><AdminIcon name="shapes" /><span>{{ answer.core.count }} icons, by name alone</span></li>
			</ul>
			<template #foot>
				<ToggleSwitch :checked="true" :label="answer.core.label" locked reason="The core set is always on." />
				<RouterLink class="button button--ghost button--small extension__menu" :to="{ name: 'icon-pack-core' }">Details</RouterLink>
			</template>
		</ExtensionCard>

		<ExtensionCard v-for="pack in answer.invalid" :key="pack.where" :label="pack.where">
			<template #media>
				<div class="glyphs glyphs--broken"><AdminIcon name="triangle-alert" /><span>No icons</span></div>
			</template>
			<template #pills><span class="pill pill--warn">Can't be used</span></template>
			<p class="notice notice--small notice--warn extension__message">
				<AdminIcon name="triangle-alert" /><span>{{ pack.reason }} Its icons can't be used until that's fixed.</span>
			</p>
			<template v-if="canDelete && pack.deletable" #foot>
				<ExtensionMenu :label="pack.where" :copy="pack.where" delete-label="Delete icon pack" @delete="remove(folderName(pack.where), pack.where)" />
			</template>
		</ExtensionCard>
	</div>

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
			Icon packs live in <code>extensions/</code> or come from Composer. An icon is used as <code>pack/name</code> wherever content, a menu, or a button takes one; the namespace is what lets two packs use the same name.
			<template v-if="answer.saved">
				Which are on was set here, and is saved in <code>user/data/settings.json</code> over <code>config/icons.php</code>.
				<button v-if="canActivate" type="button" class="link-button" @click="useConfig">Use <code>config/icons.php</code>'s list</button>
			</template>
			<template v-else>A pack in <code>extensions/</code> is off until <code>config/icons.php</code> names it in <code>enabled</code><template v-if="!answer.config"> (there's no such file yet)</template>; Composer's are on. Turning one on or off here saves the list of every pack that's on in <code>user/data/settings.json</code>, over that file; from then on, a pack it doesn't name is off, even one Composer installs later.</template>
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
	background: var(--border);
}

.glyphs > span {
	display: grid;
	place-items: center;
	aspect-ratio: 1;
	background: var(--surface-2);
	color: var(--fg-2);
}

.is-off .glyphs > span {
	color: var(--fg-3);
}

.glyphs__glyph {
	width: 20px;
	height: 20px;
	background: currentColor;
	mask-position: center;
	mask-repeat: no-repeat;
	mask-size: contain;
}

.glyphs__more {
	color: var(--fg-3);
	font-family: var(--font-mono);
	font-size: var(--text-2xs);
}

.glyphs--placeholder,
.glyphs--broken {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: var(--s-2);
	aspect-ratio: 3 / 1;
	background: var(--surface-2);
	color: var(--fg-3);
	font-size: var(--text-xs);
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
