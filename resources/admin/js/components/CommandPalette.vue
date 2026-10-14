<script setup lang="ts">
/**
 * The command palette (admin.md §7, D-248), opened with ⌘K (Ctrl+K) or
 * the top bar's search button: a modal over a scrim that searches
 * commands first (the screen's own, then going places and creating
 * entries), then entries. With nothing typed, it offers the commands and
 * the entries changed most recently. Up and down move, Enter runs or
 * opens, Escape closes.
 */

import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import MessageText from './MessageText.vue';
import TypeIcon from './TypeIcon.vue';
import StatusPill from './StatusPill.vue';
import { debounced, latest } from '../action';
import { errorMessage, request, type EntryList, type EntrySummary } from '../api';
import { loadAccounts, type AccountInfo } from '../people';
import { adminTheme, adminThemes, defaultAdminTheme, saveAdminTheme } from '../admin-theme';
import { colorScheme, saveColorScheme } from '../color-scheme';
import { commandMatches, screenCommands, type Command } from '../commands';
import { useModalDialog } from '../dialog';
import { listMove } from '../grid';
import { t } from '../messages';
import type { IconName } from '../icons';
import { can, canAnyType, canType, usesMedia } from '../session';
import { findType, listRoute, typeIcon, types } from '../types';

const emit = defineEmits<{ close: [] }>();

const router  = useRouter();
const list    = ref<HTMLElement | null>(null);
const query   = ref('');
const active  = ref(0);
const entries = ref<EntrySummary[]>([]);
const failed  = ref('');
// Accounts, for whoever manages them, so a person is found on either
// side: their account here, their profile among the entries (D-353).
const accounts = ref<AccountInfo[]>([]);

const { dialog, close, backdrop } = useModalDialog(() => void findEntries());

if (can('accounts.view')) {
	loadAccounts().then((list) => {
		accounts.value = list;
	}, () => undefined);
}

const go = (name: string, params?: Record<string, string>): (() => void) => () => void router.push({ name, params });

// Going places and creating entries, as the account may.
const everywhere = computed<Command[]>(() => {
	const found: Command[] = [{ id: 'dashboard', label: t('command_palette.go_dashboard', 'Go to the dashboard'), icon: 'gauge', keywords: 'home', run: go('dashboard') }];

	for (const type of types.value.filter((item) => canType(item.name, 'edit'))) {
		found.push({ id: `type-${type.name}`, label: t('command_palette.go_type', 'Go to {type}', { type: type.labels.plural }), icon: typeIcon(type), keywords: type.name, run: go('type', { type: type.name }) });
	}

	for (const type of types.value.filter((item) => canType(item.name, 'create'))) {
		found.push({ id: `new-${type.name}`, label: type.labels.newItem, icon: 'plus', keywords: `create add ${type.name}`, run: () => void router.push({ name: 'entry-new', query: { type: type.name } }) });
	}

	if (can('site.health')) {
		found.push({ id: 'health', label: t('command_palette.go_health', 'Go to Site Health'), icon: 'heart-pulse', keywords: 'problems lint checks requirements doctor', run: go('health') });
	}

	const screens: [string, string, IconName, string, string?][] = [
		['media', t('command_palette.go_media', 'Go to Media'), 'image', 'media', 'files images library uploads'],
		['types', t('command_palette.go_types', 'Go to Content Types'), 'layers', 'site.settings'],
		['relations', t('command_palette.go_relations', 'Go to Relationships'), 'workflow', 'site.settings', 'relations links taxonomies credits terms'],
		['fields', t('command_palette.go_fields', 'Go to Fields'), 'group', 'site.settings', 'field sets custom fields'],
		['themes', t('command_palette.go_themes', 'Go to Themes'), 'paintbrush', 'extensions.themes.view', 'appearance look'],
		['plugins', t('command_palette.go_plugins', 'Go to Plugins'), 'plug', 'extensions.plugins.view', 'addons extensions'],
		['icon-packs', t('command_palette.go_icon_packs', 'Go to Icon Packs'), 'shapes', 'extensions.icon-packs.view', 'icons extensions'],
		['admin-themes', t('command_palette.go_admin_themes', 'Go to Admin Themes'), 'swatch-book', 'extensions.admin-themes.view', 'admin look skin appearance extensions'],
		['accounts', t('command_palette.go_accounts', 'Go to Accounts'), 'key-round', 'accounts.view', 'people users sign in'],
		['roles', t('command_palette.go_roles', 'Go to Roles'), 'shield', 'accounts.view', 'capabilities']
	];

	for (const [name, label, icon, capability, keywords] of screens) {
		if (capability === 'media' ? usesMedia() : can(capability)) {
			found.push({ id: name, label, icon, keywords, run: go(name) });
		}
	}

	// The Settings screens (D-325).
	const settings: [string, string, string][] = [
		['general', t('command_palette.go_general_settings', 'Go to General Settings'), 'site name description tagline language locale time zone timezone sign-ups signups register accounts environment'],
		['reading', t('command_palette.go_reading_settings', 'Go to Reading Settings'), 'homepage front page feeds rss atom json'],
		['writing', t('command_palette.go_writing_settings', 'Go to Writing Settings'), 'markdown mentions smart quotes punctuation heading anchors figures raw html'],
		['media', t('command_palette.go_media_settings', 'Go to Media Settings'), 'uploads upload size path folders files documents images'],
		['email', t('command_palette.go_email_settings', 'Go to Email Settings'), 'mail smtp sendmail from sender address test notifications'],
		['search', t('command_palette.go_search_settings', 'Go to Addresses and Search Settings'), 'trailing slash urls sitemap robots seo'],
		['ai', t('command_palette.go_ai_settings', 'Go to AI Settings'), 'llms.txt markdown copies crawlers robots gptbot claudebot agents'],
		['api', t('command_palette.go_api_settings', 'Go to API Settings'), 'api json tokens headless rest limits changes writes'],
		['system', t('command_palette.go_system_settings', 'Go to System Settings'), 'content types caching cache publishing webhook git previews']
	];

	if (can('site.settings')) {
		for (const [screen, label, keywords] of settings) {
			found.push({ id: `settings-${screen}`, label, icon: 'settings', keywords, run: go('settings', { screen }) });
		}
	}

	for (const account of accounts.value) {
		found.push({ id: `account-${account.username}`, label: t('command_palette.account', '{name}\'s account', { name: account.displayName }), icon: 'key-round', keywords: `${account.username} account person`, run: go('account', { username: account.username }) });
	}

	found.push(
		{ id: 'profile', label: t('command_palette.go_profile', 'Go to Your Account'), icon: 'circle-user-round', keywords: 'profile password preferences', run: go('profile') },
		{
			id: 'scheme',
			label: colorScheme.value === 'dark' ? t('command_palette.use_light', 'Use the light color scheme') : t('command_palette.use_dark', 'Use the dark color scheme'),
			icon: colorScheme.value === 'dark' ? 'sun' : 'moon',
			keywords: 'theme dark light mode appearance',
			run: () => void saveColorScheme(colorScheme.value === 'dark' ? 'light' : 'dark').catch(() => undefined)
		},
		// Each other admin theme that's on (D-827); the site's default is
		// followed rather than chosen.
		...adminThemes.filter((theme) => theme.name !== adminTheme.value?.name).map((theme) => ({
			id: `admin-theme-${theme.name}`,
			label: t('command_palette.use_admin_theme', 'Use the {theme} admin theme', { theme: theme.label }),
			icon: 'swatch-book' as const,
			keywords: `admin theme look appearance skin ${theme.label} ${theme.name}`,
			run: () => void saveAdminTheme(theme.name === defaultAdminTheme.value ? null : theme.name).catch(() => undefined)
		}))
	);

	return found;
});

const commands = computed(() => {
	const all = [...screenCommands(), ...everywhere.value];

	return (query.value.trim() === '' ? all : all.filter((command) => commandMatches(command, query.value))).slice(0, query.value.trim() === '' ? 8 : 12);
});

interface Row {
	key: string;
	run: () => void;
}

const rows = computed<Row[]>(() => [
	...commands.value.map((command) => ({ key: `command-${command.id}`, run: command.run })),
	...entries.value.map((entry) => ({ key: `entry-${entry.path}`, run: () => void router.push(listRoute(entry)) }))
]);

// Entries: the latest changed, or those matching, a moment after typing.
const ask    = latest();
const typing = debounced(() => void findEntries(), 200);

async function findEntries(): Promise<void> {
	if (!canAnyType('edit')) {
		return;
	}

	const current = ask();
	const params  = new URLSearchParams({ per: '6' });

	if (query.value.trim() !== '') {
		params.set('search', query.value.trim());
	}

	try {
		const answer = await request<EntryList>('GET', `/entries?${params.toString()}`);

		if (current()) {
			entries.value = answer.entries;
			failed.value  = '';
		}
	} catch (caught) {
		if (current()) {
			entries.value = [];
			failed.value  = errorMessage(caught, t('command_palette.search_failed', 'Entries couldn\'t be searched.'));
		}
	}
}

watch(query, () => {
	active.value = 0;
	typing();
});

watch(active, () => {
	list.value?.querySelector('.palette__item.is-active')?.scrollIntoView({ block: 'nearest' });
});

function run(row: Row | undefined): void {
	if (row === undefined) {
		return;
	}

	close();
	row.run();
}

function keydown(event: KeyboardEvent): void {
	if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
		event.preventDefault();
		active.value = listMove(event.key, active.value, rows.value.length, false) ?? active.value;
	} else if (event.key === 'Enter') {
		event.preventDefault();
		run(rows.value[active.value]);
	} else if (event.key === 'Escape') {
		// Closed here, so the screen under it doesn't also act on Escape.
		event.preventDefault();
		event.stopPropagation();
		close();
	}
}

const commandCount = computed(() => commands.value.length);
</script>

<template>
	<dialog ref="dialog" class="palette" :aria-label="t('command_palette.label', 'Search and commands')" @close="emit('close')" @click="backdrop" @keydown="keydown">
		<div class="palette__input">
			<AdminIcon name="search" />
			<input
				v-model="query"
				type="text"
				:placeholder="t('command_palette.placeholder', 'Search entries, or type a command…')"
				autocomplete="off"
				spellcheck="false"
				role="combobox"
				:aria-label="t('command_palette.input_label', 'Search entries and commands')"
				aria-expanded="true"
				aria-controls="palette-list"
				:aria-activedescendant="rows[active] ? `palette-${rows[active]?.key}` : undefined"
			>
			<kbd>esc</kbd>
		</div>

		<div id="palette-list" ref="list" class="palette__list" role="listbox" :aria-label="t('command_palette.results', 'Results')">
			<template v-if="commands.length">
				<p class="eyebrow palette__section" aria-hidden="true">{{ t('command_palette.commands', 'Commands') }}</p>
				<div
					v-for="(command, index) in commands"
					:id="`palette-command-${command.id}`"
					:key="command.id"
					class="palette__item"
					:class="{ 'is-active': index === active }"
					role="option"
					:aria-selected="index === active"
					@pointermove="active = index"
					@click="run(rows[index])"
				>
					<AdminIcon :name="command.icon" />
					<span>{{ command.label }}</span>
					<kbd v-if="command.shortcut" class="palette__shortcut">{{ command.shortcut }}</kbd>
				</div>
			</template>

			<template v-if="entries.length">
				<p class="eyebrow palette__section" aria-hidden="true">{{ query.trim() ? t('command_palette.matching', 'Matching entries') : t('command_palette.recent', 'Recently changed') }}</p>
				<div
					v-for="(entry, index) in entries"
					:id="`palette-entry-${entry.path}`"
					:key="entry.path"
					class="palette__item"
					:class="{ 'is-active': commandCount + index === active }"
					role="option"
					:aria-selected="commandCount + index === active"
					@pointermove="active = commandCount + index"
					@click="run(rows[commandCount + index])"
				>
					<TypeIcon v-if="findType(entry.type)" :type="findType(entry.type)!" />
					<AdminIcon v-else name="file-text" />
					<span class="palette__title">{{ entry.title || t('command_palette.untitled', 'Untitled') }}</span>
					<span class="palette__type">{{ findType(entry.type)?.labels.singular ?? entry.type }}</span>
					<StatusPill :status="entry.status" />
				</div>
			</template>

			<p v-if="!rows.length" class="palette__none">{{ failed || t('command_palette.nothing', 'Nothing matches.') }}</p>
		</div>

		<p class="palette__foot">
			<span><MessageText :text="t('command_palette.hint_move', '{keys} move')"><template #keys><kbd>↑</kbd><kbd>↓</kbd></template></MessageText></span>
			<span><MessageText :text="t('command_palette.hint_open', '{key} open')"><template #key><kbd>↵</kbd></template></MessageText></span>
			<span><MessageText :text="t('command_palette.hint_close', '{key} close')"><template #key><kbd>esc</kbd></template></MessageText></span>
		</p>
	</dialog>
</template>

<style scoped>
.palette {
	width: min(560px, calc(100vw - 32px));
	max-height: none;
	margin: 14vh auto auto;
	padding: 0;
	overflow: hidden;
	border: 1px solid var(--border-color-strong);
	border-radius: var(--border-radius-large);
	background: var(--color-surface);
	box-shadow: var(--shadow-3);
	color: var(--color-text);
}

.palette::backdrop {
	background: color-mix(in srgb, var(--color-text) 30%, transparent);
}

.palette__input {
	display: flex;
	align-items: center;
	gap: 11px;
	padding: 18px var(--s-5);
	border-bottom: 1px solid var(--border-color);
	color: var(--color-text-muted);
}

.palette__input input {
	flex: 1;
	min-width: 0;
	border: 0;
	background: none;
	color: var(--color-text);
	font-size: var(--font-size-editor);
	outline: none;
}

.palette__list {
	max-height: min(46vh, 360px);
	padding: var(--s-2);
	overflow-y: auto;
}

.palette__section {
	padding: var(--s-3) 10px var(--s-1);
	font-size: var(--font-size-xsmall);
}

.palette__item {
	display: flex;
	align-items: center;
	gap: 11px;
	padding: 10px;
	border-radius: var(--border-radius-small);
	color: var(--color-text-secondary);
	cursor: pointer;
}

.palette__item > :deep(svg) {
	flex: none;
	width: 16px;
	height: 16px;
	color: var(--color-text-muted);
}

.palette__item.is-active {
	background: var(--color-surface-subtle);
	color: var(--color-text);
}

.palette__item.is-active > :deep(svg) {
	color: var(--color-accent);
}

.palette__title {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.palette__type {
	flex: none;
	margin-left: auto;
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}

.palette__shortcut {
	margin-left: auto;
}

.palette__none {
	padding: 22px 8px;
	color: var(--color-text-muted);
	text-align: center;
}

.palette__foot {
	display: flex;
	gap: var(--s-4);
	padding: 12px var(--s-5);
	border-top: 1px solid var(--border-color);
	background: var(--color-background);
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}
</style>
