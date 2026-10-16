<script setup lang="ts">
/**
 * Workflows (D-854), under Structure: the statuses each type's entries
 * move through and the transitions between them, built like
 * Relationships, with two tabs.
 *
 * - **Workflows:** a row each, its label (opening its screen) with its
 *   key under it, its statuses as pills in order, the types using it,
 *   and where it's from: Blush, a plugin, the site, or the site over
 *   one of those (a customized copy).
 * - **Statuses:** a row each, its pill and key, its plural, whether it
 *   puts entries on the site, the workflows using it, and where it's
 *   from, with **Edit** for the site's own, **Relabel** for Blush's and
 *   plugins', and **Delete** (or **Revert**, over another) for the
 *   site's records (`StatusDialog`). Blush's own computed statuses
 *   (scheduled, expired, trash) aren't listed.
 *
 * What was left out (D-853) is a notice above the tabs. The tab is in
 * the address (`?tab=statuses`).
 */

import { computed, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import StatusDialog from '../components/StatusDialog.vue';
import StatusPill from '../components/StatusPill.vue';
import { errorMessage, request, type StatusInfo, type WorkflowInfo, type WorkflowList } from '../api';
import { confirmAction } from '../confirm';
import { t } from '../messages';
import { setStatuses } from '../statuses';
import { toast } from '../toast';

const route = useRoute();

const list  = ref<WorkflowList | null>(null);
const error = ref('');

// The status dialog: a status to change, `'new'`, or closed.
const dialog = ref<StatusInfo | 'new' | null>(null);

function take(answer: WorkflowList): void {
	list.value = answer;
	setStatuses(answer.statuses);
}

request<WorkflowList>('GET', '/workflows').then(take, (caught: unknown) => {
	error.value = errorMessage(caught, t('workflows_view.not_loaded', 'The workflows couldn\'t be loaded.'));
});

const tab = computed(() => route.query.tab === 'statuses' ? 'statuses' : 'workflows');

// Blush's own computed statuses aren't the site's to change, or a
// workflow's to use.
const COMPUTED = ['scheduled', 'expired', 'trash'];

const statuses = computed(() => (list.value?.statuses ?? []).filter((status) => !COMPUTED.includes(status.name) && status.subject === 'entries'));
const labelOf  = (name: string): string => list.value?.workflows.find((workflow) => workflow.name === name)?.label ?? name;

function sourceOf(item: WorkflowInfo | StatusInfo): string {
	return item.over === null ? item.source.label : t('workflows_view.over', '{site}, over {source}', { site: item.source.label, source: item.over.label });
}

const tabs = computed(() => [
	{ key: 'workflows', label: t('workflows_view.workflows', 'Workflows'), count: list.value?.workflows.length },
	{ key: 'statuses', label: t('workflows_view.statuses', 'Statuses'), count: list.value === null ? undefined : statuses.value.length }
]);

function saved(answer: WorkflowList): void {
	const name = dialog.value === 'new' || dialog.value === null ? null : dialog.value.label;

	take(answer);
	dialog.value = null;
	toast(name === null ? t('workflows_view.status_added', 'Added the status') : t('workflows_view.status_saved', 'Saved {label}', { label: name }));
}

async function remove(status: StatusInfo): Promise<void> {
	const over = status.over !== null;

	if (!await confirmAction({
		title: over ? t('workflows_view.revert_title', 'Revert {label}?', { label: status.label }) : t('workflows_view.delete_title', 'Delete {label}?', { label: status.label }),
		body: over
			? t('workflows_view.revert_body', 'It goes back to the label and tone {source} gives it.', { source: status.over?.label ?? '' })
			: t('workflows_view.delete_body', 'Entries that still have it keep it in their files, and Site Health lists them.'),
		confirm: over ? t('workflows_view.revert', 'Revert the Status') : t('workflows_view.delete', 'Delete the Status'),
		danger: !over
	})) {
		return;
	}

	try {
		take(await request<WorkflowList>('DELETE', `/statuses/${encodeURIComponent(status.name)}`));
		toast(over ? t('workflows_view.reverted', 'Reverted {label}', { label: status.label }) : t('workflows_view.deleted', 'Deleted {label}', { label: status.label }), { kind: over ? 'info' : 'danger' });
	} catch (caught) {
		toast(errorMessage(caught, t('workflows_view.not_deleted', 'The status couldn\'t be deleted.')), { kind: 'warn' });
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ t('workflows_view.title', 'Workflows') }}</h1>
			<p class="page-header__hint">{{ t('workflows_view.hint', 'The statuses entries move through, and who may move them.') }}</p>
		</div>
		<div class="page-header__actions">
			<RouterLink v-if="tab === 'workflows'" class="button button--primary" :to="{ name: 'workflow-new' }"><AdminIcon name="plus" />{{ t('workflows_view.new_workflow', 'New Workflow') }}</RouterLink>
			<button v-else type="button" class="button button--primary" aria-haspopup="dialog" @click="dialog = 'new'"><AdminIcon name="plus" />{{ t('workflows_view.new_status', 'New Status') }}</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-for="problem in list?.problems ?? []" :key="problem" class="notice notice--warn"><AdminIcon name="triangle-alert" /><span>{{ problem }}</span></p>

	<template v-if="!error">
		<nav class="status-tabs" :aria-label="t('workflows_view.show', 'Show')">
			<RouterLink v-for="item in tabs" :key="item.key" class="status-tabs__tab" :to="{ query: { tab: item.key === 'workflows' ? undefined : item.key } }" :aria-current="tab === item.key ? 'page' : undefined">
				{{ item.label }}
				<span v-if="item.count !== undefined" class="status-tabs__count">{{ item.count }}</span>
			</RouterLink>
		</nav>

		<section v-if="tab === 'workflows'" class="panel" aria-labelledby="workflows-heading" :aria-busy="list === null">
			<header class="panel__header">
				<h2 id="workflows-heading">{{ t('workflows_view.workflows', 'Workflows') }}</h2>
				<p class="panel__hint">{{ t('workflows_view.workflows_hint', 'Each type uses one; Standard unless it says otherwise.') }}</p>
			</header>
			<SkeletonTable v-if="list === null" :columns="[t('workflows_view.columns.name', 'Name'), t('workflows_view.columns.statuses', 'Statuses'), t('workflows_view.columns.types', 'Used By'), t('workflows_view.columns.source', 'Source')]" :rows="2" :label="t('workflows_view.loading', 'Loading the workflows…')" />
			<div v-else class="table-wrap">
				<table class="table workflows-table" aria-labelledby="workflows-heading">
					<thead>
						<tr>
							<th scope="col">{{ t('workflows_view.columns.name', 'Name') }}</th>
							<th scope="col">{{ t('workflows_view.columns.statuses', 'Statuses') }}</th>
							<th scope="col">{{ t('workflows_view.columns.types', 'Used By') }}</th>
							<th scope="col">{{ t('workflows_view.columns.source', 'Source') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="workflow in list.workflows" :key="workflow.name">
							<th scope="row">
								<span class="entry-title">
									<span class="entry-title__text"><RouterLink class="entry-title__link" :to="{ name: 'workflow', params: { name: workflow.name } }">{{ workflow.label }}</RouterLink></span>
									<span class="entry-title__path">{{ workflow.name }}</span>
								</span>
							</th>
							<td><span class="workflows-table__pills"><StatusPill v-for="status in workflow.statuses" :key="status" :status="status" /></span></td>
							<td>
								<template v-if="workflow.types.length">
									<template v-for="(type, index) in workflow.types" :key="type.name"><template v-if="index">, </template><RouterLink class="lnk" :to="{ name: 'content-type', params: { name: type.name } }">{{ type.label }}</RouterLink></template>
								</template>
								<span v-else class="muted">{{ t('workflows_view.no_types', 'No types') }}</span>
							</td>
							<td>{{ sourceOf(workflow) }}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>

		<section v-else class="panel" aria-labelledby="statuses-heading" :aria-busy="list === null">
			<header class="panel__header">
				<h2 id="statuses-heading">{{ t('workflows_view.statuses', 'Statuses') }}</h2>
				<p class="panel__hint">{{ t('workflows_view.statuses_hint', 'One list for every type: a status means the same in each file.') }}</p>
			</header>
			<SkeletonTable v-if="list === null" :columns="[t('workflows_view.columns.status', 'Status'), t('workflows_view.columns.plural', 'Plural'), t('workflows_view.columns.live', 'On the Site'), t('workflows_view.columns.workflows', 'Used In'), t('workflows_view.columns.source', 'Source'), '']" :rows="3" :label="t('workflows_view.loading_statuses', 'Loading the statuses…')" />
			<EmptyState v-else-if="statuses.length === 0" icon="route" :heading="t('workflows_view.no_statuses', 'No Statuses')" :text="t('workflows_view.no_statuses_text', 'Blush\'s Draft and Published are always here.')" />
			<div v-else class="table-wrap">
				<table class="table" aria-labelledby="statuses-heading">
					<thead>
						<tr>
							<th scope="col">{{ t('workflows_view.columns.status', 'Status') }}</th>
							<th scope="col">{{ t('workflows_view.columns.plural', 'Plural') }}</th>
							<th scope="col">{{ t('workflows_view.columns.live', 'On the Site') }}</th>
							<th scope="col">{{ t('workflows_view.columns.workflows', 'Used In') }}</th>
							<th scope="col">{{ t('workflows_view.columns.source', 'Source') }}</th>
							<th scope="col"><span class="visually-hidden">{{ t('workflows_view.columns.actions', 'Actions') }}</span></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="status in statuses" :key="status.name">
							<th scope="row">
								<span class="entry-title">
									<span class="entry-title__text"><StatusPill :status="status.name" /></span>
									<span class="entry-title__path">{{ status.name }}</span>
								</span>
							</th>
							<td>{{ status.plural }}</td>
							<td>{{ status.live ? t('workflows_view.live_yes', 'Yes, once dated') : t('workflows_view.live_no', 'No') }}</td>
							<td>
								<template v-if="status.workflows.length">{{ status.workflows.map(labelOf).join(', ') }}</template>
								<span v-else class="muted">{{ t('workflows_view.no_workflows', 'None') }}</span>
							</td>
							<td>{{ sourceOf(status) }}</td>
							<td class="table__buttons">
								<button v-if="status.edit || status.relabel" type="button" class="button button--ghost button--small" aria-haspopup="dialog" @click="dialog = status">{{ status.edit ? t('common.edit', 'Edit') : t('workflows_view.relabel', 'Relabel') }}</button>
								<button v-if="status.delete" type="button" class="button button--ghost button--small" @click="remove(status)">{{ status.over ? t('workflows_view.revert_button', 'Revert') : t('common.delete', 'Delete') }}</button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>
	</template>

	<StatusDialog
		v-if="dialog !== null"
		:status="dialog === 'new' ? null : dialog"
		:taken="(list?.statuses ?? []).map((status) => status.name)"
		@saved="saved"
		@close="dialog = null"
	/>
</template>

<style scoped>
.workflows-table__pills {
	display: inline-flex;
	flex-wrap: wrap;
	gap: var(--s-1);
}
</style>
