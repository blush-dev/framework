<script setup lang="ts">
/**
 * A status's dialog on the Workflows screen's Statuses tab (D-854): a new
 * status of the site's own, or one to change. The site's own sets
 * everything: its key (only while it's new, since files name it), label,
 * plural, tone, and whether it's live and active. One from Blush or a
 * plugin is relabeled: its label, plural, and tone, never what it does
 * (D-853). Saving answers the screen's listing, which it hands back.
 */

import { computed, ref } from 'vue';
import AdminModal from './AdminModal.vue';
import AdminSelect from './AdminSelect.vue';
import ToggleSwitch from './ToggleSwitch.vue';
import { errorMessage, request, type StatusInfo, type WorkflowList } from '../api';
import { keyOf } from '../type-form';
import { t } from '../messages';

const props = defineProps<{
	// The status to change, or `null` for a new one.
	status: StatusInfo | null;
	// Every status's key, so a new one's can't take one.
	taken: string[];
}>();

const emit = defineEmits<{ close: []; saved: [list: WorkflowList] }>();

const fresh  = computed(() => props.status === null);
const own    = computed(() => fresh.value || props.status?.edit === true);
const label  = ref(props.status?.label ?? '');
const plural = ref(props.status?.plural === props.status?.label ? '' : (props.status?.plural ?? ''));
const tone   = ref<StatusInfo['tone']>(props.status?.tone ?? 'neutral');
const live   = ref(props.status?.live ?? false);
const active = ref(props.status?.active ?? true);
const name   = ref('');
const named  = ref(false);
const error  = ref('');
const busy   = ref(false);

// A new status's key follows its label until it's typed.
const key = computed(() => props.status?.name ?? (named.value ? name.value : keyOf(label.value)));

const keyProblem = computed(() => {
	if (!fresh.value || key.value === '') {
		return '';
	}

	if (!/^[a-z][a-z0-9_]*$/.test(key.value)) {
		return t('status_dialog.bad_key', 'Use lowercase letters, numbers, and underscores, starting with a letter.');
	}

	return props.taken.includes(key.value) ? t('status_dialog.taken', 'There\'s already a status with this key.') : '';
});

const tones = [
	{ value: 'neutral', label: t('status_dialog.tones.neutral', 'Neutral') },
	{ value: 'good', label: t('status_dialog.tones.good', 'Good') },
	{ value: 'warn', label: t('status_dialog.tones.warn', 'Warn') },
	{ value: 'danger', label: t('status_dialog.tones.danger', 'Danger') },
	{ value: 'accent', label: t('status_dialog.tones.accent', 'Accent') }
];

const title = computed(() => fresh.value
	? t('status_dialog.new_title', 'New Status')
	: (own.value ? t('status_dialog.edit_title', 'Edit {label}', { label: props.status?.label ?? '' }) : t('status_dialog.relabel_title', 'Relabel {label}', { label: props.status?.label ?? '' })));

async function save(): Promise<void> {
	if (label.value.trim() === '' || keyProblem.value !== '' || key.value === '') {
		return;
	}

	busy.value  = true;
	error.value = '';

	try {
		const list = await request<WorkflowList>('PUT', `/statuses/${encodeURIComponent(key.value)}`, {
			label: label.value.trim(),
			plural: plural.value.trim(),
			tone: tone.value,
			...(own.value ? { live: live.value, active: active.value } : {})
		});

		emit('saved', list);
	} catch (caught) {
		error.value = errorMessage(caught, t('status_dialog.not_saved', 'The status couldn\'t be saved.'));
	} finally {
		busy.value = false;
	}
}
</script>

<template>
	<AdminModal open :title="title" @close="emit('close')">
		<form id="status-dialog" class="form-stack" @submit.prevent="save">
			<p v-if="!own" class="field__help">{{ t('status_dialog.relabel_help', 'It comes from {source}, which decides what it does. Its label, plural, and tone are the site\'s to change.', { source: status?.source.label ?? '' }) }}</p>
			<div class="field">
				<label for="status-label">{{ t('status_dialog.label', 'Label') }}</label>
				<input id="status-label" v-model="label" class="input" autocomplete="off" autofocus required>
			</div>
			<div v-if="fresh" class="field">
				<label for="status-key">{{ t('status_dialog.key', 'Key') }}</label>
				<input id="status-key" :value="key" class="input mono" autocomplete="off" autocapitalize="none" spellcheck="false" :aria-invalid="keyProblem ? 'true' : undefined" aria-describedby="status-key-help" @input="named = true; name = ($event.target as HTMLInputElement).value">
				<p id="status-key-help" :class="keyProblem ? 'field__error' : 'field__help'">{{ keyProblem || t('status_dialog.key_help', 'What files write, such as status: in_review. It can\'t change once it\'s saved.') }}</p>
			</div>
			<div class="field">
				<label for="status-plural">{{ t('status_dialog.plural', 'Plural') }}</label>
				<input id="status-plural" v-model="plural" class="input" autocomplete="off" :placeholder="label" aria-describedby="status-plural-help">
				<p id="status-plural-help" class="field__help">{{ t('status_dialog.plural_help', 'What a list\'s tab of them is called. Left empty, it\'s the label.') }}</p>
			</div>
			<div class="field">
				<label for="status-tone">{{ t('status_dialog.tone', 'Tone') }}</label>
				<AdminSelect id="status-tone" v-model="tone" :options="tones" described-by="status-tone-help" />
				<p id="status-tone-help" class="field__help">{{ t('status_dialog.tone_help', 'The color of its pill:') }} <span class="pill" :class="tone === 'neutral' ? '' : `pill--${tone}`">{{ label || t('status_dialog.preview', 'Preview') }}</span></p>
			</div>
			<template v-if="own">
				<ToggleSwitch :checked="live" :label="t('status_dialog.live', 'On the site')" form @change="live = $event">
					{{ t('status_dialog.live', 'On the site') }}
					<small>{{ t('status_dialog.live_help', 'Entries with it are on the site once their date comes, as published ones are.') }}</small>
				</ToggleSwitch>
				<ToggleSwitch :checked="active" :label="t('status_dialog.active', 'Found by “any status”')" form @change="active = $event">
					{{ t('status_dialog.active', 'Found by “any status”') }}
					<small>{{ t('status_dialog.active_help', 'Lists and searches for every status include it. Only the trash is left out among Blush\'s own.') }}</small>
				</ToggleSwitch>
			</template>
		</form>
		<p v-if="error" class="field__error" role="alert">{{ error }}</p>
		<template #footer>
			<button type="button" class="button" @click="emit('close')">{{ t('common.cancel', 'Cancel') }}</button>
			<button type="submit" form="status-dialog" class="button button--primary" :disabled="busy || label.trim() === '' || keyProblem !== ''">{{ busy ? t('status_dialog.saving', 'Saving…') : (fresh ? t('status_dialog.add', 'Add Status') : t('status_dialog.save', 'Save Status')) }}</button>
		</template>
	</AdminModal>
</template>
