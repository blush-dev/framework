<script setup lang="ts">
/**
 * The signed-in layout (D-231, D-244): a labeled section rail (Home,
 * Content, Structure, Extend, Users, Settings; D-326, D-704), the panel beside it with only the active section's
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
 * this one (`Content / Posts / Editing`, `Structure / Content Types /
 * Pages`; D-317): the section crumb shows its panel, and is a rail
 * toggle when the panel already shows it (D-367); the others are ways
 * back. The account's menu is in the top bar,
 * with the command palette's button (⌘K anywhere, D-248). While the browser is offline, a bar under the top bar says so. The editor's focus mode drops everything but the work
 * area.
 */

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter, type RouteLocationRaw } from 'vue-router';
import { errorMessage, type ContentTypeSummary } from '../api';
import { useAction } from '../action';
import { config } from '../config';
import { online } from '../connection';
import type { IconName } from '../icons';
import { focusMode, lastVisits, screenCrumb, screenTitle, screenTrail } from '../screen';
import { loadCounts, navCounts } from '../counts';
import { can, canAnyType, canType, savePreferences, session, signOut, usesMedia } from '../session';
import { toast } from '../toast';
import { formatNumber } from '../format';
import { t } from '../messages';
import { contentMenu, profileType, currentType, loadTypes, typeIcon, types } from '../types';
import AdminAvatar from './AdminAvatar.vue';
import AdminIcon from './AdminIcon.vue';
import CommandPalette from './CommandPalette.vue';
import MenuButton from './MenuButton.vue';
import ConfirmHost from './ConfirmHost.vue';
import ToastHost from './ToastHost.vue';
import TypeIcon from './TypeIcon.vue';

type Area = 'home' | 'content' | 'structure' | 'extend' | 'people' | 'settings';

interface NavLink {
	key: string;
	label: string;
	icon: IconName;
	to: RouteLocationRaw;
	// A content type's link, shown with the type's own icon.
	type?: ContentTypeSummary;
	current?: boolean;
	// Links nested under this one, such as the types under a type.
	links?: NavLink[];
	// Site Health's problems (D-545); lists aren't counted (D-800).
	count?: number;
	// Its id as a shortcut (D-547), and its name there when its own
	// needs its section to make sense ("Reading Settings").
	pin?: string;
	pinLabel?: string;
}

interface NavGroup {
	key: string;
	heading?: string;
	links: NavLink[];
}

const route  = useRoute();
const router = useRouter();

const { busy: leaving, error, run } = useAction();

// Content types come from the server; the menu works without them.
onMounted(() => {
	if (canAnyType('edit')) {
		loadTypes().catch(() => undefined);
	}

	void loadCounts();
});

// A screen is where things are made and removed, so leaving one counts
// again.
watch(() => route.fullPath, () => {
	void loadCounts();
});

// A detail screen marks its list (`meta.parent`).
const screen = (name: string, label: string, icon: IconName): NavLink => ({ key: name, label, icon, to: { name }, current: route.meta.parent === name, pin: name });

/**
 * Each section's links, in groups (D-241, D-244). **Home**: the admin's
 * own screens (the Dashboard, Site Health, and Tools); its
 * shortcuts are drawn after them (`shortcuts`). **Content**: the content
 * types in the site's order, each with the types that sit under it
 * (`contentMenu()`, D-726), and Media. **Users** (D-326, D-354):
 * Your Account, Accounts, Profiles, and Roles (D-353, D-358).
 * **Structure** (D-704): content types, relationships (D-610), fields,
 * and menus. **Extend**: Themes, Plugins, and Icon Packs (D-327, D-378,
 * D-380). **Settings** (D-325, D-704): its screens, with Redirects after
 * Addresses and Search (D-686).
 * Links the account can't use aren't shown.
 */
const sections = computed<Record<Area, NavGroup[]>>(() => {
	const home: NavLink[] = [screen('dashboard', t('admin_layout.nav_dashboard', 'Dashboard'), 'gauge')];

	// With what its last report found (D-545).
	if (can('site.health')) {
		home.push({ ...screen('health', t('admin_layout.nav_health', 'Site Health'), 'heart-pulse'), count: navCounts.value?.health || undefined });
	}

	// Tools, with an action the account may run, the jobs, or the log
	// (D-540, D-621).
	if ((navCounts.value?.tools ?? 0) > 0 || can('site.logs') || can('site.jobs')) {
		home.push(screen('tools', t('admin_layout.nav_tools', 'Tools'), 'wrench'));
	}

	// Your Account is your own account's screen (D-371), so it's current
	// there, and Accounts isn't.
	const own       = session.account?.username ?? '';
	const onOwn     = route.name === 'account' && route.params.username === own;
	const yourLink: NavLink = { key: 'profile', label: t('admin_layout.nav_your_account', 'Your Account'), icon: 'circle-user-round', to: { name: 'account', params: { username: own } }, current: onOwn, pin: 'account' };

	// A Settings screen (D-325).
	const settingsScreen = (key: string, label: string, icon: IconName): NavLink => ({ key: `settings-${key}`, label, icon, to: { name: 'settings', params: { screen: key } }, current: false, pin: `settings:${key}`, pinLabel: key === 'general' ? t('admin_layout.pin_settings', 'Settings') : t('admin_layout.pin_screen_settings', '{label} Settings', { label }) });

	const inEntries = route.meta.section === 'entries';
	const link = (type: ContentTypeSummary): NavLink => ({
		key: type.name,
		label: type.labels.menu,
		icon: typeIcon(type),
		type,
		to: { name: 'type', params: { type: type.name } },
		current: inEntries && currentType.value === type.name,
		pin: `type:${type.name}`
	});

	// In the site's order (D-726); only the types the account edits
	// entries of (D-359).
	const content = contentMenu(types.value.filter((type) => canType(type.name, 'edit'))).map(({ type, under }) => ({ ...link(type), links: under.map(link) }));
	const library = usesMedia() ? [screen('media', t('admin_layout.nav_media', 'Media'), 'image')] : [];

	const structure = [
		...(can('site.settings') ? [screen('types', t('admin_layout.nav_types', 'Content Types'), 'layers'), screen('relations', t('admin_layout.nav_relations', 'Relationships'), 'workflow'), screen('workflows', t('admin_layout.nav_workflows', 'Workflows'), 'route'), screen('fields', t('admin_layout.nav_fields', 'Fields'), 'group')] : []),
		// Menus, with their own capability.
		...(can('menus.edit') ? [screen('menus', t('admin_layout.nav_menus', 'Menus'), 'menu')] : [])
	];
	// Redirects (D-686) after Addresses and Search, with its own capability.
	const redirects = can('site.redirects') ? [screen('redirects', t('admin_layout.nav_redirects', 'Redirects'), 'corner-down-right')] : [];
	const settings  = can('site.settings')
		? [settingsScreen('general', t('admin_layout.settings_general', 'General'), 'settings-2'), settingsScreen('reading', t('admin_layout.settings_reading', 'Reading'), 'book-open'), settingsScreen('writing', t('admin_layout.settings_writing', 'Writing'), 'pen-line'), settingsScreen('media', t('admin_layout.settings_media', 'Media'), 'image'), settingsScreen('email', t('admin_layout.settings_email', 'Email'), 'mail'), settingsScreen('search', t('admin_layout.settings_search', 'Addresses and Search'), 'globe'), ...redirects, settingsScreen('ai', t('admin_layout.settings_ai', 'AI'), 'bot'), settingsScreen('api', t('admin_layout.settings_api', 'API'), 'braces'), settingsScreen('system', t('admin_layout.settings_system', 'System'), 'settings')]
		: redirects;
	// Each kind of extension needs seeing it (D-389).
	const extensions = [
		...(can('extensions.themes.view') ? [screen('themes', t('admin_layout.nav_themes', 'Themes'), 'paintbrush')] : []),
		...(can('extensions.plugins.view') ? [screen('plugins', t('admin_layout.nav_plugins', 'Plugins'), 'plug')] : []),
		...(can('extensions.icon-packs.view') ? [screen('icon-packs', t('admin_layout.nav_icon_packs', 'Icon Packs'), 'shapes')] : []),
		...(can('extensions.admin-themes.view') ? [screen('admin-themes', t('admin_layout.nav_admin_themes', 'Admin Themes'), 'swatch-book')] : [])
	];
	// Accounts and Profiles are two lists (D-353): who can sign in, and
	// who's credited on the site. A profile's screens, and its type's
	// list and editor, mark Profiles.
	const profiles  = types.value.find((type) => type.name === profileType.value);
	const profileLink: NavLink[] = profiles && canType(profiles.name, 'edit') ? [{
		key: 'profiles',
		label: profiles.labels.menu,
		icon: 'user-round',
		to: { name: 'type', params: { type: profiles.name } },
		current: route.meta.parent === 'profiles' || (inEntries && currentType.value === profiles.name),
		pin: 'profiles'
	}] : [];
	const people    = [
		yourLink,
		...(can('accounts.view') ? [{ ...screen('accounts', t('admin_layout.nav_accounts', 'Accounts'), 'key-round'), current: route.meta.parent === 'accounts' && !onOwn }] : []),
		...profileLink,
		...(can('accounts.view') ? [screen('roles', t('admin_layout.nav_roles', 'Roles'), 'shield')] : [])
	];

	const groups = (list: NavGroup[]): NavGroup[] => list.filter((group) => group.links.length > 0);

	return {
		home: groups([{ key: 'home', links: home }]),
		content: groups([{ key: 'types', links: content }, { key: 'library', heading: t('admin_layout.group_library', 'Library'), links: library }]),
		people: groups([{ key: 'people', links: people }]),
		structure: groups([{ key: 'structure', links: structure }]),
		extend: groups([{ key: 'extensions', links: extensions }]),
		settings: groups([{ key: 'settings', links: settings }])
	};
});

/*
 * A link with links under it has a chevron that shows or hides them
 * (D-800), open until closed. What's closed is remembered in this
 * browser for each account; storage may be off. Going to a link under a
 * closed one opens it.
 */
const closedKey = computed(() => `blush-admin-nav-closed:${session.account?.username ?? ''}`);

function storedClosed(): string[] {
	try {
		const value: unknown = JSON.parse(localStorage.getItem(closedKey.value) ?? '[]');

		return Array.isArray(value) ? value.filter((key): key is string => typeof key === 'string') : [];
	} catch {
		return [];
	}
}

const closed = ref<string[]>(storedClosed());

watch(closedKey, () => {
	closed.value = storedClosed();
});

watch(closed, (value) => {
	try {
		localStorage.setItem(closedKey.value, JSON.stringify(value));
	} catch {
		// Not remembered; nothing else depends on it.
	}
});

const isOpen = (link: NavLink): boolean => !closed.value.includes(link.key);

function toggleLinks(link: NavLink): void {
	closed.value = isOpen(link) ? [...closed.value, link.key] : closed.value.filter((key) => key !== link.key);
}

watch(sections, (value) => {
	const opened = Object.values(value).flat().flatMap((group) => group.links).filter((link) => !isOpen(link) && link.links?.some((child) => child.current));

	if (opened.length > 0) {
		closed.value = closed.value.filter((key) => !opened.some((link) => link.key === key));
	}
}, { immediate: true });

/*
 * Shortcuts (D-547, from the Home sketch): screens the account pins in
 * Home's panel, in its own order, kept with its preferences, so they
 * follow it to any device. Edit turns the list into rows to move and
 * remove, and adds **Add a Shortcut**, offering every screen the panel
 * shows the account that isn't pinned; **Done** saves them, once
 * (D-549). Without a choice of its own, an
 * account has Your Account alone (D-548). A pinned screen the account
 * can no longer use isn't shown.
 */
const DEFAULT_SHORTCUTS = ['account'];

const editingShortcuts = ref(false);

// While editing, the shortcuts as they're being arranged; saved on Done.
const draft = ref<string[] | null>(null);

// Every screen that can be pinned, in the panel's order, once each.
const pinnable = computed<NavLink[]>(() => {
	const found = new Map<string, NavLink>();
	const visit = (links: NavLink[]): void => links.forEach((item) => {
		if (item.pin && !found.has(item.pin)) {
			found.set(item.pin, item);
		}

		visit(item.links ?? []);
	});

	(['home', 'content', 'structure', 'extend', 'people', 'settings'] as Area[]).forEach((key) => sections.value[key].forEach((group) => visit(group.links)));

	return [...found.values()];
});

const pinnedIds = computed(() => draft.value ?? session.account?.preferences.shortcuts ?? DEFAULT_SHORTCUTS);

// The pinned screens, as links of their own.
const shortcuts = computed<NavLink[]>(() => pinnedIds.value
	.map((id) => pinnable.value.find((item) => item.pin === id))
	.filter((item): item is NavLink => item !== undefined)
	.map((item) => ({ ...item, key: `shortcut-${item.pin ?? ''}`, label: item.pinLabel ?? item.label, current: false, links: undefined, count: undefined })));

const unpinned = computed(() => pinnable.value.filter((item) => !pinnedIds.value.includes(item.pin ?? '')));

// The shown shortcuts' ids, so screens no longer shown drop out.
const shownIds = (): string[] => shortcuts.value.map((item) => item.pin ?? '');

/**
 * Starts editing, or, on Done, saves the shortcuts once, when they
 * changed, and says so (D-549).
 */
async function toggleShortcuts(): Promise<void> {
	if (!editingShortcuts.value) {
		draft.value = shownIds();
		editingShortcuts.value = true;

		return;
	}

	const ids = shownIds();
	const saved = session.account?.preferences.shortcuts ?? DEFAULT_SHORTCUTS;

	editingShortcuts.value = false;

	try {
		if (ids.join('\n') !== saved.join('\n')) {
			await savePreferences({ shortcuts: ids });
			toast(t('admin_layout.shortcuts_updated', 'Shortcuts updated'));
		}
	} catch (caught) {
		toast(errorMessage(caught, t('admin_layout.shortcuts_failed', 'The shortcuts couldn\'t be saved.')), { kind: 'danger' });
	} finally {
		draft.value = null;
	}
}

function pinShortcut(item: NavLink): void {
	draft.value = [...shownIds(), item.pin ?? ''];
}

function unpinShortcut(item: NavLink): void {
	draft.value = shownIds().filter((id) => id !== item.pin);
}

function moveShortcut(index: number, by: -1 | 1): void {
	const ids = shownIds();
	const [moved] = ids.splice(index, 1);

	if (moved !== undefined) {
		ids.splice(index + by, 0, moved);
		draft.value = ids;
	}
}

// The rail's sections; one with nothing in it for this account is left out.
const areas = computed(() => ([
	{ key: 'home', label: t('admin_layout.area_home', 'Home'), icon: 'house' },
	{ key: 'content', label: t('admin_layout.area_content', 'Content'), icon: 'file-text' },
	{ key: 'structure', label: t('admin_layout.area_structure', 'Structure'), icon: 'blocks' },
	{ key: 'extend', label: t('admin_layout.area_extend', 'Extend'), icon: 'package' },
	{ key: 'people', label: t('admin_layout.area_people', 'Users'), icon: 'user' },
	{ key: 'settings', label: t('admin_layout.area_settings', 'Settings'), icon: 'settings' }
] as const).filter((area) => sections.value[area.key].length > 0));

const routeArea = computed<Area>(() => {
	const area = route.meta.area;

	// Profiles are listed with people, so their screens are too.
	if (route.meta.section === 'entries' && currentType.value !== null && currentType.value === profileType.value) {
		return 'people';
	}

	return area === 'content' || area === 'structure' || area === 'extend' || area === 'people' || area === 'settings' ? area : 'home';
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

		return count === 0 ? t('admin_layout.sub_content_empty', 'Entries and media') : t('admin_layout.sub_content', '{count, plural, one {# content type} other {# content types}}', { count });
	}

	if (area.value === 'people') {
		return t('admin_layout.sub_people', 'Accounts, profiles, and roles');
	}

	if (area.value === 'structure') {
		return t('admin_layout.sub_structure', 'Types, relationships, fields, and menus');
	}

	if (area.value === 'extend') {
		return t('admin_layout.sub_extend', 'Themes, plugins, and icon packs');
	}

	return area.value === 'settings' ? t('admin_layout.sub_settings', 'How the site reads and runs') : config.site.name;
});

/**
 * A rail button: the section already shown closes its panel (or the
 * drawer); any other, or a closed panel, opens it on that section.
 */
function choose(key: Area): void {
	if (showing(key)) {
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

// Whether the panel (or the drawer) is showing this section.
function showing(key: Area): boolean {
	return key === area.value && (narrow.value ? open.value : !hidden.value);
}

/**
 * The trail's section crumb: shows that section's panel, or, when it's
 * already shown, closes it like its rail button (D-367).
 */
function showSection(key: Area): void {
	if (showing(key)) {
		choose(key);

		return;
	}

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

	// Your own account is Your Account, not one of the Accounts (D-371).
	if (route.name === 'account' && route.params.username === session.account?.username) {
		return [];
	}

	const parent = typeof route.meta.parent === 'string' ? router.getRoutes().find((item) => item.name === route.meta.parent) : undefined;
	const label  = parent?.meta.title;

	return parent !== undefined && typeof label === 'string' ? [{ label, to: { name: route.meta.parent as string } }] : [];
});

// A crumb's address as you last saw it, so `Posts` keeps its status tab.
function returnTo(to: RouteLocationRaw): RouteLocationRaw {
	return lastVisits.get(router.resolve(to).path) ?? to;
}

const sectionLabel = computed(() => ({ home: t('admin_layout.area_home', 'Home'), content: t('admin_layout.area_content', 'Content'), structure: t('admin_layout.area_structure', 'Structure'), extend: t('admin_layout.area_extend', 'Extend'), people: t('admin_layout.area_people', 'Users'), settings: t('admin_layout.area_settings', 'Settings') })[routeArea.value]);
const bleed = computed(() => route.meta.bleed === true);
const wide  = computed(() => route.meta.wide === true);
// A screen read top to bottom, like the dashboard, keeps a narrower
// column (the Home sketch's).
const narrowColumn = computed(() => route.meta.narrow === true);

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
const writing = computed(() => route.meta.section === 'entries' && route.meta.bleed === true);
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
	await run(t('admin_layout.sign_out_failed', 'Signing out failed.'), async () => {
		await signOut();
		await router.push({ name: 'sign-in' });
	});
}
</script>

<template>
	<div class="app" :class="{ 'is-collapsed': hidden && !narrow, 'is-narrow': narrow, 'is-open': open, 'is-focus': focusMode }">
		<a class="skip-link" href="#main">{{ t('admin_layout.skip', 'Skip to content') }}</a>

		<div id="nav-drawer" ref="drawer" class="drawer" :inert="(narrow && !open) || focusMode">
			<nav class="railbar" :aria-label="t('admin_layout.sections', 'Sections')">
				<a class="railbar__mark" :href="config.site.url" :title="t('admin_layout.view_site_title', '{site} (view the site)', { site: config.site.name })">
					<span aria-hidden="true">{{ config.site.name.charAt(0) }}</span>
					<span class="visually-hidden">{{ t('admin_layout.view_site_title', '{site} (view the site)', { site: config.site.name }) }}</span>
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
						<span class="visually-hidden">{{ t('admin_layout.close_menu', 'Close menu') }}</span>
					</button>
				</div>

				<nav class="panel-nav__nav" :aria-label="current?.label">
					<div v-for="group in sections[area]" :key="group.key" class="panel-nav__group">
						<p v-if="group.heading" :id="`nav-${group.key}`" class="eyebrow panel-nav__heading">{{ group.heading }}</p>
						<ul :aria-labelledby="group.heading ? `nav-${group.key}` : undefined">
							<li v-for="link in group.links" :key="link.key">
								<RouterLink class="panel-nav__link" :class="{ 'is-current': link.current, 'has-links': link.links?.length }" :to="link.to">
									<TypeIcon v-if="link.type" :type="link.type" />
									<AdminIcon v-else :name="link.icon" />
									<span class="panel-nav__label">{{ link.label }}</span>
									<span v-if="link.count" class="panel-nav__count"><span class="visually-hidden">, </span>{{ formatNumber(link.count) }}</span>
								</RouterLink>
								<template v-if="link.links?.length">
									<button
										type="button"
										class="twist panel-nav__twist"
										:aria-expanded="isOpen(link)"
										:aria-controls="`nav-under-${link.key}`"
										:aria-label="isOpen(link) ? t('admin_layout.hide_links', 'Hide the links under {label}', { label: link.label }) : t('admin_layout.show_links', 'Show the links under {label}', { label: link.label })"
										@click="toggleLinks(link)"
									>
										<AdminIcon name="chevron-right" />
									</button>
									<ul v-show="isOpen(link)" :id="`nav-under-${link.key}`" class="panel-nav__nest">
										<li v-for="child in link.links" :key="child.key">
											<RouterLink class="panel-nav__link" :class="{ 'is-current': child.current }" :to="child.to">
												<TypeIcon v-if="child.type" :type="child.type" />
												<AdminIcon v-else :name="child.icon" />
												<span class="panel-nav__label">{{ child.label }}</span>
											</RouterLink>
										</li>
									</ul>
								</template>
							</li>
						</ul>
					</div>

					<div v-if="area === 'home'" class="panel-nav__group">
						<div class="panel-nav__heading-row">
							<p id="nav-shortcuts" class="eyebrow panel-nav__heading">{{ t('admin_layout.shortcuts', 'Shortcuts') }}</p>
							<button type="button" class="panel-nav__edit" :aria-pressed="editingShortcuts" @click="toggleShortcuts">{{ editingShortcuts ? t('common.done', 'Done') : t('common.edit', 'Edit') }}<span class="visually-hidden">{{ t('admin_layout.shortcuts_hidden', ' shortcuts') }}</span></button>
						</div>
						<ul v-if="shortcuts.length" aria-labelledby="nav-shortcuts">
							<li v-for="(link, index) in shortcuts" :key="link.key">
								<div v-if="editingShortcuts" class="panel-nav__link is-editing">
									<TypeIcon v-if="link.type" :type="link.type" />
									<AdminIcon v-else :name="link.icon" />
									<span class="panel-nav__label">{{ link.label }}</span>
									<span class="panel-nav__tools">
										<button type="button" class="panel-nav__tool" :disabled="index === 0" :aria-label="t('admin_layout.move_up', 'Move {label} up', { label: link.label })" @click="moveShortcut(index, -1)"><AdminIcon name="chevron-up" /></button>
										<button type="button" class="panel-nav__tool" :disabled="index === shortcuts.length - 1" :aria-label="t('admin_layout.move_down', 'Move {label} down', { label: link.label })" @click="moveShortcut(index, 1)"><AdminIcon name="chevron-down" /></button>
										<button type="button" class="panel-nav__tool panel-nav__tool--remove" :aria-label="t('admin_layout.remove_shortcut', 'Remove {label}', { label: link.label })" @click="unpinShortcut(link)"><AdminIcon name="x" /></button>
									</span>
								</div>
								<RouterLink v-else class="panel-nav__link" :to="link.to">
									<TypeIcon v-if="link.type" :type="link.type" />
									<AdminIcon v-else :name="link.icon" />
									<span class="panel-nav__label">{{ link.label }}</span>
								</RouterLink>
							</li>
						</ul>
						<p v-else class="panel-nav__empty">{{ t('admin_layout.no_shortcuts', 'Nothing pinned yet.') }}</p>
						<div v-if="editingShortcuts && unpinned.length" class="panel-nav__add">
							<MenuButton button-class="panel-nav__add-button" align="start" floating>
								<template #button><AdminIcon name="plus" /><span class="panel-nav__label">{{ t('admin_layout.add_shortcut', 'Add a Shortcut') }}</span></template>
								<button v-for="item in unpinned" :key="item.pin" type="button" class="menu-item" @click="pinShortcut(item)">
									<TypeIcon v-if="item.type" :type="item.type" />
									<AdminIcon v-else :name="item.icon" />
									{{ item.pinLabel ?? item.label }}
								</button>
							</MenuButton>
						</div>
					</div>
				</nav>
			</aside>
		</div>

		<div v-if="narrow && open" class="scrim" aria-hidden="true" @click="closeDrawer()" />

		<div class="work" :inert="narrow && open">
			<header v-if="!focusMode" class="bar">
				<button v-if="narrow" ref="menu" type="button" class="button button--ghost button--icon" aria-controls="nav-drawer" :aria-expanded="open" @click="openDrawer">
					<AdminIcon name="menu" />
					<span class="visually-hidden">{{ t('admin_layout.menu', 'Menu') }}</span>
				</button>
				<nav class="bar__crumbs" :aria-label="t('admin_layout.crumbs', 'Where you are')">
					<button type="button" class="bar__root" aria-controls="nav-panel" :aria-expanded="showing(routeArea)" :title="showing(routeArea) ? t('admin_layout.hide_panel', 'Hide the panel') : t('admin_layout.show_panel', 'Show {section} in the panel', { section: sectionLabel })" @click="showSection(routeArea)">{{ sectionLabel }}</button>
					<template v-for="crumb in trail" :key="crumb.label">
						<span class="bar__sep" aria-hidden="true">/</span>
						<RouterLink class="bar__link" :to="returnTo(crumb.to)">{{ crumb.label }}</RouterLink>
					</template>
					<template v-if="screenCrumb ?? title">
						<span class="bar__sep" aria-hidden="true">/</span>
						<span class="bar__current" aria-current="page">{{ screenCrumb ?? title }}</span>
					</template>
				</nav>
				<button type="button" class="bar__search" aria-haspopup="dialog" @click="paletteOpen = true">
					<AdminIcon name="search" />
					<span class="bar__search-text">{{ t('admin_layout.search', 'Search or jump to…') }}</span>
					<kbd>⌘K</kbd>
					<span class="visually-hidden">{{ t('admin_layout.search_hidden', ' (search entries and commands)') }}</span>
				</button>
				<a class="button button--small bar__view" :href="config.site.url" target="_blank" rel="noopener">
					<AdminIcon name="external-link" />
					<span>{{ t('admin_layout.view_site', 'View Site') }}</span><span class="visually-hidden">{{ t('admin_layout.new_tab', ' (new tab)') }}</span>
				</a>
				<MenuButton button-class="account" :label="t('admin_layout.account', 'Account: {name}', { name: session.account?.displayName ?? '' })">
					<template #button>
						<AdminAvatar :name="session.account?.displayName ?? ''" :image="session.account?.avatar" />
					</template>
					<p class="account__who">
						<span>{{ session.account?.displayName }}</span>
						<span v-if="session.account && session.account.displayName !== session.account.username" class="account__username mono">{{ session.account.username }}</span>
						<span class="account__roles">{{ session.account?.roles.map((role) => role.label).join(', ') }}</span>
					</p>
					<RouterLink class="menu-item" :to="{ name: 'account', params: { username: session.account?.username ?? '' } }">
						<AdminIcon name="circle-user-round" />{{ t('admin_layout.nav_your_account', 'Your Account') }}
					</RouterLink>
					<button type="button" class="menu-item" :disabled="leaving" @click="leave">
						<AdminIcon name="log-out" />{{ leaving ? t('admin_layout.signing_out', 'Signing out…') : t('admin_layout.sign_out', 'Sign out') }}
					</button>
				</MenuButton>
			</header>

			<p v-if="!online" class="offline" role="status">
				<AdminIcon name="triangle-alert" />
				{{ t('admin_layout.offline', 'You\'re offline. Changes you make stay in this browser, and you can save them once the connection is back.') }}
			</p>

			<p v-if="error" class="notice notice--error bar-error" role="alert">{{ error }}</p>

			<!-- One element either way, as each route's `meta.bleed` says. -->
			<main id="main" class="main" :class="{ 'main--bleed': bleed }">
				<div :class="bleed ? 'bleed' : ['wrap', { 'wrap--wide': wide, 'wrap--narrow': narrowColumn }]">
					<slot />
				</div>
			</main>
		</div>

		<ToastHost />
		<ConfirmHost />
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
	border-radius: var(--border-radius-small);
	background: var(--color-surface);
	box-shadow: var(--shadow-2);
	transform: translateY(-200%);
}

.skip-link:focus {
	transform: none;
}

/* The section rail and its panel. With the top bar, they're the chrome
   (D-878), drawn in its colors, and so is every control in them; menus
   opened from it keep the page's. */

.drawer {
	display: flex;
	min-height: 0;
}

.drawer :focus-visible,
.bar :focus-visible {
	outline-color: var(--color-chrome-accent);
}

.drawer :deep(.menu-button__list :focus-visible),
.bar :deep(.menu-button__list :focus-visible) {
	outline-color: var(--color-accent);
}

.railbar {
	display: flex;
	flex: none;
	flex-direction: column;
	align-items: center;
	gap: var(--s-1);
	width: var(--railbar);
	padding: var(--s-3) 0;
	border-right: 1px solid var(--color-chrome-border);
	background: var(--color-chrome);
	color: var(--color-chrome-text);
}

.railbar__mark {
	display: grid;
	place-items: center;
	width: 34px;
	height: 34px;
	margin-bottom: var(--s-3);
	border-radius: var(--border-radius-medium);
	background: var(--color-chrome-text);
	color: var(--color-chrome);
	font-family: var(--font-family-display);
	font-weight: var(--font-weight-display);
	text-decoration: none;
	text-transform: uppercase;
}

.railbar__mark:hover {
	color: var(--color-chrome);
}

.railbar__section {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 6px;
	width: 60px;
	padding: 10px 2px 8px;
	border: 0;
	border-radius: var(--border-radius-medium);
	background: none;
	color: var(--color-chrome-text-muted);
	font-size: var(--font-size-xxsmall);
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
	background: var(--color-chrome-subtle);
	color: var(--color-chrome-text-secondary);
}

.railbar__section[aria-current="true"] {
	background: var(--color-chrome-accent-subtle);
	color: var(--color-chrome-accent);
}

.panel-nav {
	display: flex;
	flex-direction: column;
	width: var(--rail);
	min-width: 0;
	min-height: 0;
	overflow: hidden;
	border-right: 1px solid var(--color-chrome-border);
	background: var(--color-chrome);
	color: var(--color-chrome-text);
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
	border-bottom: 1px solid var(--color-chrome-border);
}

.panel-nav__head h2 {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	font-family: var(--font-family-display);
	font-size: var(--font-size-panel-title);
	font-weight: var(--font-weight-display);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.panel-nav__sub {
	display: block;
	overflow: hidden;
	color: var(--color-chrome-text-muted);
	font-family: var(--font-family-ui);
	font-size: var(--font-size-xsmall);
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
	color: var(--color-chrome-text-muted);
	font-size: var(--font-size-xsmall);
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
	border-radius: var(--border-radius-small);
	color: var(--color-chrome-text-secondary);
	font-weight: 500;
	text-decoration: none;
}

.panel-nav__link:hover {
	background: var(--color-chrome-subtle);
	color: var(--color-chrome-text);
}

.panel-nav__label {
	flex: 1 1 auto;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* Site Health's problems (D-545), at the link's end; lists aren't
   counted (D-800). */

.panel-nav__count {
	flex: none;
	color: var(--color-chrome-text-muted);
	font-family: var(--font-mono);
	font-size: var(--font-size-xsmall);
	font-weight: 400;
}

/* Shortcuts (D-547): Edit beside the heading, and while editing, rows
   with their tools in place of links. */
.panel-nav__heading-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding-bottom: var(--s-2);
}

.panel-nav__heading-row .panel-nav__heading {
	padding-bottom: 0;
}

.panel-nav__edit {
	margin-right: 4px;
	padding: 2px 6px;
	border: 0;
	border-radius: var(--border-radius-small);
	background: none;
	color: var(--color-chrome-text-muted);
	font: inherit;
	font-size: var(--font-size-xsmall);
	font-weight: 600;
	letter-spacing: .06em;
	text-transform: uppercase;
	cursor: pointer;
}

.panel-nav__edit:hover {
	background: var(--color-chrome-subtle);
	color: var(--color-chrome-text-secondary);
}

.panel-nav__edit[aria-pressed="true"] {
	color: var(--color-chrome-accent);
}

.panel-nav__link.is-editing {
	padding-right: 4px;
}

.panel-nav__link.is-editing:hover {
	background: none;
}

.panel-nav__tools {
	display: flex;
	flex: none;
	gap: 1px;
	margin-left: auto;
}

.panel-nav__tool {
	display: grid;
	place-items: center;
	width: 24px;
	height: 24px;
	padding: 0;
	border: 0;
	border-radius: var(--border-radius-small);
	background: none;
	color: var(--color-chrome-text-muted);
	cursor: pointer;
}

.panel-nav__tool:hover:not(:disabled) {
	background: var(--color-chrome-strong);
	color: var(--color-chrome-text);
}

.panel-nav__tool--remove:hover:not(:disabled) {
	background: var(--color-danger-subtle);
	color: var(--color-danger);
}

.panel-nav__tool:disabled {
	cursor: default;
	opacity: .35;
}

.panel-nav__tool .icon {
	width: 14px;
	height: 14px;
}

.panel-nav__empty {
	margin: 0;
	padding: var(--s-1) 10px;
	color: var(--color-chrome-text-muted);
	font-size: var(--font-size-small);
}

.panel-nav__add {
	margin-top: 3px;
}

.panel-nav__add :deep(.panel-nav__add-button) {
	display: flex;
	align-items: center;
	gap: 11px;
	width: 100%;
	min-height: 36px;
	padding: 0 10px;
	border: 0;
	border-radius: var(--border-radius-small);
	background: none;
	color: var(--color-chrome-text-muted);
	font: inherit;
	font-weight: 500;
	text-align: left;
	cursor: pointer;
}

.panel-nav__add :deep(.panel-nav__add-button:hover),
.panel-nav__add :deep(.panel-nav__add-button[aria-expanded="true"]) {
	background: var(--color-chrome-subtle);
	color: var(--color-chrome-text);
}

.panel-nav__add :deep(.menu-button) {
	width: 100%;
}

/* The types under a type sit under it, one level only. */

/* The chevron of a link with links under it (D-800), over the link's
   end, which keeps its room. */
.panel-nav__nav li {
	position: relative;
}

.panel-nav__link.has-links {
	padding-right: 34px;
}

.panel-nav__twist {
	position: absolute;
	top: 9px;
	right: 8px;
	margin-top: 0;
	color: var(--color-chrome-text-muted);
}

.panel-nav__twist:hover {
	background: var(--color-chrome-strong);
	color: var(--color-chrome-text);
}

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
	background: var(--color-chrome-border);
}

.panel-nav__link[aria-current="page"],
.panel-nav__link.is-current {
	background: var(--color-chrome-accent-subtle);
	color: var(--color-chrome-accent);
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
	background: var(--color-chrome-accent);
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
	background: var(--color-text);
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
	/* The ground, with a theme's texture over it (D-878). */
	background-color: var(--color-background);
	background-image: var(--background-image);
	background-size: var(--background-size);
}

.bar {
	display: flex;
	flex: none;
	height: var(--bar);
	align-items: center;
	gap: var(--s-2);
	padding: 0 var(--s-4);
	background: var(--color-chrome);
	border-bottom: 1px solid var(--color-chrome-border);
	color: var(--color-chrome-text);
}

/* The shared buttons the chrome holds (the drawer's menu and close, View
   Site), in its colors. */
.drawer :deep(.button--ghost),
.bar :deep(.button--ghost) {
	color: var(--color-chrome-text-secondary);
}

.drawer :deep(.button--ghost:hover),
.bar :deep(.button--ghost:hover) {
	background: var(--color-chrome-subtle);
	color: var(--color-chrome-text);
}

.bar__view {
	border-color: var(--color-chrome-border);
	background: var(--color-chrome);
	color: var(--color-chrome-text);
}

.bar__view:hover {
	background: var(--color-chrome-subtle);
	color: var(--color-chrome-text);
}

.bar__view:active {
	background: var(--color-chrome-strong);
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
	color: var(--color-chrome-text-secondary);
	font: inherit;
	text-overflow: ellipsis;
	cursor: pointer;
}

.bar__root:hover {
	color: var(--color-chrome-text);
	text-decoration: underline;
}

.bar__sep {
	color: var(--color-chrome-text-muted);
}

.bar__link {
	overflow: hidden;
	color: var(--color-chrome-text-secondary);
	text-decoration: none;
	text-overflow: ellipsis;
}

.bar__link:hover {
	color: var(--color-chrome-text);
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
	border: 1px solid var(--color-chrome-border);
	border-radius: var(--border-radius-small);
	background: var(--color-chrome-subtle);
	color: var(--color-chrome-text-muted);
	cursor: pointer;
}

.bar__search:hover {
	color: var(--color-chrome-text-secondary);
}

.bar__search kbd {
	border-color: var(--color-chrome-border);
	background: var(--color-chrome);
	color: var(--color-chrome-text-muted);
}

.bar__search :deep(svg) {
	width: 15px;
	height: 15px;
}

.bar__search-text {
	flex: 1;
	font-size: var(--font-size-small);
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
	padding: 0;
	border: 0;
	border-radius: 50%;
	background: var(--color-chrome-strong);
	color: var(--color-chrome-text-secondary);
	font-size: var(--font-size-xsmall);
	font-weight: 600;
	letter-spacing: 0.02em;
	text-transform: uppercase;
	cursor: pointer;
}

.bar :deep(.account) .avatar {
	width: 100%;
	height: 100%;
	background: none;
	color: inherit;
	font-size: inherit;
}

.bar :deep(.account[aria-expanded="true"]) {
	background: var(--color-chrome-accent);
	color: var(--color-chrome);
}

.account__who {
	display: grid;
	padding: 10px 11px 12px;
	margin-bottom: 7px;
	border-bottom: 1px solid var(--border-color);
	font-weight: 500;
}

.account__username,
.account__roles {
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
	font-weight: 400;
}

.offline {
	display: flex;
	flex: none;
	align-items: center;
	gap: 11px;
	padding: 11px var(--s-5);
	border-bottom: 1px solid var(--color-warning-indicator);
	background: var(--color-warning-subtle);
	color: var(--color-warning);
	font-size: var(--font-size-small);
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

/* Settings screens fill the width (D-404): their rows put the help to
   the right of the controls, so nothing is left stranded at the edge. */
.wrap--wide {
	max-width: none;
}

.wrap--narrow {
	max-width: calc(980px + 2 * var(--s-6));
}

/* A page's header stands further from what follows than sections do
   from each other, also under a screen's root that adds no box (D-879). */
.wrap :deep(.page-header) {
	margin-bottom: calc(var(--s-6) - var(--s-4));
}

@media (width <= 640px) {
	.wrap {
		gap: var(--s-3);
		padding: var(--s-5) var(--s-4) 80px;
	}

	.wrap :deep(.page-header) {
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
