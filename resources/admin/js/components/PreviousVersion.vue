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
import { errorMessage, refreshIfAsked, request, type RollbackAnswer } from '../api';
import { confirmAction } from '../confirm';
import { KIND_PATHS } from '../extensions';
import { can } from '../session';
import { toast } from '../toast';
import { t } from '../messages';
import MessageText from './MessageText.vue';

const props = defineProps<{
	kind: InstallKind;
	extension: { name: string; label: string; version: string; backup: { version: string } | null };
	// Whether it runs on the site: the active theme (or one it falls back to), or on.
	live: boolean;
}>();

const emit = defineEmits<{
	changed: [];
}>();

const kept       = computed(() => props.extension.backup?.version || t('previous_version.earlier', 'An earlier version'));
const canRollBack = computed(() => can(`extensions.${KIND_PATHS[props.kind]}.update`));
const canDiscard = computed(() => can(`extensions.${KIND_PATHS[props.kind]}.delete`));

// What rolling back changes at once, for one that runs.
function now(to: string): string {
	return props.kind === 'theme'
		? t('previous_version.now_theme', 'It\'s in use, so visitors see {to} as soon as it\'s done.', { to })
		: (props.kind === 'plugin' ? t('previous_version.now_plugin', 'It\'s on, so the site runs {to} as soon as it\'s done.', { to }) : t('previous_version.now_icons', 'It\'s on, so its icons change as soon as it\'s done.'));
}

async function rollBack(ask = true): Promise<void> {
	const { label, version } = props.extension;
	const to                 = kept.value;
	const from               = version || t('previous_version.installed', 'the installed version');

	if (ask && !await confirmAction({
		title: t('previous_version.roll_back_title', 'Roll Back {label} to {to}?', { label, to }),
		body: [
			t('previous_version.roll_back_body', '{label} {from} is swapped for {to}, the version kept from before it was last replaced. {from} is kept in its place, so you can switch back.', { label, from, to }),
			...(props.live ? [now(to)] : [])
		],
		confirm: t('previous_version.roll_back_to', 'Roll Back to {to}', { to })
	})) {
		return;
	}

	try {
		const answer = await request<RollbackAnswer>('POST', `/${KIND_PATHS[props.kind]}/${props.extension.name}/rollback`);

		await refreshIfAsked(answer);
		emit('changed');
		toast(t('previous_version.rolled_back', 'Rolled back {label} to {to}', { label, to: answer.rolledBack.version || to }), {
			undo: ask ? () => void rollBack(false) : undefined
		});
	} catch (caught) {
		toast(errorMessage(caught, t('previous_version.errors.roll_back', '{label} couldn\'t be rolled back.', { label })), { kind: 'warn' });
	}
}

async function discard(): Promise<void> {
	const { label } = props.extension;

	if (!await confirmAction({
		title: t('previous_version.discard_title', 'Discard {label} {version}?', { label, version: kept.value }),
		body: t('previous_version.discard_body', 'The kept folder is removed from the server, so {label} can\'t be rolled back to it. This can\'t be undone.', { label }),
		confirm: t('previous_version.discard_version', 'Discard {version}', { version: kept.value }),
		danger: true
	})) {
		return;
	}

	try {
		await request('DELETE', `/${KIND_PATHS[props.kind]}/${props.extension.name}/backup`);
		emit('changed');
		toast(t('previous_version.discarded', 'Discarded {label} {version}', { label, version: kept.value }), { kind: 'danger' });
	} catch (caught) {
		toast(errorMessage(caught, t('previous_version.errors.discard', '{label} {version} couldn\'t be discarded.', { label, version: kept.value })), { kind: 'warn' });
	}
}
</script>

<template>
	<div v-if="extension.backup" class="previous">
		<AdminIcon name="undo-2" />
		<p><MessageText :text="t('previous_version.kept', '{version} is kept from before {label} was last replaced.', { label: extension.label })"><template #version><b>{{ kept }}</b></template></MessageText></p>
		<span class="previous__actions">
			<button v-if="canDiscard" type="button" class="button button--ghost" @click="discard">{{ t('previous_version.discard', 'Discard') }}</button>
			<button v-if="canRollBack" type="button" class="button" @click="rollBack()">{{ t('previous_version.roll_back_to', 'Roll Back to {to}', { to: kept }) }}</button>
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
	padding: var(--s-4) var(--padding-inline);
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-large);
	background: var(--color-surface);
}

.previous > :deep(svg) {
	flex: none;
	width: 16px;
	height: 16px;
	color: var(--color-text-muted);
}

.previous p {
	margin: 0;
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.previous b {
	color: var(--color-text);
	font-weight: 600;
}

.previous__actions {
	display: flex;
	gap: var(--s-2);
	margin-left: auto;
}
</style>
