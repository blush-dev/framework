<script setup lang="ts">
/**
 * The version of a folder extension that replacing it kept (D-393), on
 * its details screen: **Roll back** swaps it in, after asking, and keeps
 * the version it replaces, so the toast's Undo rolls back again;
 * **Discard** removes it. Rolling back needs the kind's `update`
 * capability, and discarding its `delete`.
 */

import { computed } from 'vue';
import AdminIcon from './AdminIcon.vue';
import type { InstallKind } from './InstallModal.vue';
import { ApiError, request, type RollbackAnswer } from '../api';
import { confirmAction } from '../confirm';
import { can } from '../session';
import { toast } from '../toast';

const props = defineProps<{
	kind: InstallKind;
	extension: { name: string; label: string; version: string; backup: { version: string } | null };
	// Whether it runs on the site: the active theme (or one it falls back to), or on.
	live: boolean;
}>();

const emit = defineEmits<{
	changed: [];
}>();

const PATHS: Record<InstallKind, { path: string; capability: string }> = {
	'theme': { path: '/themes', capability: 'extensions.themes' },
	'plugin': { path: '/plugins', capability: 'extensions.plugins' },
	'icon-pack': { path: '/icon-packs', capability: 'extensions.icon-packs' }
};

const kept       = computed(() => props.extension.backup?.version || 'An earlier version');
const canRollBack = computed(() => can(`${PATHS[props.kind].capability}.update`));
const canDiscard = computed(() => can(`${PATHS[props.kind].capability}.delete`));

// What rolling back changes at once, for one that runs.
function now(to: string): string {
	return props.kind === 'theme'
		? `It's in use, so visitors see ${to} as soon as it's done.`
		: (props.kind === 'plugin' ? `It's on, so the site runs ${to} as soon as it's done.` : `It's on, so its icons change as soon as it's done.`);
}

async function rollBack(ask = true): Promise<void> {
	const { label, version } = props.extension;
	const to                 = kept.value;
	const from               = version || 'the installed version';

	if (ask && !await confirmAction({
		title: `Roll Back ${label} to ${to}?`,
		body: [
			`${label} ${from} is swapped for ${to}, the version kept from before it was last replaced. ${from} is kept in its place, so you can switch back.`,
			...(props.live ? [now(to)] : [])
		],
		confirm: `Roll back to ${to}`
	})) {
		return;
	}

	try {
		const answer = await request<RollbackAnswer>('POST', `${PATHS[props.kind].path}/${props.extension.name}/rollback`);

		if (answer.refresh) {
			await request('POST', '/settings/refresh').catch(() => undefined);
		}

		emit('changed');
		toast(`Rolled back ${label} to ${answer.rolledBack.version || to}`, {
			undo: ask ? () => void rollBack(false) : undefined
		});
	} catch (caught) {
		toast(caught instanceof ApiError ? caught.message : `${label} couldn't be rolled back.`, { kind: 'warn' });
	}
}

async function discard(): Promise<void> {
	const { label } = props.extension;

	if (!await confirmAction({
		title: `Discard ${label} ${kept.value}?`,
		body: `The kept folder is removed from the server, so ${label} can't be rolled back to it. This can't be undone.`,
		confirm: `Discard ${kept.value}`,
		danger: true
	})) {
		return;
	}

	try {
		await request('DELETE', `${PATHS[props.kind].path}/${props.extension.name}/backup`);
		emit('changed');
		toast(`Discarded ${label} ${kept.value}`, { kind: 'danger' });
	} catch (caught) {
		toast(caught instanceof ApiError ? caught.message : `${label} ${kept.value} couldn't be discarded.`, { kind: 'warn' });
	}
}
</script>

<template>
	<div v-if="extension.backup" class="previous">
		<AdminIcon name="undo-2" />
		<p><b>{{ kept }}</b> is kept from before {{ extension.label }} was last replaced.</p>
		<span class="previous__actions">
			<button v-if="canDiscard" type="button" class="button button--ghost" @click="discard">Discard</button>
			<button v-if="canRollBack" type="button" class="button" @click="rollBack()">Roll back to {{ kept }}</button>
		</span>
	</div>
</template>

<style scoped>
.previous {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-3);
	margin-bottom: var(--s-4);
	padding: var(--s-4) var(--pad-x);
	border: 1px solid var(--border);
	border-radius: var(--r-3);
	background: var(--surface);
}

.previous > :deep(svg) {
	flex: none;
	width: 16px;
	height: 16px;
	color: var(--fg-3);
}

.previous p {
	margin: 0;
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.previous b {
	color: var(--fg);
	font-weight: 600;
}

.previous__actions {
	display: flex;
	gap: var(--s-2);
	margin-left: auto;
}
</style>
