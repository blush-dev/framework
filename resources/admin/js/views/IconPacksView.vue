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
 * the list names every pack that's on. The core set is always on. **Delete** removes a folder
 * pack (or a broken one) from `user/icons`. A pack's name, and **Icon
 * pack details** in its menu, open its details screen (`IconPackView`).
 *
 * Installing from the admin is planned (packs are data, so they're the
 * first kind it will take): **Install Icon Pack** says how for now.
 */

import { computed } from 'vue';
import AdminIcon from '../components/AdminIcon.vue';
import InstallModal, { type InstallState } from '../components/InstallModal.vue';
import MenuButton from '../components/MenuButton.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import type { CoreIcons, IconPackSummary, PackIcon } from '../api';
import { iconPackRoute, packIconMask, useIconPacks } from '../icon-packs';
import { useInstall } from '../install';
import { copy, folderName } from '../themes';
import { can } from '../session';

const { answer, error, busy, load, toggle: togglePack, useConfig, remove: removePack } = useIconPacks();
const { installing, canInstall, afterInstall, hop } = useInstall('icon-pack', load);

// What the account may do here (D-389).
const canActivate = can('extensions.icon-packs.activate');
const canDelete   = can('extensions.icon-packs.delete');

void load();

// The cells a card shows.
const CELLS = 12;

const packs   = computed(() => answer.value?.packs ?? []);
const count   = computed(() => packs.value.length + (answer.value?.invalid.length ?? 0) + (answer.value ? 1 : 0));
const showing = computed(() => (answer.value?.core.count ?? 0) + packs.value.filter((pack) => pack.enabled).reduce((total, pack) => total + pack.count, 0));

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

// What a pack the Install modal installed is, and whether it can be turned on.
function installState(name: string): InstallState {
	const pack = packs.value.find((item) => item.name === name);

	return {
		words: pack?.enabled ? 'turned on' : 'turned off',
		next: pack !== undefined && !pack.enabled && canActivate,
		live: pack?.enabled ?? false
	};
}

async function next(name: string): Promise<void> {
	installing.value = false;

	const pack = packs.value.find((item) => item.name === name);

	if (pack !== undefined) {
		await toggle(pack, true);
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

	<div v-if="answer" class="packs">
		<article v-for="pack in packs" :key="pack.name" class="pack" :class="{ 'is-off': !pack.enabled }">
			<div class="pack__glyphs" aria-hidden="true">
				<span v-for="icon in cells(pack).icons" :key="icon.name"><span v-if="icon.svg" class="pack__glyph" :style="{ maskImage: packIconMask(icon) }" /></span>
				<span v-if="cells(pack).more"><span class="pack__more">+{{ cells(pack).more }}</span></span>
				<span v-for="blank in cells(pack).blanks" :key="`blank-${blank}`" />
			</div>
			<div class="pack__body">
				<p class="pack__name">
					<RouterLink class="pack__label" :to="iconPackRoute(pack.name)">{{ pack.label }}</RouterLink>
					<span v-if="pack.version" class="pack__version mono">{{ pack.version }}</span>
				</p>
				<p v-if="pack.description" class="pack__description">{{ pack.description }}</p>
				<p v-if="pack.source === 'composer' && !pack.enabled" class="pack__description">Installed by Composer. It's off because the list of packs turned on here doesn't name it.</p>
				<ul class="pack__facts">
					<li v-if="pack.source === 'composer'"><AdminIcon name="package" /><span>Composer · <span class="mono">{{ pack.name }}</span></span></li>
					<li v-else><AdminIcon name="folder" /><span class="mono">{{ pack.path }}</span></li>
					<li><AdminIcon name="shapes" /><span>{{ pack.count }} {{ pack.count === 1 ? 'icon' : 'icons' }} in <span class="mono">{{ pack.namespace }}/</span></span></li>
				</ul>
			</div>
			<div class="pack__foot">
				<ToggleSwitch :checked="pack.enabled" :label="pack.label" :locked="!canActivate" :busy="busy === pack.name" :reason="canActivate ? null : 'Your role can\'t turn icon packs on and off.'" @change="toggle(pack, $event)" />
				<MenuButton class="pack__more-actions" button-class="button button--ghost button--small button--icon" :label="`More actions for ${pack.label}`" floating>
					<template #button>
						<AdminIcon name="ellipsis" />
					</template>
					<RouterLink class="menu-item" :to="iconPackRoute(pack.name)"><AdminIcon name="info" />Icon pack details</RouterLink>
					<button type="button" class="menu-item" @click="copy(pack.path, 'the folder path')"><AdminIcon name="copy" />Copy folder path</button>
					<template v-if="canDelete && pack.deletable && pack.folder">
						<hr class="menu-rule">
						<button type="button" class="menu-item menu-item--danger" @click="remove(pack.label, pack.folder, pack)"><AdminIcon name="trash-2" />Delete icon pack</button>
					</template>
				</MenuButton>
			</div>
		</article>

		<article class="pack">
			<div class="pack__glyphs" aria-hidden="true">
				<span v-for="icon in cells(answer.core).icons" :key="icon.name"><span v-if="icon.svg" class="pack__glyph" :style="{ maskImage: packIconMask(icon) }" /></span>
				<span v-if="cells(answer.core).more"><span class="pack__more">+{{ cells(answer.core).more }}</span></span>
				<span v-for="blank in cells(answer.core).blanks" :key="`blank-${blank}`" />
			</div>
			<div class="pack__body">
				<p class="pack__name">
					<RouterLink class="pack__label" :to="{ name: 'icon-pack-core' }">{{ answer.core.label }}</RouterLink>
					<span class="pill">Built in</span>
					<span class="pack__version mono">{{ answer.core.version }}</span>
				</p>
				<p class="pack__description">Blush's own icons: always on, and always available to content.</p>
				<ul class="pack__facts">
					<li><AdminIcon name="package" />Ships with Blush</li>
					<li><AdminIcon name="shapes" /><span>{{ answer.core.count }} icons, by name alone</span></li>
				</ul>
			</div>
			<div class="pack__foot">
				<ToggleSwitch :checked="true" :label="answer.core.label" locked reason="The core set is always on." />
				<RouterLink class="button button--ghost button--small pack__more-actions" :to="{ name: 'icon-pack-core' }">Details</RouterLink>
			</div>
		</article>

		<article v-for="pack in answer.invalid" :key="pack.where" class="pack">
			<div class="pack__broken"><AdminIcon name="triangle-alert" /><span>No icons</span></div>
			<div class="pack__body">
				<p class="pack__name">
					<span class="pack__label mono">{{ pack.where }}</span>
					<span class="pill pill--warn">Can't be used</span>
				</p>
				<p class="pack__message">
					<AdminIcon name="triangle-alert" /><span>{{ pack.reason }} Its icons can't be used until that's fixed.</span>
				</p>
			</div>
			<div v-if="canDelete && pack.deletable" class="pack__foot">
				<MenuButton class="pack__more-actions" button-class="button button--ghost button--small button--icon" :label="`More actions for ${pack.where}`" floating>
					<template #button>
						<AdminIcon name="ellipsis" />
					</template>
					<button type="button" class="menu-item" @click="copy(pack.where, 'the folder path')"><AdminIcon name="copy" />Copy folder path</button>
					<hr class="menu-rule">
					<button type="button" class="menu-item menu-item--danger" @click="remove(folderName(pack.where), pack.where)"><AdminIcon name="trash-2" />Delete icon pack</button>
				</MenuButton>
			</div>
		</article>
	</div>

	<div v-else-if="!error" class="packs" aria-hidden="true">
		<div v-for="card in 3" :key="card" class="pack">
			<div class="pack__placeholder" />
			<div class="pack__body">
				<span class="skeleton skeleton--title" />
				<span class="skeleton skeleton--wide" />
				<span class="skeleton skeleton--half" />
			</div>
		</div>
	</div>

	<p v-if="answer" class="notice packs__note">
		<span>
			Icon packs live in <code>user/icons</code> or come from Composer. An icon is used as <code>pack/name</code> wherever content, a menu, or a button takes one; the namespace is what lets two packs use the same name.
			<template v-if="answer.saved">
				Which are on was set here, and is saved in <code>user/data/settings.json</code> over <code>config/icons.php</code>.
				<button v-if="canActivate" type="button" class="link-button" @click="useConfig">Use <code>config/icons.php</code>'s list</button>
			</template>
			<template v-else>A pack in <code>user/icons</code> is off until <code>config/icons.php</code> names it in <code>enabled</code><template v-if="!answer.config"> (there's no such file yet)</template>; Composer's are on. Turning one on or off here saves the list of every pack that's on in <code>user/data/settings.json</code>, over that file; from then on, a pack it doesn't name is off, even one Composer installs later.</template>
		</span>
	</p>

	<InstallModal kind="icon-pack" :open="installing" :upload="answer?.upload ?? null" :state="installState" @close="installing = false" @installed="afterInstall" @next="next" @hop="hop" />
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

.packs {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(min(340px, 100%), 1fr));
	gap: var(--s-4);
}

.pack {
	display: flex;
	flex-direction: column;
	overflow: hidden;
	border: 1px solid var(--border);
	border-radius: var(--r-3);
	background: var(--surface);
	box-shadow: var(--shadow-1);
	transition: border-color .14s ease-out;
}

.pack:hover {
	border-color: var(--border-strong);
}

/* The same two rows of six on every card. */
.pack__glyphs {
	display: grid;
	grid-template-columns: repeat(6, 1fr);
	gap: 1px;
	border-bottom: 1px solid var(--border);
	background: var(--border);
}

.pack__glyphs > span {
	display: grid;
	place-items: center;
	aspect-ratio: 1;
	background: var(--surface-2);
	color: var(--fg-2);
}

.pack.is-off .pack__glyphs > span {
	color: var(--fg-3);
}

.pack__glyph {
	width: 20px;
	height: 20px;
	background: currentColor;
	mask-position: center;
	mask-repeat: no-repeat;
	mask-size: contain;
}

.pack__more {
	color: var(--fg-3);
	font-family: var(--font-mono);
	font-size: var(--text-2xs);
}

.pack__placeholder,
.pack__broken {
	aspect-ratio: 3 / 1;
	border-bottom: 1px solid var(--border);
	background: var(--surface-2);
}

.pack__broken {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: var(--s-2);
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.pack__broken .icon {
	width: 16px;
	height: 16px;
}

.pack__body {
	display: flex;
	flex: 1;
	flex-direction: column;
	gap: var(--s-2);
	padding: var(--s-4) var(--pad-x) var(--s-3);
}

.pack__name {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
	margin: 0;
}

.pack__label {
	min-width: 0;
	color: var(--fg);
	font-family: var(--font-title);
	font-size: var(--title-size);
	font-weight: 600;
	letter-spacing: var(--title-track);
	overflow-wrap: anywhere;
}

.pack__label:any-link {
	text-decoration: none;
}

.pack__label:any-link:hover {
	color: var(--accent);
}

.pack__label.mono {
	font-family: var(--font-mono);
	font-size: var(--text-sm);
}

.pack.is-off .pack__label,
.pack.is-off .pack__description {
	opacity: .62;
}

.pack__version {
	margin-left: auto;
	padding-left: var(--s-2);
	color: var(--fg-3);
	font-size: var(--text-2xs);
}

.pack__description {
	margin: 0;
	color: var(--fg-2);
	font-size: var(--text-sm);
	line-height: 1.5;
}

.pack__facts {
	display: grid;
	gap: 5px;
	margin: var(--s-1) 0 0;
	padding: 0;
	color: var(--fg-3);
	font-size: var(--text-xs);
	list-style: none;
}

.pack__facts li {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	min-width: 0;
}

.pack__facts .icon {
	flex: none;
	width: 13px;
	height: 13px;
}

.pack__facts .mono {
	min-width: 0;
	overflow: hidden;
	color: var(--fg-2);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.pack__message {
	display: flex;
	align-items: flex-start;
	gap: var(--s-2);
	margin: var(--s-2) 0 0;
	padding: var(--s-3);
	border-radius: var(--r-1);
	background: var(--warn-soft);
	color: var(--warn);
	font-size: var(--text-xs);
	line-height: 1.45;
}

.pack__message .icon {
	flex: none;
	width: 14px;
	height: 14px;
	margin-top: 1px;
}

.pack__foot {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	padding: var(--s-3) var(--pad-x);
	border-top: 1px solid var(--border);
}

.pack__more-actions {
	margin-left: auto;
}

.menu-rule {
	margin: 5px -1px;
	border: 0;
	border-top: 1px solid var(--border);
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

.packs__note {
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



@media (width <= 640px) {
	.pack__glyphs {
		grid-template-columns: repeat(4, 1fr);
	}
}
</style>
