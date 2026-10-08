<script setup lang="ts">
/**
 * A type's relationships (D-585, D-593), on its screen whether or not the
 * type itself is edited here: every relation definition from or to it,
 * each said as a sentence from this type's side ("Filed under
 * Categories", "Files Posts and Recipes", "Links to People as `actors`"),
 * with what it asks of an entry. A relation the site defines in
 * `user/data/relations` is changed (`RelationForm`) or removed here; one
 * from config or a plugin says where it's defined. **Add Relationship**
 * starts one from this type's side.
 *
 * Links entries hold stay in their files when a relation is removed.
 * After a change it asks the server to refresh the routes and the index
 * (`refreshTypes()`), and `changed` asks the screen to load the type
 * again.
 */

import { computed, ref } from 'vue';
import AdminIcon from './AdminIcon.vue';
import RelationForm from './RelationForm.vue';
import { request, type ContentTypeDetail, type RelationInfo } from '../api';
import { useAction } from '../action';
import { confirmAction, confirmChecked } from '../confirm';
import { plural } from '../format';
import { toast } from '../toast';
import { canCreateTypes, refreshTypes, types } from '../types';

const props = defineProps<{ type: ContentTypeDetail }>();
const emit  = defineEmits<{ changed: [] }>();

const editing = ref<RelationInfo | null>(null);
const open    = ref(false);

const { error, run } = useAction();

const labelOf = (name: string): string => types.value.find((item) => item.name === name)?.labels.plural ?? name;
const listOf  = (names: string[]): string => names.length <= 2 ? names.map(labelOf).join(' and ') : `${names.slice(0, -1).map(labelOf).join(', ')}, and ${labelOf(names.at(-1) ?? '')}`;

// Each relation, said from this type's side.
const rows = computed(() => props.type.relations.map((relation) => {
	const here     = props.type.name;
	const isTarget = relation.to.includes(here);
	const filed    = relation.kind === 'classify';

	const credit   = relation.kind === 'credit';
	const label    = relation.label || relation.name;

	const title = filed
		? (isTarget ? `Files ${relation.from.length === 0 ? 'every type' : listOf(relation.from)}` : `Filed under ${labelOf(relation.to[0] ?? '')}`)
		: (credit
			? (isTarget ? `Credited by ${relation.from.length === 0 ? 'every type' : listOf(relation.from)} as ${label.toLowerCase()}` : `Credits ${label.toLowerCase()}`)
			: (isTarget && !relation.from.includes(here) ? `${listOf(relation.from)} link here` : `Links to ${listOf(relation.to)}`));

	const asks = [
		relation.multiple ? (relation.ordered ? 'several, in order' : 'several') : 'one',
		...(filed && relation.create ? ['added as typed'] : []),
		...(filed && relation.inverse !== false && relation.inverse.page ? ['each term has a page'] : [])
	];

	return { relation, title, sub: `Written as ${relation.field} · ${asks.join(' · ')}`, required: relation.min > 0 };
}));

function source(relation: RelationInfo): string {
	return relation.origin === 'config' ? 'In config/content.php' : (relation.origin === 'extension' ? 'From a plugin' : 'Written as a taxonomy until it\'s migrated');
}

function add(): void {
	editing.value = null;
	open.value    = true;
}

function edit(relation: RelationInfo): void {
	editing.value = relation;
	open.value    = true;
}

async function remove(relation: RelationInfo, title: string): Promise<void> {
	const name    = encodeURIComponent(relation.name);
	const entries = await request<{ entries: number }>('GET', `/relations/${name}/uses`).then((answer) => answer.entries, () => 0);
	const body    = [`**${title}** is removed from user/data/relations, and the site stops reading it as a link.`];

	// Entries keep their values unless asked (D-600): kept, the
	// relationship can come back as it was.
	const strip = entries === 0
		? (await confirmAction({ title: 'Remove the Relationship?', body: [...body, 'No entry has a value in it.'], confirm: 'Remove It', danger: true }) ? false : null)
		: await confirmChecked({
			title: 'Remove the Relationship?',
			body: [...body, `**${plural(entries, 'entry has', 'entries have')} values in it**, under \`${relation.field}\`. Kept, they stay in their files, so adding the relationship again brings the links back.`],
			check: `Also remove them, with their ids, from ${entries === 1 ? 'that entry' : `those ${entries} entries`}`,
			checked: false,
			confirm: 'Remove It',
			danger: true
		});

	if (strip === null) {
		return;
	}

	await run('The relationship couldn\'t be removed.', async () => {
		const answer = await request<{ deleted: string; stripped: number }>('DELETE', `/relations/${name}${strip ? '?strip=1' : ''}`);

		refreshTypes();
		toast(answer.stripped > 0 ? `Removed the relationship, and its values from ${plural(answer.stripped, 'entry', 'entries')}` : 'Removed the relationship', { kind: 'danger' });
		emit('changed');
	});
}
</script>

<template>
	<section class="panel" aria-labelledby="relations-heading">
		<header class="panel__header">
			<h2 id="relations-heading">Relationships</h2>
			<p class="panel__hint">How its entries link to others, and others to them</p>
			<div v-if="canCreateTypes" class="panel__actions">
				<button type="button" class="button" @click="add"><AdminIcon name="plus" />Add Relationship</button>
			</div>
		</header>
		<ul v-if="rows.length" class="rows">
			<li v-for="row in rows" :key="row.relation.name">
				<div class="rows__item">
					<AdminIcon class="rows__icon" :name="row.relation.kind === 'classify' ? 'tag' : (row.relation.kind === 'credit' ? 'user' : 'link')" />
					<div class="rows__main">
						<span class="rows__title">{{ row.title }} <span v-if="row.required" class="tag">Required</span></span>
						<span class="rows__sub">{{ row.sub }}</span>
					</div>
					<div class="rows__side">
						<template v-if="row.relation.editable">
							<button type="button" class="button" @click="edit(row.relation)">Edit</button>
							<button type="button" class="button" @click="remove(row.relation, row.title)">Remove</button>
						</template>
						<span v-else class="rows__sub">{{ source(row.relation) }}</span>
					</div>
				</div>
			</li>
		</ul>
		<div v-else class="panel__body">
			<p class="field__help">Nothing links {{ type.labels.items }} to other entries yet.</p>
		</div>
		<p v-if="error" class="panel__body notice notice--error" role="alert">{{ error }}</p>
		<RelationForm :open="open" :type="type.name" :relation="editing" @close="open = false" @saved="emit('changed')" />
	</section>
</template>
