<script setup lang="ts">
/**
 * Menus (from the menus sketch): the site's menus, and the active
 * theme's locations, each showing one of them.
 *
 * **Menus** is a table: the label leads (with the name under it) and
 * opens the menu, then how many items it has and the locations showing
 * it, and a row menu (Edit, Duplicate, Delete). New Menu and Duplicate
 * open `MenuDialog`; deleting asks first, says which locations go back
 * to their default, and offers Undo.
 *
 * **Locations** are rows of label, control, and help, as Settings draws
 * them: each location's menu is chosen in a select, saved at once, with
 * Undo. Until it's given one, a location shows the theme's default
 * items, or nothing.
 */

import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect, { type SelectOption } from '../components/AdminSelect.vue';
import EmptyState from '../components/EmptyState.vue';
import MenuButton from '../components/MenuButton.vue';
import MenuDialog from '../components/MenuDialog.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import { errorMessage } from '../api';
import { confirmAction } from '../confirm';
import { loadCounts } from '../counts';
import { plural } from '../format';
import { assignLocation, deleteMenu, loadMenus, saveMenu, type MenuListed, type MenuLocation, type MenuOverview } from '../menus';
import { toast } from '../toast';

const overview = ref<MenuOverview | null>(null);
const error    = ref('');
const dialog   = ref<{ from: MenuListed | null } | null>(null);

function load(): void {
	loadMenus().then((answer) => {
		overview.value = answer;
	}, (caught: unknown) => {
		error.value = errorMessage(caught, 'The menus couldn\'t be loaded.');
	});
}

load();

const menus = computed(() => [...overview.value?.menus ?? []].sort((a, b) => labelOf(a).localeCompare(labelOf(b))));
const names = computed(() => menus.value.map((menu) => menu.name));

const labelOf = (menu: MenuListed): string => menu.label || menu.name;

function locationsOf(menu: MenuListed): string {
	return (overview.value?.locations ?? []).filter((location) => menu.locations.includes(location.name)).map((location) => location.label).join(', ');
}

function depthOf(location: MenuLocation): string {
	if (location.depth === null) {
		return 'Any depth';
	}

	return location.depth === 1 ? 'One level' : `Up to ${location.depth} levels`;
}

function fallback(location: MenuLocation): string {
	return location.defaults > 0 ? 'Theme Default' : 'Nothing';
}

function options(location: MenuLocation): SelectOption[] {
	return [
		...menus.value.map((menu) => {
			const elsewhere = (overview.value?.locations ?? []).filter((other) => other.name !== location.name && other.menu === menu.name);

			return { value: menu.name, label: labelOf(menu), hint: elsewhere.length ? `Also in ${elsewhere.map((other) => other.label).join(', ')}` : null };
		}),
		{ value: '', label: fallback(location), hint: location.defaults > 0 ? `The ${plural(location.defaults, 'item')} the theme ships` : 'The location stays empty', pinned: true }
	];
}

async function assign(location: MenuLocation, value: string): Promise<void> {
	const was  = location.menu;
	const menu = value === '' ? null : value;

	if (menu === was) {
		return;
	}

	try {
		overview.value = await assignLocation(location.name, menu);

		const said = menu === null ? (location.defaults > 0 ? 'the theme\'s default' : 'nothing') : labelOf(menus.value.find((item) => item.name === menu)!);

		toast(`${location.label} now shows ${said}`, {
			undo: async () => {
				overview.value = await assignLocation(location.name, was);
			}
		});
	} catch (caught) {
		toast(errorMessage(caught, `${location.label} couldn't be changed.`), { kind: 'warn' });
	}
}

async function remove(menu: MenuListed): Promise<void> {
	const showing = (overview.value?.locations ?? []).filter((location) => menu.locations.includes(location.name));
	const ok      = await confirmAction({
		title: `Delete ${labelOf(menu)}?`,
		body: [
			showing.length ? 'The locations showing it go back to the theme\'s default, or show nothing.' : 'It isn\'t in any location.',
			`An entry that shows it with **::menu{name=${menu.name}}** shows nothing there.`
		],
		items: showing.map((location) => ({ title: location.label, meta: 'Location' })),
		confirm: `Delete ${labelOf(menu)}`,
		danger: true
	});

	if (!ok) {
		return;
	}

	try {
		const answer = await deleteMenu(menu.name);

		load();
		void loadCounts();
		toast(`Deleted ${labelOf(menu)}`, {
			undo: async () => {
				await saveMenu({ was: null, name: answer.deleted.name, label: answer.deleted.label, items: answer.deleted.items, locations: answer.locations });
				load();
				void loadCounts();
			}
		});
	} catch (caught) {
		toast(errorMessage(caught, `${labelOf(menu)} couldn't be deleted.`), { kind: 'warn' });
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Menus</h1>
			<p class="page-header__hint">Lists of links for the site. The theme decides where they can go, and any entry can hold one too.</p>
		</div>
		<div v-if="overview" class="page-header__actions">
			<button type="button" class="button button--primary" @click="dialog = { from: null }"><AdminIcon name="plus" />New Menu</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div v-else class="setting-panels">
		<section class="panel" aria-labelledby="menus-heading" :aria-busy="overview === null">
			<header class="panel__header">
				<h2 id="menus-heading">Menus</h2>
				<p v-if="overview" class="panel__hint">{{ plural(menus.length, 'menu') }}</p>
			</header>
			<SkeletonTable v-if="!overview" :columns="['Menu', 'Items', 'Locations']" :rows="3" label="Loading the menus…" />
			<EmptyState v-else-if="!menus.length" icon="menu" heading="No Menus Yet" text="A menu is a list of links you build once, then place in the theme's locations or inside an entry.">
				<template #actions>
					<button type="button" class="button button--primary" @click="dialog = { from: null }"><AdminIcon name="plus" />New Menu</button>
				</template>
			</EmptyState>
			<div v-else class="table-wrap">
				<table class="table menus-table" aria-labelledby="menus-heading">
					<colgroup><col class="menus-table__name"><col class="menus-table__count"><col><col class="table__actions"></colgroup>
					<thead>
						<tr>
							<th scope="col">Menu</th>
							<th scope="col" class="table__count">Items</th>
							<th scope="col">Locations</th>
							<th scope="col"><span class="visually-hidden">Actions</span></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="menu in menus" :key="menu.name">
							<th scope="row">
								<span class="entry-title">
									<span class="entry-title__text"><RouterLink class="entry-title__link" :to="{ name: 'menu', params: { name: menu.name } }">{{ labelOf(menu) }}</RouterLink></span>
									<span class="entry-title__path">{{ menu.name }}</span>
								</span>
							</th>
							<td class="table__count mono">{{ menu.items.toLocaleString() }}</td>
							<td><template v-if="menu.locations.length">{{ locationsOf(menu) }}</template><span v-else class="muted">No locations</span></td>
							<td class="table__actions">
								<MenuButton button-class="row-more" :label="`Actions for ${labelOf(menu)}`" floating>
									<template #button><AdminIcon name="ellipsis" /></template>
									<RouterLink class="menu-item" :to="{ name: 'menu', params: { name: menu.name } }"><AdminIcon name="pen-line" />Edit</RouterLink>
									<button type="button" class="menu-item" @click="dialog = { from: menu }"><AdminIcon name="copy" />Duplicate</button>
									<div class="menu-divider" />
									<button type="button" class="menu-item menu-item--danger" @click="remove(menu)"><AdminIcon name="trash-2" />Delete</button>
								</MenuButton>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>

		<section v-if="overview" class="panel" aria-labelledby="locations-heading">
			<header class="panel__header setting-panels__header">
				<h2 id="locations-heading">Locations</h2>
				<p class="panel__hint">{{ overview.theme }} · Each shows one menu. Changes save right away.</p>
			</header>
			<p v-if="!overview.locations.length" class="panel__note">{{ overview.theme }} has no menu locations. Its menus can still go in an entry with <code>::menu{name=…}</code>.</p>
			<div v-else class="setting-panels__rows">
				<div v-for="location in overview.locations" :key="location.name" class="setting">
					<div class="setting__label"><label :for="`location-${location.name}`">{{ location.label }}</label></div>
					<div class="field setting__control">
						<AdminSelect :id="`location-${location.name}`" :model-value="location.menu ?? ''" :options="options(location)" :described-by="`location-${location.name}-help`" @update:model-value="assign(location, $event)" />
					</div>
					<div :id="`location-${location.name}-help`" class="setting__help">
						<p><span class="mono">{{ location.name }}</span> · {{ depthOf(location) }}<template v-if="location.defaults"> · Ships {{ plural(location.defaults, 'default item') }}</template></p>
					</div>
				</div>
			</div>
		</section>
	</div>

	<MenuDialog v-if="dialog" :names="names" :from="dialog.from" @close="dialog = null" />
</template>

<style scoped>
.menus-table__name {
	width: 40%;
}

.menus-table__count {
	width: 12%;
}
</style>
