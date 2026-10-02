<script setup lang="ts">
/**
 * The signed-in layout (D-231, D-244): a labeled section rail (Home,
 * Content, People, Config; D-326), the panel beside it with only the active section's
 * links, a top bar, and the work area, the only part that scrolls.
 *
 * Choosing a section changes what the panel offers and nothing else: the
 * rail never navigates, Home included, so an entry being written is never
 * left by a look at another section (admin.md §6). A rail button is a
 * toggle for its panel (D-317): pressing the section already shown
 * closes the panel, leaving the rail (remembered in this browser), and
 * pressing it again, or another section, opens it. The editor opens with
 * it closed and puts it back as it was on the way out, without changing
 * what's remembered. Below 860px the rail and panel slide in together as
 * a drawer, and the shown section's button closes it.
 *
 * The top bar's trail is the section, the screens above this one, and
 * this one (`Content / Posts / Editing`, `Config / Content Types /
 * Pages`; D-317): the section crumb opens its panel (never closes it),
 * and the others are ways back. The account's menu is in the top bar,
 * with the command palette's button (⌘K anywhere, D-248). While the browser is offline, a bar under the top bar says so. The editor's focus mode drops everything but the work
 * area.
 */

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter, type RouteLocationRaw } from 'vue-router';
import { ApiError, type ContentTypeSummary } from '../api';
import { config } from '../config';
import { online } from '../connection';
import type { IconName } from '../icons';
import { focusMode, screenBleed, screenCrumb, screenTitle, screenTrail } from '../screen';
import { initials } from '../people';
import { can, session, signOut } from '../session';
import { profileType, currentType, loadTypes, typeIcon, types } from '../types';
import AdminIcon from './AdminIcon.vue';
import CommandPalette from './CommandPalette.vue';
import MenuButton from './MenuButton.vue';
import ToastHost from './ToastHost.vue';
import TypeIcon from './TypeIcon.vue';

type Area = 'home' | 'content' | 'people' | 'config';

interface NavLink {
	key: string;
	label: string;
	icon: IconName;
	to: RouteLocationRaw;
	// A content type's link, shown with the type's own icon.
	type?: ContentTypeSummary;
	current?: boolean;
	// A second line, such as the types a taxonomy groups.
	detail?: string;
	// Links nested under this one, such as its type's own taxonomies.
	links?: NavLink[];
}

interface NavGroup {
	key: string;
	heading?: string;
	links: NavLink[];
}

const route   = useRoute();
const router  = useRouter();
const leaving = ref(false);
const error   = ref('');

// Content types come from the server; the menu works without them.
onMounted(() => {
	if (can('content.edit')) {
		loadTypes().catch(() => undefined);
	}
});

// A detail screen marks its list (`meta.parent`).
const screen = (name: string, label: string, icon: IconName): NavLink => ({ key: name, label, icon, to: { name }, current: route.meta.parent === name });

/**
 * Each section's links, in groups (D-241, D-244). **Home**: the admin's
 * own screens and shortcuts. **Content**: each content type with the
 * taxonomies that group only it nested under it, the taxonomies shared
 * by several types (or every type), and Media. **People** (D-326): Your
 * Profile, People (accounts and authors in one list, D-329), and Roles.
 * **Config** (D-325): Structure (content types), Settings (its four
 * screens), and Customize (Themes and Extensions; D-327).
 * Links the account can't use aren't shown.
 */
const sections = computed<Record<Area, NavGroup[]>>(() => {
	const home: NavLink[] = [screen('dashboard', 'Dashboard', 'layout-dashboard')];

	if (can('content.edit.others')) {
		home.push(screen('health', 'Content Health', 'heart-pulse'));
	}

	const shortcuts: NavLink[] = [screen('profile', 'Your Profile', 'circle-user-round')];

	// A Settings screen (D-325).
	const settingsScreen = (key: string, label: string, icon: IconName): NavLink => ({ key: `settings-${key}`, label, icon, to: { name: 'settings', params: { screen: key } }, current: false });

	if (can('site.settings')) {
		shortcuts.push({ ...settingsScreen('general', 'Settings', 'settings'), key: 'settings' });
	}

	const inEntries = route.meta.section === 'entries';
	const link = (type: ContentTypeSummary, detail?: string): NavLink => ({
		key: type.name,
		label: type.labels.menu,
		icon: typeIcon(type),
		type,
		to: { name: 'type', params: { type: type.name } },
		current: inEntries && currentType.value === type.name,
		detail
	});

	// By the names the menu shows, which a site may shorten (D-278).
	const editing    = can('content.edit');
	const sorted     = [...types.value].sort((a, b) => a.labels.menu.localeCompare(b.labels.menu));
	const entryTypes = editing ? sorted.filter((type) => type.kind !== 'taxonomy' && type.kind !== 'profiles') : [];
	const taxonomies = editing ? sorted.filter((type) => type.kind === 'taxonomy') : [];
	const labelOf    = (name: string): string => types.value.find((type) => type.name === name)?.labels.menu ?? name;

	// A taxonomy grouping one listed type sits under it; the rest are shared.
	const owner  = (taxonomy: ContentTypeSummary): string | undefined => taxonomy.types?.length === 1 && entryTypes.some((type) => type.name === taxonomy.types?.[0]) ? taxonomy.types[0] : undefined;
	const shared = taxonomies.filter((taxonomy) => owner(taxonomy) === undefined).map((taxonomy) => {
		const grouped = (taxonomy.types ?? []).map(labelOf);

		return link(taxonomy, grouped.length === 0 ? 'Every type' : (grouped.length <= 2 ? grouped.join(', ') : `${grouped.length} types`));
	});

	const content = entryTypes.map((type) => ({ ...link(type), links: taxonomies.filter((taxonomy) => owner(taxonomy) === type.name).map((taxonomy) => link(taxonomy)) }));
	const library = can('media.upload') ? [screen('media', 'Media', 'image')] : [];

	const structure = can('site.settings') ? [screen('types', 'Content Types', 'layers'), screen('fields', 'Fields', 'group')] : [];
	const settings  = can('site.settings') ? [settingsScreen('general', 'General', 'sliders-horizontal'), settingsScreen('reading', 'Reading', 'book-open'), settingsScreen('search', 'Addresses and Search', 'globe'), settingsScreen('system', 'System', 'settings')] : [];
	const customize = can('site.settings') ? [screen('themes', 'Themes', 'paintbrush'), screen('extensions', 'Extensions', 'plug')] : [];
	// Accounts and Profiles are two lists (D-353): who can sign in, and
	// who's credited on the site. A profile's screens, and its type's
	// list and editor, mark Profiles.
	const profiles  = types.value.find((type) => type.name === profileType.value);
	const profileLink: NavLink[] = profiles && editing ? [{
		key: 'profiles',
		label: profiles.labels.menu,
		icon: 'user-round',
		to: { name: 'type', params: { type: profiles.name } },
		current: route.meta.parent === 'profiles' || (inEntries && currentType.value === profiles.name)
	}] : [];
	const people    = [
		screen('profile', 'Your Profile', 'circle-user-round'),
		...(can('accounts.manage') ? [screen('accounts', 'Accounts', 'users')] : []),
		...profileLink,
		...(can('accounts.manage') ? [screen('roles', 'Roles', 'shield')] : [])
	];

	const groups = (list: NavGroup[]): NavGroup[] => list.filter((group) => group.links.length > 0);

	return {
		home: groups([{ key: 'home', links: home }, { key: 'shortcuts', heading: 'Shortcuts', links: shortcuts }]),
		content: groups([{ key: 'types', links: content }, { key: 'shared', heading: 'Shared Taxonomies', links: shared }, { key: 'library', heading: 'Library', links: library }]),
		people: groups([{ key: 'people', links: people }]),
		config: groups([{ key: 'structure', heading: 'Structure', links: structure }, { key: 'settings', heading: 'Settings', links: settings }, { key: 'customize', heading: 'Customize', links: customize }])
	};
});

// The rail's sections; one with nothing in it for this account is left out.
const areas = computed(() => ([
	{ key: 'home', label: 'Home', icon: 'gauge' },
	{ key: 'content', label: 'Content', icon: 'file-text' },
	{ key: 'people', label: 'People', icon: 'users' },
	{ key: 'config', label: 'Config', icon: 'settings' }
] as const).filter((area) => sections.value[area.key].length > 0));

const routeArea = computed<Area>(() => {
	const area = route.meta.area;

	// Profiles are listed with people, so their screens are too.
	if (route.meta.section === 'entries' && currentType.value !== null && currentType.value === profileType.value) {
		return 'people';
	}

	return area === 'content' || area === 'people' || area === 'config' ? area : 'home';
});

// The section the panel shows: the screen's, until another is chosen.
const area = ref<Area>(routeArea.value);

watch(routeArea, (value) => {
	area.value = value;
});

const current = computed(() => areas.value.find((item) => item.key === area.value) ?? areas.value[0]);
const panelSub = computed(() => {
	if (area.value === 'content') {
		const count = types.value.length;

		return count === 0 ? 'Entries and media' : `${count} content ${count === 1 ? 'type' : 'types'}`;
	}

	if (area.value === 'people') {
		return 'Your profile, accounts, profiles, and roles';
	}

	return area.value === 'config' ? 'Types, settings, and the look' : config.site.name;
});

/**
 * A rail button: the section already shown closes its panel (or the
 * drawer); any other, or a closed panel, opens it on that section.
 */
function choose(key: Area): void {
	if (key === area.value && (narrow.value ? open.value : !hidden.value)) {
		if (narrow.value) {
			void closeDrawer(false);
		} else {
			toggle();
		}

		return;
	}

	if (hidden.value && !narrow.value) {
		toggle();
	}

	area.value = key;
}

/**
 * The trail's section crumb: opens that section's panel, never closes it.
 */
function showSection(key: Area): void {
	area.value = key;

	if (narrow.value) {
		openDrawer();
	} else if (hidden.value) {
		toggle();
	}
}

const title = computed(() => screenTitle.value ?? (typeof route.meta.title === 'string' ? route.meta.title : ''));

// The trail between the section and this screen: what the screen says,
// else the list a detail screen belongs to (`meta.parent`).
const trail = computed<{ label: string; to: RouteLocationRaw }[]>(() => {
	if (screenTrail.value.length > 0) {
		return screenTrail.value;
	}

	const parent = typeof route.meta.parent === 'string' ? router.getRoutes().find((item) => item.name === route.meta.parent) : undefined;
	const label  = parent?.meta.title;

	return parent !== undefined && typeof label === 'string' ? [{ label, to: { name: route.meta.parent as string } }] : [];
});

const sectionLabel = computed(() => ({ home: 'Home', content: 'Content', people: 'People', config: 'Config' })[routeArea.value]);
const bleed = computed(() => screenBleed.value ?? route.meta.bleed === true);

// The collapsed panel is a per-browser convenience; storage may be off.
const COLLAPSED = 'blush-admin-rail-collapsed';

function stored(): boolean {
	try {
		return localStorage.getItem(COLLAPSED) === '1';
	} catch {
		return false;
	}
}

const collapsed = ref(stored());

// The editor's own collapse: the panel shut on the way in, as a
// courtesy, and whatever it's toggled to while writing; `null` elsewhere,
// so leaving puts the remembered setting back.
const writing = computed(() => (route.meta.section === 'entries' && route.meta.bleed === true) || screenBleed.value === true);
const shut    = ref<boolean | null>(null);

watch(writing, (value) => {
	shut.value = value ? true : null;
}, { immediate: true });

const hidden = computed(() => shut.value ?? collapsed.value);

function toggle(): void {
	if (writing.value) {
		shut.value = !hidden.value;
	} else {
		collapsed.value = !collapsed.value;
	}
}

watch(collapsed, (value) => {
	try {
		localStorage.setItem(COLLAPSED, value ? '1' : '0');
	} catch {
		// Not remembered; nothing else depends on it.
	}
});

// Below 860px the rail and panel are a drawer.
const query  = window.matchMedia('(width <= 860px)');
const narrow = ref(query.matches);
const open   = ref(false);
const menu   = ref<HTMLButtonElement | null>(null);
const drawer = ref<HTMLElement | null>(null);

function changed(event: MediaQueryListEvent): void {
	narrow.value = event.matches;
	open.value   = false;
}

function openDrawer(): void {
	open.value = true;
	requestAnimationFrame(() => drawer.value?.querySelector<HTMLElement>('.panel-nav a, .panel-nav button')?.focus());
}

async function closeDrawer(returnFocus = true): Promise<void> {
	if (!open.value) {
		return;
	}

	open.value = false;

	// The work area is inert until the drawer has closed.
	if (returnFocus) {
		await nextTick();
		menu.value?.focus();
	}
}

// The command palette, from ⌘K (Ctrl+K) anywhere or the top bar.
const paletteOpen = ref(false);

function keydown(event: KeyboardEvent): void {
	if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
		event.preventDefault();
		paletteOpen.value = !paletteOpen.value;
	} else if (event.key === 'Escape' && open.value) {
		void closeDrawer();
	}
}

// Following a link closes the drawer and leaves focus mode; focus goes to
// the new screen's heading (App.vue).
router.afterEach(() => {
	focusMode.value = false;
	void closeDrawer(false);
});

onMounted(() => {
	query.addEventListener('change', changed);
	document.addEventListener('keydown', keydown);
});

onBeforeUnmount(() => {
	query.removeEventListener('change', changed);
	document.removeEventListener('keydown', keydown);
});

async function leave(): Promise<void> {
	leaving.value = true;
	error.value   = '';

	try {
		await signOut();
		await router.push({ name: 'sign-in' });
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'Signing out failed.';
	} finally {
		leaving.value = false;
	}
}
</script>

<template>
	<div class="app" :class="{ 'is-collapsed': hidden && !narrow, 'is-narrow': narrow, 'is-open': open, 'is-focus': focusMode }">
		<a class="skip-link" href="#main">Skip to content</a>

		<div id="nav-drawer" ref="drawer" class="drawer" :inert="(narrow && !open) || focusMode">
			<nav class="railbar" aria-label="Sections">
				<a class="railbar__mark" :href="config.site.url" :title="`${config.site.name} (view the site)`">
					<span aria-hidden="true">{{ config.site.name.charAt(0) }}</span>
					<span class="visually-hidden">{{ config.site.name }} (view the site)</span>
				</a>
				<button
					v-for="item in areas"
					:key="item.key"
					type="button"
					class="railbar__section"
					:aria-current="area === item.key ? 'true' : undefined"
					:aria-expanded="area === item.key ? (narrow ? open : !hidden) : undefined"
					aria-controls="nav-panel"
					@click="choose(item.key)"
				>
					<AdminIcon :name="item.icon" />
					<span>{{ item.label }}</span>
				</button>
			</nav>

			<aside id="nav-panel" class="panel-nav" :aria-label="current?.label">
				<div class="panel-nav__head">
					<h2>{{ current?.label }}<span class="panel-nav__sub">{{ panelSub }}</span></h2>
					<button v-if="narrow" type="button" class="button button--ghost button--icon" @click="closeDrawer()">
						<AdminIcon name="x" />
						<span class="visually-hidden">Close menu</span>
					</button>
				</div>

				<nav class="panel-nav__nav" :aria-label="current?.label">
					<div v-for="group in sections[area]" :key="group.key" class="panel-nav__group">
						<p v-if="group.heading" :id="`nav-${group.key}`" class="panel-nav__heading">{{ group.heading }}</p>
						<ul :aria-labelledby="group.heading ? `nav-${group.key}` : undefined">
							<li v-for="link in group.links" :key="link.key">
								<RouterLink class="panel-nav__link" :class="{ 'is-current': link.current, 'panel-nav__link--two': link.detail }" :to="link.to">
									<TypeIcon v-if="link.type" :type="link.type" />
									<AdminIcon v-else :name="link.icon" />
									<span class="panel-nav__label">{{ link.label }}<span v-if="link.detail" class="panel-nav__detail">{{ link.detail }}</span></span>
								</RouterLink>
								<ul v-if="link.links?.length" class="panel-nav__nest">
									<li v-for="child in link.links" :key="child.key">
										<RouterLink class="panel-nav__link" :class="{ 'is-current': child.current }" :to="child.to">
											<TypeIcon v-if="child.type" :type="child.type" />
											<AdminIcon v-else :name="child.icon" />
											<span class="panel-nav__label">{{ child.label }}</span>
										</RouterLink>
									</li>
								</ul>
							</li>
						</ul>
					</div>
				</nav>
			</aside>
		</div>

		<div v-if="narrow && open" class="scrim" aria-hidden="true" @click="closeDrawer()" />

		<div class="work" :inert="narrow && open">
			<header v-if="!focusMode" class="bar">
				<button v-if="narrow" ref="menu" type="button" class="button button--ghost button--icon" aria-controls="nav-drawer" :aria-expanded="open" @click="openDrawer">
					<AdminIcon name="menu" />
					<span class="visually-hidden">Menu</span>
				</button>
				<nav class="bar__crumbs" aria-label="Where you are">
					<button type="button" class="bar__root" aria-controls="nav-panel" :title="`Show ${sectionLabel} in the panel`" @click="showSection(routeArea)">{{ sectionLabel }}</button>
					<template v-for="crumb in trail" :key="crumb.label">
						<span class="bar__sep" aria-hidden="true">/</span>
						<RouterLink class="bar__link" :to="crumb.to">{{ crumb.label }}</RouterLink>
					</template>
					<template v-if="screenCrumb ?? title">
						<span class="bar__sep" aria-hidden="true">/</span>
						<span class="bar__current" aria-current="page">{{ screenCrumb ?? title }}</span>
					</template>
				</nav>
				<button type="button" class="bar__search" aria-haspopup="dialog" @click="paletteOpen = true">
					<AdminIcon name="search" />
					<span class="bar__search-text">Search or jump to…</span>
					<kbd>⌘K</kbd>
					<span class="visually-hidden"> (search entries and commands)</span>
				</button>
				<a class="button button--small bar__view" :href="config.site.url" target="_blank" rel="noopener">
					<AdminIcon name="external-link" />
					<span>View site</span><span class="visually-hidden"> (new tab)</span>
				</a>
				<MenuButton button-class="account" :label="`Account: ${session.account?.displayName ?? ''}`">
					<template #button>
						<span aria-hidden="true">{{ initials(session.account?.displayName ?? '') }}</span>
					</template>
					<p class="account__who">
						<span>{{ session.account?.displayName }}</span>
						<span v-if="session.account && session.account.displayName !== session.account.username" class="account__username mono">{{ session.account.username }}</span>
						<span class="account__roles">{{ session.account?.roles.map((role) => role.label).join(', ') }}</span>
					</p>
					<RouterLink class="menu-item" :to="{ name: 'profile' }">
						<AdminIcon name="users" />Your Profile
					</RouterLink>
					<button type="button" class="menu-item" :disabled="leaving" @click="leave">
						<AdminIcon name="log-out" />{{ leaving ? 'Signing out…' : 'Sign out' }}
					</button>
				</MenuButton>
			</header>

			<p v-if="!online" class="offline" role="status">
				<AdminIcon name="triangle-alert" />
				You're offline. Changes you make stay in this browser, and you can save them once the connection is back.
			</p>

			<p v-if="error" class="notice notice--error bar-error" role="alert">{{ error }}</p>

			<!-- One element either way, so a screen that turns bleed on or off
			     itself (Your Profile, D-329) isn't mounted again. -->
			<main id="main" class="main" :class="{ 'main--bleed': bleed }">
				<div :class="bleed ? 'bleed' : 'wrap'">
					<slot />
				</div>
			</main>
		</div>

		<ToastHost />
		<CommandPalette v-if="paletteOpen" @close="paletteOpen = false" />
	</div>
</template>

<style scoped>
.app {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr);
	height: 100%;
}

.skip-link {
	position: absolute;
	top: 8px;
	left: 8px;
	z-index: 30;
	padding: 8px 12px;
	border-radius: var(--r-1);
	background: var(--surface);
	box-shadow: var(--shadow-2);
	transform: translateY(-200%);
}

.skip-link:focus {
	transform: none;
}

/* The section rail and its panel. */

.drawer {
	display: flex;
	min-height: 0;
}

.railbar {
	display: flex;
	flex: none;
	flex-direction: column;
	align-items: center;
	gap: var(--s-1);
	width: var(--railbar);
	padding: var(--s-3) 0;
	border-right: 1px solid var(--border);
	background: var(--surface);
}

.railbar__mark {
	display: grid;
	place-items: center;
	width: 34px;
	height: 34px;
	margin-bottom: var(--s-3);
	border-radius: var(--r-2);
	background: var(--fg);
	color: var(--bg);
	font-family: var(--font-display);
	font-weight: 600;
	text-decoration: none;
	text-transform: uppercase;
}

.railbar__mark:hover {
	color: var(--bg);
}

.railbar__section {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 6px;
	width: 60px;
	padding: 10px 2px 8px;
	border: 0;
	border-radius: var(--r-2);
	background: none;
	color: var(--fg-3);
	font-size: var(--text-2xs);
	font-weight: 500;
	line-height: 1.2;
	cursor: pointer;
}

.railbar__section :deep(svg) {
	width: 19px;
	height: 19px;
	stroke-width: 1.5;
}

.railbar__section:hover {
	background: var(--surface-2);
	color: var(--fg-2);
}

.railbar__section[aria-current="true"] {
	background: var(--accent-soft);
	color: var(--accent);
}

.panel-nav {
	display: flex;
	flex-direction: column;
	width: var(--rail);
	min-width: 0;
	min-height: 0;
	overflow: hidden;
	border-right: 1px solid var(--border);
	background: var(--surface);
	transition: width 160ms ease-out;
}

.is-collapsed .panel-nav {
	width: 0;
	border-right-width: 0;
}

.panel-nav__head {
	display: flex;
	flex: none;
	align-items: center;
	gap: var(--s-2);
	min-height: var(--bar);
	padding: 0 var(--s-4);
	border-bottom: 1px solid var(--border);
}

.panel-nav__head h2 {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	font-family: var(--font-display);
	font-size: var(--h2);
	font-weight: 600;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.panel-nav__sub {
	display: block;
	overflow: hidden;
	color: var(--fg-3);
	font-family: var(--font-ui);
	font-size: var(--text-xs);
	font-weight: 400;
	text-overflow: ellipsis;
	margin-top: 2px;
}

.panel-nav__nav {
	display: grid;
	flex: 1;
	align-content: start;
	gap: var(--s-5);
	min-width: var(--rail);
	padding: var(--s-3);
	overflow-y: auto;
}

.panel-nav__heading {
	padding: 0 var(--s-2) var(--s-2);
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

.panel-nav__nav ul {
	display: grid;
	gap: 3px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.panel-nav__link {
	position: relative;
	display: flex;
	align-items: center;
	gap: 11px;
	min-height: 36px;
	padding: 0 10px;
	border-radius: var(--r-1);
	color: var(--fg-2);
	font-weight: 500;
	text-decoration: none;
}

.panel-nav__link:hover {
	background: var(--surface-2);
	color: var(--fg);
}

.panel-nav__label {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* A shared taxonomy names what it groups on a second line. */

.panel-nav__link--two {
	padding-block: 7px;
}

.panel-nav__detail {
	display: block;
	overflow: hidden;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 400;
	text-overflow: ellipsis;
}

/* A type's own taxonomies sit under it, one level only. */

.panel-nav__nav .panel-nav__nest {
	position: relative;
	margin-top: 3px;
	padding-left: 16px;
}

.panel-nav__nest::before {
	content: "";
	position: absolute;
	top: 1px;
	bottom: 3px;
	left: 18px;
	width: 1px;
	background: var(--border);
}

.panel-nav__link[aria-current="page"],
.panel-nav__link.is-current {
	background: var(--accent-soft);
	color: var(--accent);
}

.panel-nav__link[aria-current="page"]::before,
.panel-nav__link.is-current::before {
	content: "";
	position: absolute;
	top: 7px;
	bottom: 7px;
	left: calc(-1 * var(--s-3));
	width: 3px;
	border-radius: 0 3px 3px 0;
	background: var(--accent);
}

/* Drawer: the rail and panel slide in together. */

.app.is-narrow {
	grid-template-columns: minmax(0, 1fr);
}

.is-narrow .drawer {
	position: fixed;
	inset: 0 auto 0 0;
	z-index: 20;
	max-width: calc(100% - 48px);
	box-shadow: var(--shadow-3);
	transform: translateX(-100%);
	visibility: hidden;
	transition: transform 180ms ease-out, visibility 0s linear 180ms;
}

.is-narrow .panel-nav {
	width: var(--rail);
	min-width: 0;
	flex-shrink: 1;
}

.is-narrow.is-open .drawer {
	transform: none;
	visibility: visible;
	transition: transform 180ms ease-out;
}

.scrim {
	position: fixed;
	inset: 0;
	z-index: 10;
	background: var(--fg);
	opacity: .3;
}

/* Focus mode leaves the work area alone. */

.app.is-focus {
	grid-template-columns: minmax(0, 1fr);
}

.is-focus .drawer {
	display: none;
}

/* Work area */

.work {
	display: flex;
	flex-direction: column;
	min-width: 0;
	min-height: 0;
}

.bar {
	display: flex;
	flex: none;
	height: var(--bar);
	align-items: center;
	gap: var(--s-2);
	padding: 0 var(--s-4);
	background: var(--surface);
	border-bottom: 1px solid var(--border);
}

.bar__crumbs {
	display: flex;
	flex: 1;
	align-items: center;
	gap: 8px;
	min-width: 0;
	white-space: nowrap;
}

.bar__root {
	overflow: hidden;
	padding: 0;
	border: 0;
	background: none;
	color: var(--fg-2);
	font: inherit;
	text-overflow: ellipsis;
	cursor: pointer;
}

.bar__root:hover {
	color: var(--fg);
	text-decoration: underline;
}

.bar__sep {
	color: var(--fg-3);
}

.bar__link {
	overflow: hidden;
	color: var(--fg-2);
	text-decoration: none;
	text-overflow: ellipsis;
}

.bar__link:hover {
	color: var(--fg);
	text-decoration: underline;
}

.bar__current {
	overflow: hidden;
	font-weight: 500;
	text-overflow: ellipsis;
}

.bar__search {
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 220px;
	height: var(--ctl);
	padding: 0 12px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--bg);
	color: var(--fg-3);
	cursor: pointer;
}

.bar__search:hover {
	border-color: var(--border-strong);
	color: var(--fg-2);
}

.bar__search :deep(svg) {
	width: 15px;
	height: 15px;
}

.bar__search-text {
	flex: 1;
	font-size: var(--text-sm);
	text-align: left;
}

.bar-error {
	margin: var(--s-3) var(--s-4) 0;
}

/* The account's menu. */

.bar :deep(.account) {
	display: grid;
	place-items: center;
	width: 29px;
	height: 29px;
	border: 0;
	border-radius: 50%;
	background: var(--surface-3);
	color: var(--fg-2);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: 0.02em;
	text-transform: uppercase;
	cursor: pointer;
}

.bar :deep(.account[aria-expanded="true"]) {
	background: var(--accent);
	color: var(--accent-fg);
}

.account__who {
	display: grid;
	padding: 10px 11px 12px;
	margin-bottom: 7px;
	border-bottom: 1px solid var(--border);
	font-weight: 500;
}

.account__username,
.account__roles {
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 400;
}

.offline {
	display: flex;
	flex: none;
	align-items: center;
	gap: 11px;
	padding: 11px var(--s-5);
	border-bottom: 1px solid var(--warn-dot);
	background: var(--warn-soft);
	color: var(--warn);
	font-size: var(--text-sm);
}

.offline svg {
	flex: none;
	width: 16px;
	height: 16px;
}

/* Positioned, so an absolute descendant (a visually hidden label) is
   placed in the scroller, not against the body, where one far down a
   long screen would make the whole document scroll. */
.main {
	position: relative;
	flex: 1;
	min-height: 0;
	overflow: auto;
}

/* A screen that fills the work area (the editor) scrolls itself. */

.main--bleed {
	display: flex;
	flex-direction: column;
	overflow: hidden;
}

.bleed {
	display: contents;
}

.wrap {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: var(--s-4);
	max-width: var(--work-max);
	margin: 0 auto;
	padding: var(--s-6) var(--s-6) 96px;
}

/* A page's header stands further from what follows than sections do
   from each other. */
.wrap > :deep(.page-header) {
	margin-bottom: calc(var(--s-6) - var(--s-4));
}

@media (width <= 640px) {
	.wrap {
		gap: var(--s-3);
		padding: var(--s-5) var(--s-4) 80px;
	}

	.wrap > :deep(.page-header) {
		margin-bottom: calc(var(--s-5) - var(--s-3));
	}

	.bar__root,
	.bar__root + .bar__sep {
		display: none;
	}

	.bar__search {
		min-width: 0;
		width: var(--ctl);
		justify-content: center;
		padding: 0;
	}

	.bar__search-text,
	.bar__search kbd {
		display: none;
	}

	.bar__view span:not(.visually-hidden) {
		position: absolute;
		width: 1px;
		height: 1px;
		overflow: hidden;
		clip-path: inset(50%);
	}
}
</style>
