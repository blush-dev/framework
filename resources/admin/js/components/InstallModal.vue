<script setup lang="ts">
/**
 * Installing an extension from a .zip (D-392, the extensions sketch's
 * uploader), the same for themes, plugins, and icon packs. Its anatomy is
 * the media library's Upload tab: a drop zone and a button, with the
 * limits stated before anyone tries. A file dropped anywhere on the modal
 * counts.
 *
 * One thing fills the zone's place at a time: the file being sent (with
 * how much has gone), what was installed (with the one next step, Activate
 * or Turn on, a warning beneath when it's abandoned, D-433, and what it
 * suggests, D-434), an
 * installed one with the archive's name (with Replace, naming both
 * versions), or why nothing was installed (with Choose another file, and
 * the right screen for an archive of another kind).
 * Nothing is written until the server has checked the archive, so every
 * failure says nothing was installed.
 */

import { computed, ref, useTemplateRef, watch } from 'vue';
import AdminIcon from './AdminIcon.vue';
import AdminModal from './AdminModal.vue';
import MessageText from './MessageText.vue';
import { ApiError, errorMessage, uploadWithProgress, type ExtensionUpload, type InstallAnswer, type InstallClash } from '../api';
import { useFileDrop } from '../drop';
import type { ExtensionKind } from '../extensions';
import { t } from '../messages';
import { can } from '../session';

export type InstallKind = ExtensionKind;

// What an installed extension is on this site: in a few words ("inactive",
// "turned off"), whether the next step can be taken, and whether it runs.
export interface InstallState {
	words: string;
	next: boolean;
	live: boolean;
}

const props = defineProps<{
	kind: InstallKind;
	open: boolean;
	upload: ExtensionUpload | null;
	state: (name: string) => InstallState;
}>();

const emit = defineEmits<{
	close: [];
	installed: [answer: InstallAnswer];
	next: [name: string];
	hop: [kind: InstallKind];
}>();

const KINDS: Record<InstallKind, { title: string; noun: string; plural: string; path: string; capability: string; drag: string; next: string; after: string }> = {
	'theme': { title: t('install_modal.theme.title', 'Install Theme'), noun: t('install_modal.theme.noun', 'theme'), plural: t('install_modal.theme.plural', 'Themes'), path: '/themes', capability: 'extensions.themes', drag: t('install_modal.theme.drag', 'Drag a Theme Here'), next: t('install_modal.theme.next', 'Activate'), after: t('install_modal.theme.after', 'and nothing is activated') },
	'plugin': { title: t('install_modal.plugin.title', 'Install Plugin'), noun: t('install_modal.plugin.noun', 'plugin'), plural: t('install_modal.plugin.plural', 'Plugins'), path: '/plugins', capability: 'extensions.plugins', drag: t('install_modal.plugin.drag', 'Drag a Plugin Here'), next: t('install_modal.plugin.next', 'Turn On'), after: t('install_modal.plugin.after', 'and arrives turned off') },
	'icon-pack': { title: t('install_modal.icon_pack.title', 'Install Icon Pack'), noun: t('install_modal.icon_pack.noun', 'icon pack'), plural: t('install_modal.icon_pack.plural', 'Icon Packs'), path: '/icon-packs', capability: 'extensions.icon-packs', drag: t('install_modal.icon_pack.drag', 'Drag an Icon Pack Here'), next: t('install_modal.icon_pack.next', 'Turn On'), after: t('install_modal.icon_pack.after', 'and arrives turned off') },
	'admin-theme': { title: t('install_modal.admin_theme.title', 'Install Admin Theme'), noun: t('install_modal.admin_theme.noun', 'admin theme'), plural: t('install_modal.admin_theme.plural', 'Admin Themes'), path: '/admin-themes', capability: 'extensions.admin-themes', drag: t('install_modal.admin_theme.drag', 'Drag an Admin Theme Here'), next: t('install_modal.admin_theme.next', 'Turn On'), after: t('install_modal.admin_theme.after', 'and arrives turned off') }
};

type Phase = 'idle' | 'working' | 'done' | 'clash' | 'error';

const k        = computed(() => KINDS[props.kind]);
const input    = useTemplateRef<HTMLInputElement>('input');
const phase    = ref<Phase>('idle');
const file     = ref<File | null>(null);
const sent     = ref(0);
const answer   = ref<InstallAnswer | null>(null);
const clash    = ref<InstallClash | null>(null);
const failure  = ref<{ message: string; kind: InstallKind | null } | null>(null);

const limit      = computed(() => props.upload?.limit ?? 0);
const problem    = computed(() => props.upload?.problem ?? null);
const canReplace = computed(() => can(`${k.value.capability}.update`));
const done       = computed(() => answer.value === null ? null : props.state(answer.value.installed.name));
const clashState = computed(() => clash.value === null ? null : props.state(clash.value.installed.name));

// Starts again each time it opens.
watch(() => props.open, (open) => {
	if (open) {
		phase.value   = 'idle';
		file.value    = null;
		answer.value  = null;
		clash.value   = null;
		failure.value = null;
	}
});

function size(bytes: number): string {
	return bytes < 1024 * 1024 ? t('install_modal.kb', '{size} KB', { size: Math.max(1, Math.round(bytes / 1024)) }) : t('install_modal.mb', '{size} MB', { size: (bytes / 1024 / 1024).toFixed(1).replace(/\.0$/, '') });
}

function pick(): void {
	if (input.value !== null) {
		input.value.value = '';
		input.value.click();
	}
}

// A file chosen or dropped: what can be told before sending it is.
function choose(chosen: File | undefined): void {
	if (chosen === undefined || phase.value === 'working') {
		return;
	}

	file.value    = chosen;
	failure.value = null;
	clash.value   = null;

	if (!chosen.name.toLowerCase().endsWith('.zip')) {
		fail(t('install_modal.errors.not_zip', '{name} isn\'t a .zip file.', { name: chosen.name }));
	} else if (limit.value > 0 && chosen.size > limit.value) {
		fail(t('install_modal.errors.too_large', '{name} is {size}, larger than the {limit} the site takes.', { name: chosen.name, size: size(chosen.size), limit: size(limit.value) }));
	} else {
		phase.value = 'idle';
	}
}

function fail(message: string, kind: InstallKind | null = null): void {
	failure.value = { message, kind: kind !== null && kind !== props.kind ? kind : null };
	phase.value   = 'error';
}

function picked(event: Event): void {
	choose((event.target as HTMLInputElement).files?.[0]);
}

const { dragging, over: dragOver, leave: dragLeave, drop: dropped } = useFileDrop((files) => choose(files[0]));

async function install(replace = false): Promise<void> {
	if (file.value === null) {
		return;
	}

	phase.value = 'working';
	sent.value  = 0;

	try {
		answer.value = await uploadWithProgress<InstallAnswer>(k.value.path, file.value, replace ? { replace: '1' } : {}, (part) => {
			sent.value = part;
		});
		phase.value = 'done';
		emit('installed', answer.value);
	} catch (caught) {
		const data = caught instanceof ApiError && typeof caught.data === 'object' && caught.data !== null ? caught.data as Record<string, unknown> : {};

		if (caught instanceof ApiError && caught.status === 409 && typeof data.clash === 'object' && data.clash !== null) {
			clash.value = data.clash as InstallClash;
			phase.value = 'clash';
		} else {
			fail(errorMessage(caught, t('install_modal.errors.not_sent', 'The file couldn\'t be sent.')), typeof data.kind === 'string' && data.kind in KINDS ? data.kind as InstallKind : null);
		}
	}
}

// What a replaced extension keeps, when it runs.
const keeps = computed(() => {
	if (clashState.value === null || !clashState.value.live) {
		return 'none';
	}

	return props.kind === 'theme' ? 'theme' : 'other';
});

const replaceLabel = computed(() => {
	const from = clash.value?.installed.version ?? '';
	const to   = clash.value?.incoming.version ?? '';

	return from !== '' && to !== '' ? t('install_modal.replace_versions', 'Replace {from} with {to}', { from, to }) : t('install_modal.replace_it', 'Replace It');
});
</script>

<template>
	<AdminModal :open="open" :title="k.title" @close="phase !== 'working' && emit('close')" @dragover="dragOver" @dragleave="dragLeave" @drop="dropped">
		<p v-if="phase !== 'done'">
			<MessageText :text="t('install_modal.intro', '{plural} are folders in {folder}, each at its name ({path}). One published as a package is installed with {command} instead, and Composer owns it from then on.', { plural: k.plural })"><template #folder><code>extensions/</code></template><template #path><code>extensions/vendor/name</code></template><template #command><code>composer require vendor/name</code></template></MessageText>
		</p>

		<template v-if="phase === 'idle'">
			<p v-if="problem" class="notice notice--warn"><span>{{ t('install_modal.problem', '{problem} Nothing can be installed here until that\'s fixed.', { problem }) }}</span></p>
			<div class="drop" :class="{ 'is-over': dragging }">
				<AdminIcon name="upload" />
				<p class="drop__heading">{{ k.drag }}</p>
				<p class="drop__text"><MessageText :text="t('install_modal.drop_text', 'A .zip of the {noun} folder. It\'s unpacked into {folder} at the name in its manifest, {after}.', { noun: k.noun, after: k.after })"><template #folder><code>extensions/</code></template></MessageText></p>
				<button type="button" class="button button--primary" :disabled="problem !== null" @click="pick">{{ t('install_modal.choose_file', 'Choose File') }}</button>
				<p v-if="limit > 0" class="drop__hint">{{ t('install_modal.limit', 'Up to {size} · ZIP', { size: size(limit) }) }}</p>
			</div>
		</template>

		<div v-else-if="phase === 'working' && file" class="slab" role="status">
			<p class="slab__head"><span class="spin" aria-hidden="true" />{{ sent < 1 ? t('install_modal.uploading', 'Uploading {name}…', { name: file.name }) : t('install_modal.unpacking', 'Unpacking and checking it…') }}</p>
			<div class="bar"><i :style="{ width: `${Math.round(sent * 100)}%` }" /></div>
			<p><b>{{ file.name }}</b> · {{ size(file.size) }}</p>
		</div>

		<div v-else-if="phase === 'done' && answer && file" class="slab slab--good" role="status">
			<p class="slab__head"><AdminIcon name="circle-check" />{{ answer.replaced === null ? t('install_modal.installed_named', 'Installed {label} {version}', { label: answer.installed.label, version: answer.installed.version }) : t('install_modal.updated_named', 'Updated {label} {version}', { label: answer.installed.label, version: answer.installed.version }) }}</p>
			<p v-if="answer.replaced === null">{{ t('install_modal.in_list', 'It\'s in the list, {words}, until you say otherwise.', { words: done?.words }) }}</p>
			<p v-else>{{ t('install_modal.replaced', 'The folder was replaced. Nothing else changed: it\'s still {words}.', { words: done?.words }) }}</p>
			<dl>
				<dt>{{ t('install_modal.folder', 'Folder') }}</dt>
				<dd class="mono">{{ answer.installed.folder }}</dd>
				<dt>{{ t('install_modal.from', 'From') }}</dt>
				<dd>{{ file.name }} · {{ size(file.size) }}</dd>
			</dl>
		</div>

		<div v-else-if="phase === 'clash' && clash" class="slab slab--warn">
			<p class="slab__head"><AdminIcon name="triangle-alert" />{{ t('install_modal.already_installed', '{label} is already installed', { label: clash.installed.label }) }}</p>
			<p><MessageText :text="t('install_modal.overwrites', '{keeps, select, theme {Replacing overwrites {folder}. Nothing else changes: it stays the active theme, and the site serves the new files at once.} other {Replacing overwrites {folder}. Nothing else changes, and it stays turned on, so the new version runs at once.} none {Replacing overwrites {folder}. Nothing else changes.} }', { keeps })"><template #folder><span class="mono">{{ clash.installed.folder }}</span></template></MessageText></p>
			<dl>
				<dt>{{ t('install_modal.installed', 'Installed') }}</dt>
				<dd class="mono">{{ clash.installed.version || t('install_modal.no_version', 'No version') }}</dd>
				<dt>{{ t('install_modal.this_file', 'This file') }}</dt>
				<dd class="mono">{{ clash.incoming.version || t('install_modal.no_version', 'No version') }}</dd>
			</dl>
		</div>

		<div v-else-if="phase === 'error' && failure" class="slab slab--bad" role="alert">
			<p class="slab__head"><AdminIcon name="triangle-alert" />{{ t('install_modal.nothing_installed', 'Nothing was installed') }}</p>
			<p>{{ failure.message }}</p>
			<p v-if="failure.kind">{{ t('install_modal.other_screen', 'Install it on the {plural} screen instead.', { plural: KINDS[failure.kind].plural }) }}</p>
		</div>

		<!-- Abandoned only warns, as in Composer (D-433). -->
		<div v-if="phase === 'done' && answer && answer.installed.abandoned !== false" class="slab slab--warn">
			<p class="slab__head"><AdminIcon name="triangle-alert" />{{ t('install_modal.abandoned', '{label} is abandoned', { label: answer.installed.label }) }}</p>
			<p>{{ t('install_modal.abandoned_text', 'Its author no longer maintains it. It works, but won\'t get fixes or updates.') }}<template v-if="typeof answer.installed.abandoned === 'string'">{{ ' ' }}<MessageText :text="t('install_modal.abandoned_suggests', 'Its author suggests {name} instead.')"><template #name><span class="mono">{{ answer.installed.abandoned }}</span></template></MessageText></template></p>
		</div>

		<!-- Suggestions are only shown, as Composer prints them after installing (D-434). -->
		<div v-if="phase === 'done' && answer && answer.installed.suggests.length" class="slab">
			<p class="slab__head"><AdminIcon name="lightbulb" />{{ t('install_modal.suggests', '{label} suggests', { label: answer.installed.label }) }}</p>
			<p>{{ t('install_modal.suggests_text', 'It works without these; each may add to it.') }}</p>
			<dl>
				<template v-for="suggestion in answer.installed.suggests" :key="suggestion.name">
					<dt class="mono">{{ suggestion.name }}</dt>
					<dd>{{ suggestion.reason || '—' }}</dd>
				</template>
			</dl>
		</div>

		<input ref="input" type="file" accept=".zip,application/zip" hidden @change="picked">

		<template #footer>
			<span class="install-status">
				<template v-if="phase === 'working'">{{ t('install_modal.installing', 'Installing…') }}</template>
				<template v-else-if="phase === 'clash' && !canReplace">{{ t('install_modal.cant_replace', 'Your role can\'t replace {plural}.', { plural: k.plural.toLowerCase() }) }}</template>
				<template v-else-if="(phase === 'idle' || phase === 'clash') && file"><b>{{ file.name }}</b> · {{ size(file.size) }}</template>
				<template v-else-if="phase === 'idle'">{{ t('install_modal.choose_a_file', 'Choose a file.') }}</template>
			</span>

			<template v-if="phase === 'done' && answer">
				<button type="button" class="button" :autofocus="!done?.next" @click="emit('close')">{{ t('common.done', 'Done') }}</button>
				<button v-if="done?.next" type="button" class="button button--primary" autofocus @click="emit('next', answer.installed.name)">{{ t('install_modal.next', '{action} {label}', { action: k.next, label: answer.installed.label }) }}</button>
			</template>
			<template v-else-if="phase === 'error' && failure">
				<button type="button" class="button" @click="emit('close')">{{ t('common.cancel', 'Cancel') }}</button>
				<button v-if="failure.kind && can(`${KINDS[failure.kind].capability}.view`)" type="button" class="button" @click="emit('hop', failure.kind)">{{ t('install_modal.go_to', 'Go to {plural}', { plural: KINDS[failure.kind].plural }) }}</button>
				<button type="button" class="button button--primary" autofocus @click="pick">{{ t('install_modal.choose_another', 'Choose Another File') }}</button>
			</template>
			<template v-else-if="phase === 'clash'">
				<button type="button" class="button" autofocus @click="emit('close')">{{ t('common.cancel', 'Cancel') }}</button>
				<button type="button" class="button button--primary" :disabled="!canReplace" @click="install(true)">{{ replaceLabel }}</button>
			</template>
			<template v-else>
				<button type="button" class="button" :disabled="phase === 'working'" autofocus @click="emit('close')">{{ t('common.cancel', 'Cancel') }}</button>
				<button type="button" class="button button--primary" :disabled="file === null || phase === 'working' || problem !== null" @click="install()">
					<span v-if="phase === 'working'" class="spin" aria-hidden="true" />{{ t('install_modal.install', 'Install') }}
				</button>
			</template>
		</template>
	</AdminModal>
</template>

<style scoped>
/* The drop zone, then the button, with the limits stated before anyone
   tries. The button has no icon: the large one above already says it. */
.drop {
	display: grid;
	justify-items: center;
	gap: var(--s-2);
	margin-top: var(--s-4);
	padding: var(--s-6) var(--s-5);
	border: 1px dashed var(--border-color-strong);
	border-radius: var(--border-radius-medium);
	background: var(--color-surface-subtle);
	text-align: center;
	transition: border-color .14s ease-out, background .14s ease-out;
}

.drop.is-over {
	border-color: var(--color-accent);
	background: var(--color-accent-subtle);
}

.drop > :deep(svg) {
	width: 24px;
	height: 24px;
	margin-bottom: var(--s-1);
	color: var(--color-text-muted);
}

.drop__heading {
	margin: 0;
	font-family: var(--font-family-display);
	font-weight: var(--font-weight-display);
}

.drop__text {
	max-width: 40ch;
	margin: 0 0 var(--s-2);
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.drop__hint {
	margin: var(--s-1) 0 0;
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}

/* Working, done, and failed each take the zone's place. */
.slab {
	margin-top: var(--s-4);
	padding: var(--s-5);
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-medium);
	background: var(--color-surface-subtle);
}

.slab--good {
	border-color: var(--color-success-indicator);
	background: var(--color-success-subtle);
}

.slab--warn {
	border-color: var(--color-warning-indicator);
	background: var(--color-warning-subtle);
}

.slab--bad {
	border-color: var(--color-danger-indicator);
	background: var(--color-danger-subtle);
}

.slab p {
	margin: 0 0 var(--s-2);
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.slab p:last-child {
	margin-bottom: 0;
}

.slab .slab__head {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	color: var(--color-text);
	font-size: var(--font-size-base);
	font-weight: 600;
}

.slab--good .slab__head {
	color: var(--color-success);
}

.slab--warn .slab__head {
	color: var(--color-warning);
}

.slab--bad .slab__head {
	color: var(--color-danger);
}

.slab__head :deep(svg) {
	flex: none;
	width: 15px;
	height: 15px;
}

.slab dl {
	display: grid;
	grid-template-columns: auto 1fr;
	gap: var(--s-1) var(--s-4);
	margin: var(--s-3) 0 0;
	font-size: var(--font-size-small);
}

.slab dt {
	color: var(--color-text-muted);
}

.slab dd {
	margin: 0;
	color: var(--color-text);
}

.bar {
	height: 5px;
	margin: var(--s-3) 0 var(--s-2);
	overflow: hidden;
	border-radius: 999px;
	background: var(--color-surface-strong);
}

.bar i {
	display: block;
	height: 100%;
	border-radius: 999px;
	background: var(--color-accent);
	transition: width .3s ease-out;
}

.install-status {
	min-width: 0;
	margin-right: auto;
	overflow: hidden;
	color: var(--color-text-muted);
	font-size: var(--font-size-small);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.install-status b {
	color: var(--color-text);
	font-weight: 500;
}
</style>
