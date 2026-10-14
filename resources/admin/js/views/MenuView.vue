<script setup lang="ts">
/**
 * A menu (from the menus sketch), in the frame of the admin's other
 * detail screens (Roles): a back link, the title with its facts under
 * it (name, items, locations), and the actions at the right: Settings
 * (`MenuSettings`: label, name, the block, locations), the save
 * button, and a menu with Duplicate and Delete. Below is the item tree
 * (`MenuTree`).
 *
 * Everything is saved together by the one button: **Publish** for a new
 * menu (a draft in this tab, made by New Menu or Duplicate, written for
 * the first time here), **Update** once something's changed, and
 * **Saved** when nothing has. Leaving with changes asks first; leaving
 * a draft discards it.
 *
 * A menu whose stored shape is wrong (an item that isn't a map, two
 * links on one item) is shown but can't be saved, so nothing the screen
 * can't show is lost; the notice says what to fix, and where.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import MenuButton from '../components/MenuButton.vue';
import MenuDialog from '../components/MenuDialog.vue';
import MenuSettings, { type MenuSettingsValue } from '../components/MenuSettings.vue';
import MenuTree from '../components/MenuTree.vue';
import { ApiError, errorMessage } from '../api';
import { confirmAction, confirmLeave, guardLeave } from '../confirm';
import { loadCounts } from '../counts';
import { deleteMenu, draft, empty, flatten, loadMenu, loadMenus, saveMenu, textOf, toData, toItems, toNodes, withText, type MenuDetail, type MenuDraft, type MenuLocation, type MenuNode } from '../menus';
import { toast } from '../toast';
import { t } from '../messages';
import MessageText from '../components/MessageText.vue';

const route  = useRoute();
const router = useRouter();

interface Context {
	locations: MenuLocation[];
	menus: { name: string; label: string }[];
	kinds: MenuDetail['kinds'];
	locale: string;
}

const context  = ref<Context | null>(null);
const nodes    = ref<MenuNode[]>([]);
// The name it's saved under, or `null` for a draft.
const saved    = ref<string | null>(null);
const label    = ref<unknown>('');
const name     = ref('');
const shownIn  = ref<string[]>([]);
const editable = ref(true);
const problems = ref<string[]>([]);
const where    = ref('');
const loaded   = ref<string | null>(null);
const error    = ref('');
const saving   = ref(false);
const version  = ref(0);
const snapshot = ref('');
const settings = ref(false);
const copying  = ref(false);
let leaving    = false;
// The draft this screen shows, if it shows one.
let ownDraft: MenuDraft | null = null;

const locale = computed(() => context.value?.locale ?? 'en');
const title  = computed(() => textOf(label.value, locale.value).trim() || name.value);
const count  = computed(() => flatten(nodes.value).length);

function state(): string {
	return JSON.stringify({ label: label.value, name: name.value, locations: shownIn.value, items: toItems(nodes.value) });
}

// Read again whenever the tree says it changed.
const changed = computed(() => {
	void version.value;

	return saved.value === null || state() !== snapshot.value;
});

const button = computed(() => saved.value === null ? t('menu_view.publish', 'Publish') : changed.value ? t('menu_view.update', 'Update') : t('menu_view.saved', 'Saved'));

const locationNames = computed(() => (context.value?.locations ?? []).filter((location) => shownIn.value.includes(location.name)).map((location) => location.label));

function fill(detail: MenuDetail): void {
	context.value  = { locations: detail.locations, menus: detail.menus, kinds: detail.kinds, locale: detail.locale };
	nodes.value    = toNodes(detail.menu.items);
	saved.value    = detail.menu.name;
	label.value    = detail.menu.label;
	name.value     = detail.menu.name;
	shownIn.value  = detail.locations.filter((location) => location.menu === detail.menu.name).map((location) => location.name);
	editable.value = detail.menu.editable;
	problems.value = detail.menu.problems;
	where.value    = detail.menu.where;
	loaded.value   = detail.menu.name;
	snapshot.value = state();
}

async function load(menu: string): Promise<void> {
	error.value = '';

	if (draft.value?.name === menu) {
		const fresh = draft.value;

		ownDraft = fresh;

		try {
			const overview = await loadMenus();

			context.value  = { locations: overview.locations, menus: overview.menus, kinds: overview.kinds, locale: overview.locale };
			nodes.value    = toNodes(fresh.items);
			saved.value    = null;
			label.value    = fresh.label;
			name.value     = fresh.name;
			shownIn.value  = [];
			editable.value = true;
			problems.value = [];
			loaded.value   = fresh.name;
		} catch (caught) {
			error.value = errorMessage(caught, t('menu_view.errors.load', 'The menu couldn\'t be loaded.'));
		}

		return;
	}

	try {
		fill(await loadMenu(menu));
	} catch (caught) {
		loaded.value = null;
		error.value  = errorMessage(caught, t('menu_view.errors.load', 'The menu couldn\'t be loaded.'));
	}
}

watch(() => String(route.params.name ?? ''), (menu) => {
	if (menu !== '' && menu !== loaded.value) {
		void load(menu);
	}
}, { immediate: true });

function touched(): void {
	version.value++;
}

function applySettings(value: MenuSettingsValue): void {
	label.value    = withText(label.value, value.label, locale.value);
	name.value     = value.name;
	shownIn.value  = value.locations;
	settings.value = false;
}

async function save(): Promise<void> {
	if (!changed.value || saving.value || !editable.value) {
		return;
	}

	const blank = flatten(nodes.value).find((placed) => empty(placed.node, locale.value));

	if (blank) {
		toast(t('menu_view.errors.blank', 'Give every item a label or a link first. Items marked Empty have neither.'), { kind: 'warn' });

		return;
	}

	saving.value = true;

	const publishing = saved.value === null;
	const was        = saved.value;
	const before     = new Set(context.value?.locations.filter((location) => location.menu !== null && location.menu === was).map((location) => location.name) ?? []);

	try {
		const detail = await saveMenu({ was, name: name.value, label: label.value, items: toItems(nodes.value), locations: shownIn.value });

		draft.value = null;
		fill(detail);
		touched();
		void loadCounts();

		const after   = new Set(shownIn.value);
		const moved   = detail.locations.filter((location) => before.has(location.name) !== after.has(location.name)).map((location) => location.label);
		const renamed = was !== null && was !== detail.menu.name ? t('menu_view.renamed', 'Renamed {from} to {to}', { from: was, to: detail.menu.name }) : '';
		const extra   = [renamed, moved.length ? t('menu_view.locations_changed', '{locations} changed', { locations: moved.join(' and ') }) : ''].filter((text) => text !== '');
		const head    = publishing ? t('menu_view.published', 'Published {title}', { title: title.value }) : t('menu_view.updated', 'Updated {title}', { title: title.value });

		toast(extra.length ? t('menu_view.saved_and', '{head}. {extra}.', { head, extra: extra.join('. ') }) : head);

		if (route.params.name !== detail.menu.name) {
			await router.replace({ name: 'menu', params: { name: detail.menu.name } });
		}
	} catch (caught) {
		const problem = caught instanceof ApiError ? caught.message : t('menu_view.errors.save', 'The menu couldn\'t be saved.');

		toast(problem, { kind: 'warn' });
	} finally {
		saving.value = false;
	}
}

async function remove(): Promise<void> {
	if (saved.value === null) {
		leaving     = true;
		draft.value = null;
		await router.push({ name: 'menus' });

		return;
	}

	const menu    = saved.value;
	const showing = (context.value?.locations ?? []).filter((location) => location.menu === menu);
	const ok      = await confirmAction({
		title: t('menu_view.delete_title', 'Delete {title}?', { title: title.value }),
		body: [
			showing.length ? t('menu_view.delete_locations', 'The locations showing it go back to the theme\'s default, or show nothing.') : t('menu_view.delete_no_location', 'It isn\'t in any location.'),
			t('menu_view.delete_entries', 'An entry that shows it with **{snippet}** shows nothing there.', { snippet: `::menu{name=${menu}}` })
		],
		items: showing.map((location) => ({ title: location.label, meta: t('menu_view.location', 'Location') })),
		confirm: t('menu_view.delete', 'Delete {title}', { title: title.value }),
		danger: true
	});

	if (!ok) {
		return;
	}

	try {
		const answer = await deleteMenu(menu);
		const was    = title.value;

		leaving = true;
		void loadCounts();
		await router.push({ name: 'menus' });
		toast(t('menu_view.deleted', 'Deleted {title}', { title: was }), {
			undo: async () => {
				await saveMenu({ was: null, name: answer.deleted.name, label: answer.deleted.label, items: answer.deleted.items, locations: answer.locations });
				void loadCounts();
				await router.push({ name: 'menu', params: { name: answer.deleted.name } });
			}
		});
	} catch (caught) {
		toast(errorMessage(caught, t('menu_view.errors.delete', '{title} couldn\'t be deleted.', { title: title.value })), { kind: 'warn' });
	}
}

guardLeave(() => !leaving && editable.value && loaded.value !== null && changed.value, async () => {
	if (saved.value !== null) {
		return confirmLeave(t('menu_view.leave_unsaved', 'The items, settings, and locations you changed haven\'t been saved.'));
	}

	const ok = await confirmAction({ title: t('menu_view.leave_title', 'Leave Without Publishing {title}?', { title: title.value }), body: t('menu_view.leave_body', 'It hasn\'t been published yet. Leaving discards it.'), confirm: t('menu_view.discard_menu', 'Discard Menu'), cancel: t('menu_view.keep_editing', 'Keep Editing'), danger: true });

	// A copy made from here is a draft of its own, and stays.
	if (ok && draft.value === ownDraft) {
		draft.value = null;
	}

	return ok;
});
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'menus' }"><AdminIcon name="chevron-left" />{{ t('menu_view.all_menus', 'All menus') }}</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ loaded ? title : t('menu_view.menu', 'Menu') }}</h1>
			<div v-if="loaded" class="page-facts">
				<span class="page-facts__item"><AdminIcon name="menu" /><span class="mono">{{ name }}</span></span>
				<span class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item">{{ t('menu_view.items', '{count, plural, one {# item} other {# items} }', { count }) }}</span>
				<span class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item"><AdminIcon name="map-pin" />{{ locationNames.length ? locationNames.join(', ') : t('menu_view.no_locations', 'No locations') }}</span>
				<template v-if="saved === null">
					<span class="page-facts__divider" aria-hidden="true" />
					<span class="pill pill--warn">{{ t('menu_view.not_published', 'Not Published') }}</span>
				</template>
			</div>
		</div>
		<div v-if="loaded" class="page-header__actions">
			<button type="button" class="button" :disabled="!editable" aria-haspopup="dialog" @click="settings = true"><AdminIcon name="settings" />{{ t('menu_view.settings', 'Settings') }}</button>
			<button type="button" class="button button--primary" :disabled="!changed || saving || !editable" @click="save">{{ saving ? t('menu_view.saving', 'Saving…') : button }}</button>
			<MenuButton button-class="button button--icon" :label="t('menu_view.more_actions', 'More actions')" align="end">
				<template #button><AdminIcon name="ellipsis-vertical" /></template>
				<button type="button" class="menu-item" @click="copying = true"><AdminIcon name="copy" />{{ t('menu_view.duplicate', 'Duplicate') }}</button>
				<div class="menu-divider" />
				<button type="button" class="menu-item menu-item--danger" @click="remove"><AdminIcon name="trash-2" />{{ saved === null ? t('menu_view.discard', 'Discard') : t('common.delete', 'Delete') }}</button>
			</MenuButton>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-else-if="loaded && context">
		<div v-if="!editable" class="notice notice--error" role="alert">
			<AdminIcon name="triangle-alert" />
			<div class="notice__text">
				<p><strong>{{ t('menu_view.not_editable', 'This menu can\'t be edited here until it\'s fixed.') }}</strong> <MessageText :text="t('menu_view.fix_in', 'Fix it in {file}, then reload.')"><template #file><code>{{ where }}</code></template></MessageText></p>
				<ul>
					<li v-for="(problem, index) in problems" :key="index">{{ problem }}</li>
				</ul>
			</div>
		</div>
		<fieldset class="menu-view__items" :disabled="!editable">
			<MenuTree :nodes="nodes" :locale="locale" :kinds="context.kinds" @change="touched" />
		</fieldset>
	</template>

	<MenuSettings
		v-if="settings && context"
		:value="{ label: textOf(label, locale), name, locations: shownIn }"
		:saved="saved"
		:locations="context.locations"
		:menus="context.menus"
		@done="applySettings"
		@close="settings = false"
	/>
	<MenuDialog
		v-if="copying && context"
		:names="context.menus.map((menu) => menu.name)"
		:from="{ name: saved ?? name, label: title, items: count, nodes: toData(nodes) }"
		@close="copying = false"
	/>
</template>

<style scoped>
.menu-view__items {
	min-width: 0;
	margin: 0;
	padding: 0;
	border: 0;
}
</style>
