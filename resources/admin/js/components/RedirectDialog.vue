<script setup lang="ts">
/**
 * Adding and editing a redirect (D-686; the sketch's R9 and R10), in one
 * small modal over the list: **From**, **To**, and **Type**, stacked.
 *
 * The server checks the form as it's filled in (`redirects/check`), so
 * what it says knows the site: what stops a save (a missing slash, a
 * duplicate, a loop, a placeholder with nothing to fill it) is a short
 * red line under its field, shown once the field's been left or Save
 * pressed; warnings and what will happen are gathered in one notice
 * above the buttons. Save stays pressable and marks what to fix.
 *
 * **To** is one box (`LinkPicker`, which menu items share): a path, a
 * whole address on another site, or part of a page's name, whose
 * matching pages open in a list under it (arrows move, Enter picks). A
 * picked page sits in the box as a token and is kept by id, so the
 * redirect follows it; its × or Backspace puts the typing back. Escape
 * closes the list first, then the modal.
 */

import { computed, nextTick, ref, watch } from 'vue';
import { ApiError, errorMessage, request, type EntryList } from '../api';
import { debounced, latest } from '../action';
import { siteDateTime } from '../dates';
import { checkRedirect, saveRedirect, TYPES, type RedirectCheck, type RedirectMessage as Message, type RedirectRow, type RedirectStatus } from '../redirects';
import { toast } from '../toast';
import AdminIcon from './AdminIcon.vue';
import AdminModal from './AdminModal.vue';
import AdminSelect, { type SelectOption } from './AdminSelect.vue';
import LinkPicker, { type LinkOption } from './LinkPicker.vue';
import RedirectAdded from './RedirectAdded.vue';
import RedirectMessage from './RedirectMessage.vue';

const props = defineProps<{
	// The row being changed, or `null` for a new one.
	row: RedirectRow | null;
	// A new one's old path, from a test that found nothing there.
	from?: string;
	// Opens with To cleared and its list open: Choose Another Page.
	repick?: boolean;
}>();

const emit = defineEmits<{
	close: [];
	saved: [row: RedirectRow, was: RedirectRow['stored'] | null];
	delete: [row: RedirectRow];
	// Edit That One: the row from the same old path.
	other: [from: string];
}>();

const from   = ref(props.row?.from ?? props.from ?? '');
const to     = ref(props.repick ? '' : props.row?.to ?? '');
const entry  = ref<{ id: string; title: string; url: string | null } | null>(props.repick || !props.row?.entry ? null : { id: props.row.entry.id, title: props.row.entry.title || 'Untitled', url: props.row.entry.url });
const status = ref(String(props.row?.status ?? 301));

const touched = ref({ from: props.from !== undefined, to: false });
const tried   = ref(false);
const checked = ref<RedirectCheck | null>(null);
const saving  = ref(false);
const failed  = ref('');

const fromField = ref<HTMLInputElement | null>(null);

const STATUS_OPTIONS: SelectOption[] = ([301, 302, 303, 307, 308] as RedirectStatus[]).map((code) => ({
	value: String(code),
	label: `${TYPES[code].name} · ${code}`,
	group: code === 301 || code === 302 ? null : 'For Programs'
}));

const draft = computed(() => ({
	from: from.value,
	to: entry.value === null ? to.value : '',
	entry: entry.value?.id ?? null,
	status: Number(status.value) as RedirectStatus,
	was: props.row?.from ?? null
}));

// Only the latest check's answer is shown.
const ask   = latest();
const check = debounced(async () => {
	const current = ask();

	try {
		const answer = await checkRedirect(draft.value);

		if (current()) {
			checked.value = answer;
		}
	} catch {
		// The save checks again, and says what failed.
	}
}, 250);

watch(draft, () => check(), { deep: true, immediate: true });

// A field's red lines: once it's been left, or Save pressed, or when
// there's a fix to offer right away.
function bad(field: 'from' | 'to'): Message[] {
	return (checked.value?.[field] ?? []).filter((message) => message.kind === 'bad' && (tried.value || touched.value[field] || message.fix !== null));
}

const notes  = computed(() => [...(checked.value?.from ?? []), ...(checked.value?.to ?? [])].filter((message) => message.kind !== 'bad'));
const warned = computed(() => notes.value.some((message) => message.kind === 'warn'));
const errors = computed(() => [...(checked.value?.from ?? []), ...(checked.value?.to ?? []), ...(checked.value?.status ?? [])].filter((message) => message.kind === 'bad').length);

const lead = computed(() => props.row === null
	? 'Visitors to the old address are sent to the new one, unless a page answers the old address first.'
	: 'Changes apply as soon as you save.');

/*
 * The pages To offers: published entries matching what's typed, unless
 * it's a whole address (`LinkPicker`).
 */
async function findPages(text: string): Promise<LinkOption[]> {
	if (/^https?:\/\//i.test(text)) {
		return [];
	}

	const params = new URLSearchParams({ status: 'published', per: '6' });

	if (text !== '') {
		params.set('search', text);
	}

	const answer = await request<EntryList>('GET', `/entries?${params.toString()}`);

	return answer.entries.flatMap((item) => item.id !== null && item.url !== null ? [{ key: item.id, icon: 'file-text' as const, title: item.title || 'Untitled', hint: item.url }] : []);
}

const chosen = computed<LinkOption | null>(() => entry.value === null ? null : { key: entry.value.id, icon: 'file-text', title: entry.value.title, hint: entry.value.url ?? '' });

function choose(option: LinkOption): void {
	entry.value      = { id: option.key, title: option.title, url: option.hint };
	touched.value.to = true;
}

function unpick(): void {
	to.value    = entry.value?.url ?? '';
	entry.value = null;
}

// A fix the form offers, from a message.
function fix(action: NonNullable<Message['fix']>): void {
	const value = action.value;

	switch (action.action) {
		case 'slash-from':
			from.value = String(value);
			break;
		case 'slash-to':
			to.value = String(value);
			break;
		case 'edit-other':
			emit('other', String(value));
			break;
		case 'straight':
			if (typeof value === 'object' && value !== null && 'entry' in value) {
				const there = value as { entry: string; title: string; url: string };

				entry.value = { id: there.entry, title: there.title, url: there.url };
			} else if (typeof value === 'object' && value !== null && 'to' in value) {
				to.value = String((value as { to: string }).to);
			}
			break;
	}
}

async function save(): Promise<void> {
	tried.value  = true;
	failed.value = '';

	if (errors.value > 0 || checked.value === null) {
		await nextTick();
		(document.querySelector<HTMLElement>('.redirect-form [aria-invalid="true"]') ?? fromField.value)?.focus();

		return;
	}

	saving.value = true;

	try {
		const answer = await saveRedirect(draft.value);

		emit('saved', answer.redirect, answer.was);
	} catch (caught) {
		if (caught instanceof ApiError && caught.status === 422 && typeof caught.data === 'object' && caught.data !== null && 'from' in caught.data) {
			checked.value = caught.data as RedirectCheck;
		} else {
			failed.value = errorMessage(caught, 'The redirect couldn\'t be saved.');
			toast(failed.value, { kind: 'warn' });
		}
	} finally {
		saving.value = false;
	}
}
</script>

<template>
	<AdminModal open :title="row ? 'Edit Redirect' : 'New Redirect'" @close="emit('close')">
		<form id="redirect-form" class="redirect-form" novalidate @submit.prevent="save">
			<p class="redirect-form__lead"><template v-if="row?.added">Added {{ siteDateTime(row.added) }}<template v-if="row.by || row.via"> · <RedirectAdded :row="row" /></template>. </template>{{ lead }}</p>

			<div class="field">
				<label for="redirect-from">From</label>
				<input id="redirect-from" ref="fromField" v-model="from" class="mono" type="text" placeholder="/old-address" autocomplete="off" autocapitalize="none" spellcheck="false" :autofocus="!props.from && !repick" :aria-invalid="bad('from').length > 0" aria-describedby="redirect-from-help" @blur="touched.from = true">
				<p v-for="(message, index) in bad('from')" :key="index" class="field__error"><RedirectMessage :message="message" @fix="fix" /></p>
				<p id="redirect-from-help" class="field__help">A path on this site. <code class="mono">{name}</code> matches one part of it, and can be used again in To.</p>
			</div>

			<div class="field">
				<label for="redirect-to">To</label>
				<LinkPicker
					id="redirect-to"
					v-model="to"
					:chosen="chosen"
					:search="findPages"
					label="To"
					:autofocus="props.from !== undefined || repick"
					:invalid="bad('to').length > 0"
					@choose="choose"
					@clear="unpick"
					@blur="touched.to = true"
				/>
				<p v-for="(message, index) in bad('to')" :key="index" class="field__error"><RedirectMessage :message="message" @fix="fix" /></p>
			</div>

			<div class="field">
				<label for="redirect-status">Type</label>
				<AdminSelect id="redirect-status" v-model="status" :options="STATUS_OPTIONS" describedBy="redirect-status-help" />
				<p id="redirect-status-help" class="field__help">{{ TYPES[Number(status) as RedirectStatus]?.say }}</p>
			</div>

			<div v-if="notes.length" class="notice notice--small" :class="{ 'notice--warn': warned }" aria-live="polite">
				<AdminIcon :name="warned ? 'triangle-alert' : 'info'" />
				<div class="notice__text redirect-form__notes">
					<p v-for="(message, index) in notes" :key="index"><RedirectMessage :message="message" @fix="fix" /></p>
				</div>
			</div>
		</form>

		<template #footer>
			<button v-if="row" type="button" class="button button--ghost button--danger redirect-form__delete" @click="emit('delete', row)"><AdminIcon name="trash-2" />Delete</button>
			<span v-if="tried && errors" class="redirect-form__say">{{ errors === 1 ? 'One thing to fix' : `${errors} things to fix` }} first.</span>
			<button type="button" class="button" @click="emit('close')">Cancel</button>
			<button type="submit" form="redirect-form" class="button button--primary" :disabled="saving">{{ row ? 'Save' : 'Add Redirect' }}</button>
		</template>
	</AdminModal>
</template>

<style scoped>
.redirect-form {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: var(--s-4);
}

.redirect-form__lead {
	margin: 0;
	font-size: var(--text-sm);
}

.redirect-form__notes {
	display: grid;
	gap: 4px;
}

.redirect-form__notes p {
	margin: 0;
}

.redirect-form__delete {
	margin-right: auto;
}

.redirect-form__say {
	color: var(--danger);
	font-size: var(--text-sm);
}
</style>
