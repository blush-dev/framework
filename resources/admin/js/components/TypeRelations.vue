<script setup lang="ts">
/**
 * A type's relationships (D-585, D-593; D-610, from the pickers sketch's
 * A Content Type's Screen board), on its screen whether or not the type
 * itself is edited here: every one it takes part in, from either side,
 * each a sentence from this type's side ("Credits **Profiles** as
 * Cooks", "Linked from **Posts** as Mentions"), with its key and what it
 * takes.
 *
 * Split by where it's stored: the ones stored on this type (its files
 * carry the key) are edited or removed here, on the relationship's own
 * screen; one stored on another type has one action, the way to that
 * type, so nobody edits from the wrong end. One from config or a plugin
 * keeps its row and loses its buttons, with a note on where it's
 * defined. One stored here that points back here appears once.
 *
 * The header links to the Relationships list, filtered to this type, and
 * **Add Relationship** starts one stored here.
 */

import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import { type ContentTypeDetail, type RelationInfo } from '../api';
import { useAction } from '../action';
import { purposeIcon, relationLabel, removeRelation, sentenceFrom, sourceOf, storedOn, takes } from '../relations';
import { canCreateTypes, labelsOf } from '../types';

const props = defineProps<{ type: ContentTypeDetail }>();
const emit  = defineEmits<{ changed: [] }>();

const { error, run } = useAction();

interface Row {
	relation: RelationInfo;
	say: ReturnType<typeof sentenceFrom>;
	takes: string;
	// The one type storing it, when it's stored elsewhere.
	owner: string | null;
}

const rows = computed(() => {
	const here = props.type.name;
	const all  = props.type.relations.map((relation): Row & { here: boolean } => {
		const stored = storedOn(relation, here);
		const owner  = stored || relation.from.length !== 1 ? null : relation.from[0] ?? null;

		return {
			relation,
			here: stored,
			say: sentenceFrom(relation, here),
			takes: takes(relation, stored ? undefined : (owner ? labelsOf(owner).item : 'entry')),
			owner
		};
	});

	return {
		here: all.filter((row) => row.here),
		elsewhere: all.filter((row) => !row.here)
	};
});

async function remove(relation: RelationInfo): Promise<void> {
	await run('The relationship couldn\'t be removed.', async () => {
		if (await removeRelation(relation)) {
			emit('changed');
		}
	});
}
</script>

<template>
	<section class="panel" aria-labelledby="relations-heading">
		<header class="panel__header">
			<h2 id="relations-heading">Relationships</h2>
			<p class="panel__hint">Every one {{ type.labels.plural }} takes part in, from either side</p>
			<div class="panel__actions">
				<RouterLink class="button button--ghost button--small" :to="{ name: 'relations', query: { type: type.name } }">View in Relationships</RouterLink>
				<RouterLink v-if="canCreateTypes" class="button button--small" :to="{ name: 'relation-new', query: { type: type.name } }"><AdminIcon name="plus" />Add Relationship</RouterLink>
			</div>
		</header>
		<div v-if="type.relations.length" class="table-wrap">
			<table class="table type-relations" aria-labelledby="relations-heading">
				<colgroup><col><col class="type-relations__key"><col class="type-relations__takes"><col class="type-relations__actions"></colgroup>
				<thead>
					<tr>
						<th scope="col">Relationship</th>
						<th scope="col">Key</th>
						<th scope="col">Takes</th>
						<th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th>
					</tr>
				</thead>
				<tbody v-for="group in ([['here', `Stored on ${type.labels.plural}`], ['elsewhere', 'Stored on other types']] as const)" :key="group[0]">
					<template v-if="rows[group[0]].length">
						<tr class="table__group"><th scope="rowgroup" colspan="4">{{ group[1] }}</th></tr>
						<tr v-for="row in rows[group[0]]" :key="row.relation.name">
							<th scope="row">
								<span class="type-relations__say">{{ row.say.before }} <strong>{{ row.say.type }}</strong> {{ row.say.after }}</span>
								<span v-if="!row.relation.editable" class="type-relations__sub">{{ row.relation.origin === 'extension' ? 'Defined by a plugin' : (row.relation.origin === 'config' ? 'Defined in config/content.php' : 'Written as a taxonomy until it\'s migrated') }}</span>
							</th>
							<td><span class="type-relations__key-text">{{ row.relation.field }}</span></td>
							<td><span class="type-relations__takes-text"><AdminIcon :name="purposeIcon(row.relation)" />{{ row.takes }}</span></td>
							<td class="table__actions">
								<template v-if="group[0] === 'elsewhere'">
									<RouterLink v-if="row.owner" class="button button--ghost button--small" :to="{ name: 'content-type', params: { name: row.owner } }">Edit on {{ labelsOf(row.owner).plural }}</RouterLink>
									<RouterLink v-else-if="row.relation.editable && canCreateTypes" class="button button--ghost button--small" :to="{ name: 'relation', params: { name: row.relation.name } }">Edit</RouterLink>
								</template>
								<template v-else-if="row.relation.editable && canCreateTypes">
									<RouterLink class="button button--small" :to="{ name: 'relation', params: { name: row.relation.name }, query: { type: type.name } }">Edit</RouterLink>
									<button type="button" class="button button--ghost button--small" :aria-label="`Remove ${relationLabel(row.relation)}`" @click="remove(row.relation)">Remove</button>
								</template>
								<span v-else-if="!row.relation.editable" class="type-relations__code" :title="sourceOf(row.relation)"><AdminIcon name="code" />In code</span>
							</td>
						</tr>
					</template>
				</tbody>
			</table>
		</div>
		<div v-else class="panel__body">
			<p class="field__help">Nothing links {{ type.labels.items }} to other entries yet.</p>
		</div>
		<p v-if="rows.here.some((row) => row.relation.to.includes(type.name))" class="panel__note">A relationship is edited on the type that stores it. One stored on {{ type.labels.plural }} that points back at {{ type.labels.plural }} appears once.</p>
		<p v-if="error" class="panel__body notice notice--error" role="alert">{{ error }}</p>
	</section>
</template>

<style scoped>
.type-relations {
	min-width: 640px;
	table-layout: fixed;
}

.type-relations__key {
	width: 128px;
}

.type-relations__takes {
	width: 240px;
}

.type-relations__actions {
	width: 184px;
}

.type-relations .table__actions {
	white-space: nowrap;
}

.type-relations th[scope="row"] {
	font-weight: 400;
}

.type-relations__say strong {
	font-weight: 600;
}

.type-relations__sub {
	display: block;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.type-relations__key-text {
	color: var(--fg-2);
	font-family: var(--font-mono);
	font-size: var(--text-xs);
	overflow-wrap: anywhere;
}

.type-relations__takes-text,
.type-relations__code {
	display: inline-flex;
	align-items: center;
	gap: var(--s-2);
	color: var(--fg-2);
}

.type-relations__takes-text .icon,
.type-relations__code .icon {
	flex: none;
	width: 14px;
	height: 14px;
	color: var(--fg-3);
}

.type-relations__code {
	color: var(--fg-3);
	font-size: var(--text-sm);
}
</style>
