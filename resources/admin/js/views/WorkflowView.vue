<script setup lang="ts">
/**
 * One workflow (D-854), in the frame of Field Sets' and Menus' screens:
 * a back link, the title with its facts under it (key, statuses,
 * transitions, the types using it, where it's from), and at the right
 * **Update** and a menu with Revert changes and Delete (or Revert to
 * Blush's or the plugin's, for a customized copy).
 *
 * A workflow from Blush or a plugin opens read-only, with **Customize**,
 * which copies it into the site's own (D-853) to edit. A new one starts
 * from Draft and Published, with Publish and Unpublish.
 *
 * Two panels, each the shared `ItemTree`, flat (D-509):
 *
 * - **Statuses**, in order: each row the status's pill, **First** on the
 *   one new entries start at, and how many entries of its types have it;
 *   its fields choose the status and make it the first. Taking out one
 *   that entries still have asks first, and Site Health's Reset moves
 *   them (D-853); transitions to it go too, and it leaves others' from.
 * - **Transitions**, in the order the editor offers them: each row said
 *   as a sentence ("Submit for Review: Draft to In Review"), with **Own
 *   Capability** when it has one; its fields are its label, key (fixed
 *   once saved: roles name it), the statuses it moves from (none for
 *   any), the one it moves to, and whether it needs a capability of its
 *   own.
 *
 * Everything saves with the one button, and the server checks it as it
 * reads it (D-853). Leaving with changes asks first.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect from '../components/AdminSelect.vue';
import ChoiceChips from '../components/ChoiceChips.vue';
import ItemTree from '../components/ItemTree.vue';
import MenuButton from '../components/MenuButton.vue';
import MessageText from '../components/MessageText.vue';
import StatusPill from '../components/StatusPill.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import { errorMessage, request, type StatusInfo, type WorkflowInfo, type WorkflowList } from '../api';
import { confirmAction, guardLeave } from '../confirm';
import { t } from '../messages';
import { screenTitle } from '../screen';
import { setStatuses, statusOf } from '../statuses';
import { toast } from '../toast';
import type { ItemTreeApi, ItemTreeWords, TreeNode } from '../tree';
import { keyOf } from '../type-form';
import { refreshTypes } from '../types';

interface StatusNode extends TreeNode<StatusNode> {
	name: string;
}

interface TransitionNode extends TreeNode<TransitionNode> {
	name: string;
	label: string;
	from: string[];
	to: string;
	capability: boolean;
	// Whether it's saved, so its key is fixed.
	saved: boolean;
}

const route  = useRoute();
const router = useRouter();

const fresh    = computed(() => route.name === 'workflow-new');
const workflow = ref<WorkflowInfo | null>(null);
const statuses = ref<StatusInfo[]>([]);
const error    = ref('');
const failure  = ref('');
const saving   = ref(false);
const left     = ref(0);

const label       = ref('');
const key         = ref('');
const named       = ref(false);
const initial     = ref('');
const statusNodes = ref<StatusNode[]>([]);
const moves       = ref<TransitionNode[]>([]);
const statusTree  = ref<ItemTreeApi<StatusNode> | null>(null);
const moveTree    = ref<ItemTreeApi<TransitionNode> | null>(null);

let next = 0;

const id = (): string => `n${++next}`;

const editable = computed(() => fresh.value || workflow.value?.editable === true);
const name     = computed(() => fresh.value ? (named.value ? key.value : keyOf(label.value)) : (workflow.value?.name ?? ''));
const chosen   = computed(() => statusNodes.value.map((node) => node.name).filter((status) => status !== ''));

// What the server describes, as the screen edits it.
function fill(from: WorkflowInfo): void {
	label.value       = from.label;
	initial.value     = from.initial;
	statusNodes.value = from.statuses.map((status) => ({ id: id(), name: status, children: [], open: false }));
	moves.value       = from.transitions.map((transition) => ({ id: id(), ...transition, label: transition.label, children: [], open: false, saved: !fresh.value }));
}

function payload(): Record<string, unknown> {
	return {
		label: label.value.trim(),
		statuses: chosen.value,
		initial: initial.value,
		transitions: moves.value.map((move) => ({ name: move.name, label: move.label.trim(), from: move.from, to: move.to, capability: move.capability }))
	};
}

const settled = ref('');
const changed = computed(() => JSON.stringify(payload()) !== settled.value || (fresh.value && named.value));

async function load(): Promise<void> {
	error.value = '';

	try {
		const list = await request<WorkflowList>('GET', '/workflows');

		statuses.value = list.statuses;
		setStatuses(list.statuses);

		if (fresh.value) {
			fill({
				name: '', label: '', statuses: ['draft', 'published'], initial: 'draft', subject: 'entries', source: { kind: 'site', label: '' }, over: null, types: [], editable: true, customize: false,
				transitions: [
					{ name: 'publish', label: t('workflow_view.publish', 'Publish'), from: ['draft'], to: 'published', capability: false },
					{ name: 'unpublish', label: t('workflow_view.unpublish', 'Unpublish'), from: ['published'], to: 'draft', capability: false }
				]
			});
			settled.value = '';

			return;
		}

		const found = await request<WorkflowInfo>('GET', `/workflows/${encodeURIComponent(String(route.params.name))}`);

		workflow.value = found;
		fill(found);
		settled.value = JSON.stringify(payload());
	} catch (caught) {
		error.value = errorMessage(caught, t('workflow_view.not_loaded', 'The workflow couldn\'t be loaded.'));
	}
}

watch(() => route.fullPath, () => {
	workflow.value = null;
	left.value     = 0;
	void load();
}, { immediate: true });

watch([workflow, fresh], () => {
	screenTitle.value = fresh.value ? t('workflow_view.new', 'New Workflow') : (workflow.value?.label ?? null);
}, { immediate: true });

// The registered statuses a workflow may use: not Blush's computed ones.
const usable = computed(() => statuses.value.filter((status) => !['scheduled', 'expired', 'trash'].includes(status.name) && status.subject === 'entries'));

const statusOptions = (node: StatusNode) => [
	...(node.name === '' ? [{ value: '', label: t('workflow_view.choose_status', 'Choose a status') }] : []),
	...usable.value.filter((status) => status.name === node.name || !chosen.value.includes(status.name)).map((status) => ({ value: status.name, label: status.label }))
];

const toOptions = computed(() => chosen.value.map((status) => ({ value: status, label: statusOf(status).label })));
const fromOf    = (move: TransitionNode): string => move.from.length === 0 ? t('workflow_view.any_status', 'Any status') : move.from.map((status) => statusOf(status).label).join(', ');
const moveName  = (move: TransitionNode): string => move.label.trim() || t('workflow_view.new_transition', 'New transition');
const count     = (status: string): number => workflow.value?.counts?.[status] ?? 0;

// A status taken out goes from the transitions too, and the first
// status moves to the first left.
watch(chosen, (now) => {
	moves.value = moves.value
		.filter((move) => move.to === '' || now.includes(move.to))
		.map((move) => ({ ...move, from: move.from.filter((status) => now.includes(status)) }));

	if (!now.includes(initial.value)) {
		initial.value = now[0] ?? '';
	}
});

// What keeps it from saving, said before the server is asked.
const problems = computed(() => {
	const found: string[] = [];
	const names = moves.value.map((move) => move.name);

	if (fresh.value && !/^[a-z][a-z0-9_]*$/.test(name.value)) {
		found.push(t('workflow_view.problems.key', 'Its key is lowercase letters, numbers, and underscores, starting with a letter.'));
	}

	if (chosen.value.length === 0) {
		found.push(t('workflow_view.problems.no_status', 'It needs at least one status.'));
	}

	if (statusNodes.value.some((node) => node.name === '')) {
		found.push(t('workflow_view.problems.unchosen', 'Choose a status for each row, or remove the empty one.'));
	}

	if (moves.value.some((move) => !/^[a-z][a-z0-9_]*$/.test(move.name))) {
		found.push(t('workflow_view.problems.transition_key', 'Each transition\'s key is lowercase letters, numbers, and underscores, starting with a letter.'));
	}

	if (new Set(names).size !== names.length) {
		found.push(t('workflow_view.problems.transition_twice', 'Two transitions have one key.'));
	}

	if (moves.value.some((move) => move.to === '')) {
		found.push(t('workflow_view.problems.no_to', 'Each transition moves to a status.'));
	}

	return found;
});

const statusWords: ItemTreeWords = {
	under: () => '',
	added: () => t('workflow_view.status_added', 'Added a status.'),
	addAbove: t('workflow_view.add_status_above', 'Add status above'),
	addBelow: t('workflow_view.add_status_below', 'Add status below'),
	removedWith: (item) => t('workflow_view.removed', 'Removed {name}', { name: item }),
	showUnder: () => '',
	hideUnder: () => ''
};

const moveWords: ItemTreeWords = {
	under: () => '',
	added: () => t('workflow_view.transition_added', 'Added a transition.'),
	addAbove: t('workflow_view.add_transition_above', 'Add transition above'),
	addBelow: t('workflow_view.add_transition_below', 'Add transition below'),
	removedWith: (item) => t('workflow_view.removed', 'Removed {name}', { name: item }),
	showUnder: () => '',
	hideUnder: () => ''
};

const blankStatus = (): StatusNode => ({ id: id(), name: '', children: [], open: false });
const blankMove   = (): TransitionNode => ({ id: id(), name: '', label: '', from: [], to: chosen.value[0] ?? '', capability: true, saved: false, children: [], open: false });
const copyMove    = (move: TransitionNode): TransitionNode => ({ ...move, id: id(), name: move.name === '' ? '' : `${move.name}_copy`, from: [...move.from], saved: false, children: [], open: false });

// Taking out a status entries still have asks first (D-854).
async function confirmStatus(node: StatusNode): Promise<boolean> {
	const entries = count(node.name);
	const going   = moves.value.filter((move) => move.to === node.name).length;

	if (entries === 0 && going === 0) {
		return true;
	}

	return confirmAction({
		title: t('workflow_view.take_out_title', 'Take {status} Out?', { status: statusOf(node.name).label }),
		body: [
			...(entries > 0 ? [t('workflow_view.take_out_entries', '{count, plural, one {# entry keeps it} other {# entries keep it}} in their files. Once it\'s saved, Site Health lists them, and Reset moves them to the first status.', { count: entries })] : []),
			...(going > 0 ? [t('workflow_view.take_out_moves', '{count, plural, one {The transition to it goes too} other {The # transitions to it go too}}.', { count: going })] : [])
		],
		confirm: t('workflow_view.take_out', 'Take It Out')
	});
}

// A transition's key follows its label until it's saved or typed.
function setLabel(move: TransitionNode, value: string): void {
	const following = !move.saved && (move.name === '' || move.name === keyOf(move.label));

	move.label = value;

	if (following) {
		move.name = keyOf(value);
	}
}

async function save(): Promise<void> {
	if (problems.value.length > 0 || saving.value) {
		return;
	}

	saving.value  = true;
	failure.value = '';

	const gone = (workflow.value?.statuses ?? []).filter((status) => !chosen.value.includes(status)).reduce((sum, status) => sum + count(status), 0);

	try {
		const saved = await request<WorkflowInfo>('PUT', `/workflows/${encodeURIComponent(name.value)}`, payload());

		refreshTypes();
		left.value = gone;
		toast(fresh.value ? t('workflow_view.created', 'Created {label}', { label: saved.label }) : t('workflow_view.saved', 'Updated {label}', { label: saved.label }));

		if (fresh.value) {
			settled.value = JSON.stringify(payload());
			named.value   = false;
			await router.replace({ name: 'workflow', params: { name: saved.name } });

			return;
		}

		workflow.value = saved;
		fill(saved);
		settled.value = JSON.stringify(payload());
	} catch (caught) {
		failure.value = errorMessage(caught, t('workflow_view.not_saved', 'The workflow couldn\'t be saved.'));
	} finally {
		saving.value = false;
	}
}

async function customize(): Promise<void> {
	try {
		const copied = await request<WorkflowInfo>('POST', `/workflows/${encodeURIComponent(name.value)}/customize`);

		workflow.value = copied;
		fill(copied);
		settled.value = JSON.stringify(payload());
		toast(t('workflow_view.customized', 'Copied {label} to the site, to edit', { label: copied.label }));
	} catch (caught) {
		failure.value = errorMessage(caught, t('workflow_view.not_customized', 'The workflow couldn\'t be copied.'));
	}
}

function discard(): void {
	if (workflow.value !== null) {
		fill(workflow.value);
	}
}

async function remove(): Promise<void> {
	const current = workflow.value;

	if (current === null) {
		return;
	}

	const reverting = current.over !== null;

	if (!await confirmAction({
		title: reverting ? t('workflow_view.revert_title', 'Revert {label}?', { label: current.label }) : t('workflow_view.delete_title', 'Delete {label}?', { label: current.label }),
		body: reverting
			? t('workflow_view.revert_body', 'It goes back to {source}\'s, and the site\'s changes to it are gone.', { source: current.over?.label ?? '' })
			: t('workflow_view.delete_body', 'Its file in user/data/workflows is removed.'),
		confirm: reverting ? t('workflow_view.revert', 'Revert the Workflow') : t('workflow_view.delete', 'Delete the Workflow'),
		danger: true
	})) {
		return;
	}

	try {
		const answer = await request<WorkflowInfo | { deleted: true }>('DELETE', `/workflows/${encodeURIComponent(current.name)}`);

		refreshTypes();
		settled.value = JSON.stringify(payload());

		if ('deleted' in answer) {
			toast(t('workflow_view.deleted', 'Deleted {label}', { label: current.label }), { kind: 'danger' });
			await router.push({ name: 'workflows' });

			return;
		}

		workflow.value = answer;
		fill(answer);
		settled.value = JSON.stringify(payload());
		toast(t('workflow_view.reverted', 'Reverted {label}', { label: answer.label }));
	} catch (caught) {
		failure.value = errorMessage(caught, t('workflow_view.not_deleted', 'The workflow couldn\'t be deleted.'));
	}
}

guardLeave(() => editable.value && changed.value);
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'workflows' }"><AdminIcon name="chevron-left" />{{ t('workflow_view.back', 'All workflows') }}</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ fresh ? t('workflow_view.new', 'New Workflow') : (workflow?.label ?? t('workflow_view.title', 'Workflow')) }}</h1>
			<div v-if="workflow || fresh" class="page-facts">
				<span v-if="!fresh" class="page-facts__item"><AdminIcon name="route" /><span class="mono">{{ name }}</span></span>
				<span v-if="!fresh" class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item">{{ t('workflow_view.status_count', '{count, plural, one {# status} other {# statuses} }', { count: chosen.length }) }}</span>
				<span class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item">{{ t('workflow_view.transition_count', '{count, plural, one {# transition} other {# transitions} }', { count: moves.length }) }}</span>
				<template v-if="workflow">
					<span class="page-facts__divider" aria-hidden="true" />
					<span class="page-facts__item"><AdminIcon name="layers" /><template v-if="workflow.types.length"><template v-for="(type, index) in workflow.types" :key="type.name"><template v-if="index">, </template><RouterLink class="lnk" :to="{ name: 'content-type', params: { name: type.name } }">{{ type.label }}</RouterLink></template></template><template v-else>{{ t('workflow_view.no_types', 'No types') }}</template></span>
					<span class="page-facts__divider" aria-hidden="true" />
					<span class="page-facts__item">{{ workflow.over ? t('workflow_view.over', '{site}, over {source}', { site: workflow.source.label, source: workflow.over.label }) : workflow.source.label }}</span>
					<template v-if="!editable">
						<span class="page-facts__divider" aria-hidden="true" />
						<span class="page-facts__item"><AdminIcon name="lock" />{{ t('workflow_view.read_only', 'Read only') }}</span>
					</template>
				</template>
			</div>
		</div>
		<div v-if="workflow || fresh" class="page-header__actions">
			<button v-if="!editable" type="button" class="button button--primary" @click="customize"><AdminIcon name="copy" />{{ t('workflow_view.customize', 'Customize') }}</button>
			<template v-else>
				<button type="button" class="button button--primary" :disabled="!changed || saving || problems.length > 0" @click="save">{{ saving ? t('workflow_view.saving', 'Saving…') : (fresh ? t('workflow_view.create', 'Create Workflow') : (changed ? t('workflow_view.update', 'Update') : t('workflow_view.saved_button', 'Saved'))) }}</button>
				<MenuButton v-if="!fresh" button-class="button button--icon" :label="t('workflow_view.more', 'More actions')" align="end">
					<template #button><AdminIcon name="ellipsis-vertical" /></template>
					<button type="button" class="menu-item" :disabled="!changed" @click="discard"><AdminIcon name="undo-2" />{{ t('workflow_view.discard', 'Revert changes') }}</button>
					<div class="menu-divider" />
					<button type="button" class="menu-item menu-item--danger" @click="remove"><AdminIcon :name="workflow?.over ? 'undo-2' : 'trash-2'" />{{ workflow?.over ? t('workflow_view.revert_to', 'Revert to {source}\'s', { source: workflow.over.label }) : t('workflow_view.delete_menu', 'Delete workflow') }}</button>
				</MenuButton>
			</template>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-if="failure" class="notice notice--error" role="alert">{{ failure }}</p>
	<p v-if="!editable && workflow" class="notice"><AdminIcon name="lock" /><span>{{ t('workflow_view.locked', '{source} defines it. Customize copies it to the site to edit; reverting the copy later brings back theirs.', { source: workflow.source.label }) }}</span></p>
	<p v-if="left > 0" class="notice notice--warn"><AdminIcon name="triangle-alert" /><span><MessageText :text="t('workflow_view.left', '{count, plural, one {# entry keeps a status} other {# entries keep statuses} } it no longer uses. {link} moves them to its first status.', { count: left })"><template #link><RouterLink class="lnk" :to="{ name: 'health-check', params: { area: 'content', check: 'files' } }">{{ t('workflow_view.site_health', 'Site Health') }}</RouterLink></template></MessageText></span></p>
	<div v-if="editable && changed && problems.length" class="notice notice--warn" role="status">
		<AdminIcon name="triangle-alert" />
		<ul class="notice__text">
			<li v-for="problem in problems" :key="problem">{{ problem }}</li>
		</ul>
	</div>

	<template v-if="workflow || fresh">
		<section v-if="editable" class="panel" aria-labelledby="workflow-general-heading">
			<header class="panel__header">
				<h2 id="workflow-general-heading">{{ t('workflow_view.general', 'General') }}</h2>
			</header>
			<div class="panel__body form-stack">
				<div class="field">
					<label for="workflow-label">{{ t('workflow_view.label', 'Label') }}</label>
					<input id="workflow-label" v-model="label" class="input" autocomplete="off">
				</div>
				<div v-if="fresh" class="field">
					<label for="workflow-key">{{ t('workflow_view.key', 'Key') }}</label>
					<input id="workflow-key" :value="name" class="input mono" autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="workflow-key-help" @input="named = true; key = ($event.target as HTMLInputElement).value">
					<p id="workflow-key-help" class="field__help">{{ t('workflow_view.key_help', 'What a type\'s settings name. It can\'t change once it\'s saved.') }}</p>
				</div>
			</div>
		</section>

		<section class="panel" aria-labelledby="workflow-statuses-heading">
			<header class="panel__header">
				<h2 id="workflow-statuses-heading">{{ t('workflow_view.statuses', 'Statuses') }}</h2>
				<p class="panel__hint">{{ t('workflow_view.statuses_hint', 'In the order lists show them. New entries start at the first.') }}</p>
				<div v-if="editable" class="panel__actions">
					<button type="button" class="button button--small" @click="statusTree?.add()"><AdminIcon name="plus" />{{ t('workflow_view.add_status', 'Add Status') }}</button>
				</div>
			</header>
			<ItemTree
				ref="statusTree"
				:nodes="statusNodes"
				:name="(node) => node.name === '' ? t('workflow_view.new_status', 'New status') : statusOf(node.name).label"
				:words="statusWords"
				labelled-by="workflow-statuses-heading"
				:blank="blankStatus"
				:copy="blankStatus"
				:locked="!editable"
				:confirm-remove="confirmStatus"
				flat
			>
				<template #pick="{ placed }">
					<StatusPill v-if="placed.node.name" :status="placed.node.name" />
					<span v-else class="item-tree__title muted">{{ t('workflow_view.choose_status', 'Choose a status') }}</span>
					<span v-if="placed.node.name !== '' && placed.node.name === initial" class="pill pill--accent">{{ t('workflow_view.first', 'First') }}</span>
					<span v-if="count(placed.node.name)" class="muted">{{ t('workflow_view.entries', '{count, plural, one {# entry} other {# entries} }', { count: count(placed.node.name) }) }}</span>
				</template>
				<template #fields="{ placed, done }">
					<div class="item-fields" role="group" :aria-label="t('workflow_view.edit_status', 'Edit the status')">
						<div class="field">
							<label :for="`status-${placed.node.id}`">{{ t('workflow_view.status', 'Status') }}</label>
							<AdminSelect :id="`status-${placed.node.id}`" :model-value="placed.node.name" :options="statusOptions(placed.node)" :disabled="!editable" @update:model-value="placed.node.name = $event" />
						</div>
						<ToggleSwitch :checked="placed.node.name !== '' && placed.node.name === initial" :label="t('workflow_view.first_label', 'New entries start here')" form :locked="!editable || placed.node.name === ''" @change="$event && (initial = placed.node.name)">
							{{ t('workflow_view.first_label', 'New entries start here') }}
							<small>{{ t('workflow_view.first_help', 'And copied ones, and those restored from the trash.') }}</small>
						</ToggleSwitch>
						<div class="item-fields__foot">
							<span class="field__help">{{ t('workflow_view.saves_with', 'Changes save with the workflow.') }}</span>
							<button type="button" class="button button--small" @click="done">{{ t('common.done', 'Done') }}</button>
						</div>
					</div>
				</template>
			</ItemTree>
			<p v-if="editable" class="panel__note"><MessageText :text="t('workflow_view.statuses_note', 'New statuses are made on the {statuses} tab.')"><template #statuses><RouterLink class="lnk" :to="{ name: 'workflows', query: { tab: 'statuses' } }">{{ t('workflow_view.statuses', 'Statuses') }}</RouterLink></template></MessageText></p>
		</section>

		<section class="panel" aria-labelledby="workflow-transitions-heading">
			<header class="panel__header">
				<h2 id="workflow-transitions-heading">{{ t('workflow_view.transitions', 'Transitions') }}</h2>
				<p class="panel__hint">{{ t('workflow_view.transitions_hint', 'The editor\'s buttons, in this order. Moving into or out of a live status also needs publishing.') }}</p>
				<div v-if="editable" class="panel__actions">
					<button type="button" class="button button--small" @click="moveTree?.add()"><AdminIcon name="plus" />{{ t('workflow_view.add_transition', 'Add Transition') }}</button>
				</div>
			</header>
			<ItemTree
				ref="moveTree"
				:nodes="moves"
				:name="moveName"
				:words="moveWords"
				labelled-by="workflow-transitions-heading"
				:blank="blankMove"
				:copy="copyMove"
				:locked="!editable"
				:focus-on-edit="(node) => `move-${node.id}-label`"
				flat
			>
				<template #pick="{ placed }">
					<span class="item-tree__title">{{ moveName(placed.node) }}</span>
					<span class="muted">{{ t('workflow_view.sentence', '{from} to {to}', { from: fromOf(placed.node), to: placed.node.to ? statusOf(placed.node.to).label : '…' }) }}</span>
					<span v-if="placed.node.capability" class="pill">{{ t('workflow_view.own_capability', 'Own Capability') }}</span>
				</template>
				<template #fields="{ placed, done }">
					<div class="item-fields" role="group" :aria-label="t('workflow_view.edit_transition', 'Edit {name}', { name: moveName(placed.node) })">
						<div class="field-pair">
							<div class="field">
								<label :for="`move-${placed.node.id}-label`">{{ t('workflow_view.button_label', 'Label') }}</label>
								<input :id="`move-${placed.node.id}-label`" class="input" :value="placed.node.label" autocomplete="off" :readonly="!editable" :aria-describedby="`move-${placed.node.id}-label-help`" @input="setLabel(placed.node, ($event.target as HTMLInputElement).value)">
								<p :id="`move-${placed.node.id}-label-help`" class="field__help">{{ t('workflow_view.button_label_help', 'Its button\'s words, in Title Case.') }}</p>
							</div>
							<div class="field">
								<label :for="`move-${placed.node.id}-key`">{{ t('workflow_view.transition_key', 'Key') }}</label>
								<input :id="`move-${placed.node.id}-key`" v-model="placed.node.name" class="input mono" autocomplete="off" autocapitalize="none" spellcheck="false" :readonly="!editable || placed.node.saved" :aria-describedby="`move-${placed.node.id}-key-help`">
								<p :id="`move-${placed.node.id}-key-help`" class="field__help">{{ placed.node.saved ? t('workflow_view.key_fixed', 'Fixed: roles name its capability by it.') : t('workflow_view.transition_key_help', 'Roles name its capability by it, so it can\'t change once it\'s saved.') }}</p>
							</div>
						</div>
						<div class="field-pair">
							<div class="field">
								<span :id="`move-${placed.node.id}-from-label`" class="field__label">{{ t('workflow_view.from', 'From') }}</span>
								<ChoiceChips :id="`move-${placed.node.id}-from`" :model-value="placed.node.from.join('\n')" :options="chosen" :label-of="(status) => statusOf(status).label" :name="t('workflow_view.from', 'From')" :labelled-by="`move-${placed.node.id}-from-label`" :described-by="`move-${placed.node.id}-from-help`" :disabled="!editable" @update:model-value="placed.node.from = $event.split('\n').filter((status) => status !== '')" />
								<p :id="`move-${placed.node.id}-from-help`" class="field__help">{{ t('workflow_view.from_help', 'None for any of its statuses.') }}</p>
							</div>
							<div class="field">
								<label :for="`move-${placed.node.id}-to`">{{ t('workflow_view.to', 'To') }}</label>
								<AdminSelect :id="`move-${placed.node.id}-to`" v-model="placed.node.to" :options="toOptions" :disabled="!editable" />
							</div>
						</div>
						<ToggleSwitch :checked="placed.node.capability" :label="t('workflow_view.capability', 'Needs a capability of its own')" form :locked="!editable" @change="placed.node.capability = $event">
							{{ t('workflow_view.capability', 'Needs a capability of its own') }}
							<small>{{ t('workflow_view.capability_help', 'Roles give it on each type, under its sections. Without one, editing is enough, and publishing for what\'s live.') }}</small>
						</ToggleSwitch>
						<div class="item-fields__foot">
							<span class="field__help">{{ t('workflow_view.saves_with', 'Changes save with the workflow.') }}</span>
							<button type="button" class="button button--small" @click="done">{{ t('common.done', 'Done') }}</button>
						</div>
					</div>
				</template>
			</ItemTree>
		</section>
	</template>

	<div v-else-if="!error" class="panel" aria-hidden="true"><div class="panel__body"><span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" /></div></div>
</template>
