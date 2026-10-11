<script setup lang="ts">
/**
 * Structure → Fields (the fields sketch, D-766): every field set, as a
 * list screen, then a screen for each. A set adds its fields to the
 * entries of the content types it names (D-767). Tabs split the sets
 * this site made (`user/data/fields`, edited here) from those in code
 * (config and plugins, shown here). Each row says the set's label and
 * key, the types it's used on (one the site doesn't have, such as a type
 * that's turned off, says so), where it's from, and how many fields it
 * has, with a menu to open it or delete it. **New Field Set** asks for
 * its label, key, help, types, and position (`FieldSetSettings`), then
 * opens it.
 */

import { computed, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import FieldSetSettings, { type FieldSetSettingsValue } from '../components/FieldSetSettings.vue';
import MenuButton from '../components/MenuButton.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import { useAction } from '../action';
import { errorMessage, request, type FieldSetDetail, type FieldSetList, type FieldSetSummary } from '../api';
import { confirmAction } from '../confirm';
import { plural } from '../format';
import { toast } from '../toast';
import { refreshTypes } from '../types';

const route  = useRoute();
const router = useRouter();

const list     = ref<FieldSetList | null>(null);
const error    = ref('');
const creating = ref(false);

const { busy, error: failure, run } = useAction();

async function load(): Promise<void> {
	try {
		list.value = await request<FieldSetList>('GET', '/fields/sets');
	} catch (caught) {
		error.value = errorMessage(caught, 'The field sets couldn\'t be loaded.');
	}
}

void load();

type Tab = '' | 'site' | 'code';

const tab  = computed<Tab>(() => route.query.from === 'site' || route.query.from === 'code' ? route.query.from : '');
const sets = computed(() => list.value?.sets ?? []);
const tabs = computed(() => [
	{ key: '' as Tab, label: 'All', count: sets.value.length },
	{ key: 'site' as Tab, label: 'This Site', count: sets.value.filter((set) => set.origin === 'data').length },
	{ key: 'code' as Tab, label: 'In Code', count: sets.value.filter((set) => set.origin !== 'data').length }
]);
const shown = computed(() => sets.value.filter((set) => tab.value === '' || (tab.value === 'site') === (set.origin === 'data')));

/**
 * Where a set comes from, for people.
 */
function from(set: FieldSetSummary): string {
	return { extension: 'A plugin', config: 'config/fields.php', data: 'This site' }[set.origin];
}

const blank: FieldSetSettingsValue = { name: '', label: '', description: '', targets: [], position: 10 };

async function create(value: FieldSetSettingsValue): Promise<void> {
	await run('The field set couldn\'t be created.', async () => {
		const saved = await request<FieldSetDetail>('POST', '/fields/sets', {
			name: value.name,
			set: { label: value.label, description: value.description || null, targets: value.targets, position: value.position === 10 ? null : value.position, fields: [] }
		});

		refreshTypes();
		creating.value = false;
		toast(`Created ${saved.label}`);
		await router.push({ name: 'field-set', params: { name: saved.name } });
	});
}

async function remove(set: FieldSetSummary): Promise<void> {
	if (!await confirmAction({ title: `Delete ${set.label}?`, body: ['Its file in user/data/fields is removed, and its fields leave the types it\'s used on.', 'Entries keep their values in their files.'], confirm: 'Delete Field Set', danger: true })) {
		return;
	}

	try {
		await request('DELETE', `/fields/sets/${encodeURIComponent(set.name)}`);
		refreshTypes();
		toast(`Deleted ${set.label}`, { kind: 'danger' });
		await load();
	} catch (caught) {
		toast(errorMessage(caught, `${set.label} couldn't be deleted.`), { kind: 'warn' });
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Fields</h1>
			<p class="page-header__hint">Sets of fields added to content types. Every entry of a type gets its sets' fields.</p>
		</div>
		<div v-if="list?.create" class="page-header__actions">
			<button type="button" class="button button--primary" aria-haspopup="dialog" @click="creating = true"><AdminIcon name="plus" />New Field Set</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-else>
		<nav class="status-tabs" aria-label="Where sets are from">
			<RouterLink v-for="item in tabs" :key="item.key" class="status-tabs__tab" :to="{ query: { ...route.query, from: item.key || undefined } }" :aria-current="tab === item.key ? 'page' : undefined">
				{{ item.label }}
				<span v-if="list" class="status-tabs__count">{{ item.count }}</span>
			</RouterLink>
		</nav>

		<section class="panel" aria-labelledby="sets-heading" :aria-busy="list === null">
			<header class="panel__header">
				<h2 id="sets-heading">{{ tabs.find((item) => item.key === tab)?.label === 'All' ? 'Field Sets' : tabs.find((item) => item.key === tab)?.label }}</h2>
				<p v-if="list" class="panel__hint">{{ plural(shown.length, 'set') }}</p>
			</header>

			<SkeletonTable v-if="list === null" :columns="['Field set', 'Used on', 'From', 'Fields']" :rows="3" label="Loading the field sets…" />
			<EmptyState v-else-if="!shown.length" icon="group" :heading="tab === 'code' ? 'No Field Sets in Code' : 'No Field Sets Yet'" :text="tab === 'code' ? 'Plugins and config/fields.php can define field sets. None are on this site.' : 'A set adds the same fields to several content types: a recipe\'s details, or search engine settings for posts and pages.'">
				<template v-if="tab !== 'code' && list.create" #actions>
					<button type="button" class="button button--primary" @click="creating = true"><AdminIcon name="plus" />New Field Set</button>
				</template>
			</EmptyState>
			<div v-else class="table-wrap">
				<table class="table field-sets" aria-labelledby="sets-heading">
					<thead>
						<tr>
							<th scope="col">Field set</th>
							<th scope="col">Used on</th>
							<th scope="col">From</th>
							<th scope="col" class="table__count">Fields</th>
							<th scope="col"><span class="visually-hidden">Actions</span></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="set in shown" :key="set.name">
							<th scope="row">
								<span class="entry-title">
									<span class="entry-title__text">
										<RouterLink class="entry-title__link" :to="{ name: 'field-set', params: { name: set.name } }">{{ set.label }}</RouterLink>
									</span>
									<span class="entry-title__path">{{ set.name }}</span>
								</span>
							</th>
							<td>
								<ul v-if="set.targets.length" class="chips">
									<li v-for="target in set.targets" :key="target.key">
										<span v-if="target.found" class="chips__item">{{ target.label }}</span>
										<span v-else class="pill pill--warn" :title="`The site has no ${target.key}`">Type Is Off<span class="visually-hidden">: {{ target.key }}</span></span>
									</li>
								</ul>
								<span v-else class="field-sets__none">Nowhere yet</span>
							</td>
							<td>
								<span class="field-sets__from"><AdminIcon v-if="!set.editable" name="lock" />{{ from(set) }}</span>
							</td>
							<td class="table__count mono">{{ set.fields }}</td>
							<td class="table__actions">
								<MenuButton button-class="row-more" :label="`Actions for ${set.label}`" floating>
									<template #button><AdminIcon name="ellipsis" /></template>
									<RouterLink class="menu-item" :to="{ name: 'field-set', params: { name: set.name } }"><AdminIcon :name="set.editable ? 'pen-line' : 'eye'" />{{ set.editable ? 'Edit fields' : 'Open' }}</RouterLink>
									<template v-if="set.editable">
										<RouterLink class="menu-item" :to="{ name: 'field-set', params: { name: set.name }, query: { settings: '1' } }"><AdminIcon name="settings" />Settings</RouterLink>
										<div class="menu-divider" />
										<button type="button" class="menu-item menu-item--danger" @click="remove(set)"><AdminIcon name="trash-2" />Delete field set</button>
									</template>
								</MenuButton>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>
	</template>

	<FieldSetSettings
		v-if="creating && list"
		fresh
		:value="blank"
		:options="list.targets"
		:names="sets.map((set) => set.name)"
		:busy="busy"
		:error="failure"
		@done="create"
		@close="creating = false"
	/>
</template>

<style scoped>
.field-sets__none {
	color: var(--fg-3);
}

.field-sets__from {
	display: inline-flex;
	align-items: center;
	gap: var(--s-1);
}

.field-sets__from .icon {
	width: 13px;
	height: 13px;
	color: var(--fg-3);
}
</style>
