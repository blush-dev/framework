<script setup lang="ts">
/**
 * The signed-in layout (D-231): a rail with the site's name, the admin's
 * navigation, and the account; a top bar; and the work area, the only
 * part that scrolls. The rail collapses to icons (remembered in this
 * browser), and below 860px it's a drawer opened from the top bar.
 */

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter, type RouteLocationRaw } from 'vue-router';
import { ApiError, type ContentTypeSummary } from '../api';
import { config } from '../config';
import type { IconName } from '../icons';
import { screenTitle } from '../screen';
import { can, session, signOut } from '../session';
import { currentType, loadTypes, typeIcon, types } from '../types';
import AdminIcon from './AdminIcon.vue';

interface NavLink {
	key: string;
	label: string;
	icon: IconName;
	to: RouteLocationRaw;
	current?: boolean;
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

/**
 * The navigation: the admin's own screens, then a link to each content
 * type's entries, with taxonomies under their own heading (D-234).
 */
const groups = computed<NavGroup[]>(() => {
	const tools: NavLink[] = [{ key: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard', to: { name: 'dashboard' } }];

	if (can('content.edit.others')) {
		tools.push({ key: 'health', label: 'Content health', icon: 'heart-pulse', to: { name: 'health' } });
	}

	if (!can('content.edit')) {
		return [{ key: 'tools', links: tools }];
	}

	const inEntries = route.meta.section === 'entries';
	const link = (type: ContentTypeSummary): NavLink => ({
		key: type.name,
		label: type.label,
		icon: typeIcon(type),
		to: { name: 'type', params: { type: type.name } },
		current: inEntries && currentType.value === type.name
	});

	const all: NavGroup[] = [
		{ key: 'tools', links: tools },
		{
			key: 'content',
			heading: 'Content',
			links: [
				...types.value.filter((type) => type.kind !== 'taxonomy').map(link),
				{ key: 'all', label: 'All entries', icon: 'library', to: { name: 'entries' }, current: inEntries && currentType.value === null }
			]
		},
		{ key: 'taxonomies', heading: 'Taxonomies', links: types.value.filter((type) => type.kind === 'taxonomy').map(link) }
	];

	return all.filter((group) => group.links.length > 0);
});

const title = computed(() => screenTitle.value ?? (typeof route.meta.title === 'string' ? route.meta.title : ''));

// The collapsed rail is a per-browser convenience; storage may be off.
const COLLAPSED = 'blush-admin-rail-collapsed';

function stored(): boolean {
	try {
		return localStorage.getItem(COLLAPSED) === '1';
	} catch {
		return false;
	}
}

const collapsed = ref(stored());

watch(collapsed, (value) => {
	try {
		localStorage.setItem(COLLAPSED, value ? '1' : '0');
	} catch {
		// Not remembered; nothing else depends on it.
	}
});

// Below 860px the rail is a drawer.
const query  = window.matchMedia('(width <= 860px)');
const narrow = ref(query.matches);
const open   = ref(false);
const menu   = ref<HTMLButtonElement | null>(null);
const rail   = ref<HTMLElement | null>(null);

function changed(event: MediaQueryListEvent): void {
	narrow.value = event.matches;
	open.value   = false;
}

function openDrawer(): void {
	open.value = true;
	requestAnimationFrame(() => rail.value?.querySelector<HTMLElement>('a, button')?.focus());
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

function keydown(event: KeyboardEvent): void {
	if (event.key === 'Escape' && open.value) {
		void closeDrawer();
	}
}

// Following a link in the drawer closes it; focus goes to the new
// screen's heading (App.vue).
router.afterEach(() => void closeDrawer(false));

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
	<div class="app" :class="{ 'is-collapsed': collapsed && !narrow, 'is-narrow': narrow, 'is-open': open }">
		<a class="skip-link" href="#main">Skip to content</a>

		<div id="rail" ref="rail" class="rail" :inert="narrow && !open">
			<div class="rail__site">
				<a class="rail__site-link" :href="config.site.url" :title="collapsed && !narrow ? config.site.name : undefined">
					<span class="rail__mark" aria-hidden="true">{{ config.site.name.charAt(0) }}</span>
					<span class="rail__label">{{ config.site.name }}</span>
				</a>
				<button v-if="narrow" type="button" class="button button--ghost button--icon" @click="closeDrawer()">
					<AdminIcon name="x" />
					<span class="visually-hidden">Close menu</span>
				</button>
			</div>

			<nav class="rail__nav" aria-label="Admin">
				<div v-for="group in groups" :key="group.key" class="rail__group">
					<p v-if="group.heading" :id="`nav-${group.key}`" class="rail__heading"><span class="rail__label">{{ group.heading }}</span></p>
					<ul :aria-labelledby="group.heading ? `nav-${group.key}` : undefined">
						<li v-for="link in group.links" :key="link.key">
							<RouterLink class="rail__link" :class="{ 'is-current': link.current }" :to="link.to" :title="collapsed && !narrow ? link.label : undefined">
								<AdminIcon :name="link.icon" />
								<span class="rail__label">{{ link.label }}</span>
							</RouterLink>
						</li>
					</ul>
				</div>
			</nav>

			<div class="rail__foot">
				<RouterLink class="rail__account" :to="{ name: 'profile' }" :title="collapsed && !narrow ? 'Your profile' : undefined">
					<span class="rail__avatar" aria-hidden="true">{{ session.account?.username.charAt(0) }}</span>
					<span class="rail__label rail__who">
						<span>{{ session.account?.username }}</span>
						<span class="rail__profile">Your profile</span>
					</span>
				</RouterLink>
				<button type="button" class="button button--ghost button--icon" :disabled="leaving" :title="leaving ? 'Signing out…' : 'Sign out'" @click="leave">
					<AdminIcon name="log-out" />
					<span class="visually-hidden">{{ leaving ? 'Signing out…' : 'Sign out' }}</span>
				</button>
			</div>
		</div>

		<div v-if="narrow && open" class="scrim" aria-hidden="true" @click="closeDrawer()" />

		<div class="work" :inert="narrow && open">
			<header class="bar">
				<button v-if="narrow" ref="menu" type="button" class="button button--ghost button--icon" aria-controls="rail" :aria-expanded="open" @click="openDrawer">
					<AdminIcon name="menu" />
					<span class="visually-hidden">Menu</span>
				</button>
				<button v-else type="button" class="button button--ghost button--icon" aria-controls="rail" :aria-expanded="!collapsed" @click="collapsed = !collapsed">
					<AdminIcon name="panel-left" />
					<span class="visually-hidden">{{ collapsed ? 'Expand the sidebar' : 'Collapse the sidebar' }}</span>
				</button>
				<p class="bar__crumbs">
					<span class="bar__root">{{ config.site.name }}</span>
					<span class="bar__sep" aria-hidden="true">/</span>
					<span class="bar__current">{{ title }}</span>
				</p>
				<a class="button button--small bar__view" :href="config.site.url" target="_blank" rel="noopener">
					<AdminIcon name="external-link" />
					<span>View site</span><span class="visually-hidden"> (new tab)</span>
				</a>
			</header>

			<main id="main" class="main">
				<div class="wrap">
					<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
					<slot />
				</div>
			</main>
		</div>
	</div>
</template>

<style scoped>
.app {
	display: grid;
	grid-template-columns: var(--rail) minmax(0, 1fr);
	height: 100%;
	transition: grid-template-columns 150ms ease-out;
}

.app.is-collapsed {
	grid-template-columns: var(--rail-min) minmax(0, 1fr);
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

/* Rail */

.rail {
	display: grid;
	grid-template-rows: var(--bar) minmax(0, 1fr) auto;
	min-width: 0;
	background: var(--surface);
	border-right: 1px solid var(--border);
	overflow: hidden;
}

.rail__site {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 0 12px;
	border-bottom: 1px solid var(--border);
}

.rail__site-link {
	display: flex;
	flex: 1;
	align-items: center;
	gap: 10px;
	min-width: 0;
	color: var(--fg);
	font-family: var(--font-display);
	font-weight: 600;
	text-decoration: none;
}

.rail__mark,
.rail__avatar {
	display: grid;
	flex: none;
	place-items: center;
	width: 28px;
	height: 28px;
	border-radius: var(--r-1);
	background: var(--accent);
	color: var(--accent-fg);
	font-family: var(--font-display);
	font-weight: 600;
	text-transform: uppercase;
}

.rail__avatar {
	border-radius: 50%;
	background: var(--surface-3);
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.rail__label {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.rail__nav {
	display: grid;
	align-content: start;
	gap: 14px;
	padding: 10px 8px;
	overflow-y: auto;
}

.rail__heading {
	padding: 0 10px 4px;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 500;
	letter-spacing: .06em;
	text-transform: uppercase;
}

.is-collapsed .rail__heading {
	height: 1px;
	margin: 0 10px;
	padding: 0;
	background: var(--border);
}

.rail__nav ul {
	display: grid;
	gap: 2px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.rail__link {
	position: relative;
	display: flex;
	align-items: center;
	gap: 10px;
	height: 32px;
	padding: 0 10px;
	border-radius: var(--r-1);
	color: var(--fg-2);
	font-weight: 500;
	text-decoration: none;
}

.rail__link:hover {
	background: var(--surface-2);
	color: var(--fg);
}

.rail__link[aria-current="page"],
.rail__link.is-current {
	background: var(--accent-soft);
	color: var(--accent);
}

.rail__link[aria-current="page"]::before,
.rail__link.is-current::before {
	content: "";
	position: absolute;
	top: 7px;
	bottom: 7px;
	left: -8px;
	width: 3px;
	border-radius: 0 3px 3px 0;
	background: var(--accent);
}

.rail__foot {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 10px 12px;
	border-top: 1px solid var(--border);
}

.rail__account {
	display: flex;
	flex: 1;
	align-items: center;
	gap: 10px;
	min-width: 0;
	margin: -4px 0 -4px -4px;
	padding: 4px;
	border-radius: var(--r-1);
	color: var(--fg);
	font-weight: 500;
	text-decoration: none;
}

.rail__account:hover {
	background: var(--surface-2);
	color: var(--fg);
}

.rail__account[aria-current="page"] .rail__avatar {
	background: var(--accent);
	color: var(--accent-fg);
}

.rail__who {
	display: grid;
}

.rail__profile {
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 400;
}

/* The collapsed rail shows icons only; labels stay for screen readers. */

.is-collapsed .rail__site,
.is-collapsed .rail__foot {
	flex-direction: column;
	justify-content: center;
	padding-inline: 0;
}

.is-collapsed .rail__foot {
	gap: 6px;
}

.is-collapsed .rail__site-link,
.is-collapsed .rail__account {
	flex: none;
}

.is-collapsed .rail__link {
	justify-content: center;
	padding: 0;
}

.is-collapsed .rail__label {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	clip-path: inset(50%);
}

/* Drawer */

.app.is-narrow {
	grid-template-columns: minmax(0, 1fr);
}

.is-narrow .rail {
	position: fixed;
	inset: 0 auto 0 0;
	z-index: 20;
	width: min(var(--rail), calc(100% - 48px));
	box-shadow: var(--shadow-3);
	transform: translateX(-100%);
	visibility: hidden;
	transition: transform 180ms ease-out, visibility 0s linear 180ms;
}

.is-narrow.is-open .rail {
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

/* Work area */

.work {
	display: grid;
	grid-template-rows: var(--bar) minmax(0, 1fr);
	min-width: 0;
	min-height: 0;
}

.bar {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 0 16px 0 12px;
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
	color: var(--fg-2);
	text-overflow: ellipsis;
}

.bar__sep {
	color: var(--fg-3);
}

.bar__current {
	overflow: hidden;
	font-weight: 500;
	text-overflow: ellipsis;
}

.main {
	overflow: auto;
}

.wrap {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: 20px;
	max-width: var(--work-max);
	margin: 0 auto;
	padding: 22px 24px 40px;
}

@media (width <= 640px) {
	.wrap {
		gap: 16px;
		padding: 16px 16px 32px;
	}

	.bar__root,
	.bar__sep {
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
