<script setup lang="ts">
/**
 * Tools → Backups' Schedule tab (the backups sketch, D-887; a tab of its own, D-903): a form,
 * saved together with Save Schedule, which says Saved in place. How
 * often (off, daily, or weekly on a day), at an hour in the site's time
 * zone, how many of its backups the schedule keeps, the parts each
 * holds, and its email: only when one fails (the default), after every
 * one, or never, to the site's address or another. The email never
 * carries a backup, which the panel says, with Send a Test Email and
 * Preview the Email to see it before relying on it.
 */

import { computed, ref, watch } from 'vue';
import AdminIcon from './AdminIcon.vue';
import AdminModal from './AdminModal.vue';
import AdminSelect, { type SelectOption } from './AdminSelect.vue';
import BackupPartChecks from './BackupPartChecks.vue';
import MessageText from './MessageText.vue';
import { siteTime, weekdayName } from '../dates';
import { t } from '../messages';
import { toast } from '../toast';
import { errorMessage } from '../api';
import { previewEmail, saveSchedule, sendTestEmail, type BackupEmail, type BackupGroup, type BackupPart, type BackupSchedule, type BackupScheduleValues } from '../backups';

const props = defineProps<{
	schedule: BackupSchedule;
	parts: BackupPart[];
	groups: BackupGroup[];
	folder: string;
}>();

const emit = defineEmits<{
	saved: [schedule: BackupSchedule];
}>();

const EMAIL = /^[^\s@,]+@[^\s@,]+\.[^\s@,]+$/;

const allParts = computed(() => props.parts.map((part) => part.name));

function valuesOf(schedule: BackupSchedule): BackupScheduleValues {
	return { frequency: schedule.frequency, day: schedule.day, hour: schedule.hour, keep: schedule.keep, parts: schedule.parts, notice: schedule.notice, to: schedule.to };
}

const form    = ref<BackupScheduleValues>(valuesOf(props.schedule));
// The parts ticked; every part saves as `null`, so a plugin's added later
// is in it too.
const picked  = ref<string[]>(props.schedule.parts ?? allParts.value);
const other   = ref(props.schedule.to !== '');
const saving  = ref(false);
const saved   = ref(false);
const failure = ref('');
const testing = ref(false);

watch(() => props.schedule, (schedule) => {
	form.value   = valuesOf(schedule);
	picked.value = schedule.parts ?? allParts.value;
	other.value  = schedule.to !== '';
});

const values = computed<BackupScheduleValues>(() => ({
	...form.value,
	parts: picked.value.length === allParts.value.length ? null : [...picked.value],
	to: other.value ? form.value.to.trim() : ''
}));

const dirty   = computed(() => JSON.stringify(values.value) !== JSON.stringify(valuesOf(props.schedule)));
const badTo   = computed(() => other.value && form.value.to.trim() !== '' && !EMAIL.test(form.value.to.trim()));
const noParts = computed(() => form.value.frequency !== 'off' && !picked.value.length);
const off     = computed(() => form.value.frequency === 'off');

watch(values, () => {
	saved.value = false;
}, { deep: true });

const FREQUENCIES = [
	{ value: 'off' as const, label: t('backup_schedule.off', 'Off') },
	{ value: 'daily' as const, label: t('backup_schedule.daily', 'Daily') },
	{ value: 'weekly' as const, label: t('backup_schedule.weekly', 'Weekly') }
];

const days  = computed<SelectOption[]>(() => [0, 1, 2, 3, 4, 5, 6].map((day) => ({ value: String(day), label: weekdayName(day) })));
const hours = computed<SelectOption[]>(() => Array.from({ length: 24 }, (_, hour) => ({ value: String(hour), label: siteTime(new Date(2026, 0, 1, hour)) })));
const keeps = computed<SelectOption[]>(() => [...new Set([7, 14, 30, 60, form.value.keep])].sort((a, b) => a - b).map((keep) => ({ value: String(keep), label: t('backup_schedule.keep_newest', 'The {count} newest', { count: keep }) })));

const NOTICES: SelectOption[] = [
	{ value: 'failures', label: t('backup_schedule.notice_failures', 'Only when a backup fails') },
	{ value: 'always', label: t('backup_schedule.notice_always', 'After every backup') },
	{ value: 'never', label: t('backup_schedule.notice_never', 'Never') }
];

const noticeHelp = computed(() => ({
	failures: t('backup_schedule.notice_failures_help', 'Quiet while backups work: an email when one fails, and another when they work again.'),
	always: t('backup_schedule.notice_always_help', 'An email after each scheduled backup, saying whether it worked. Daily backups mean an email every day.'),
	never: t('backup_schedule.notice_never_help', 'Nobody hears about a failed backup until they look at this screen.')
})[form.value.notice]);

const say = computed(() => {
	if (noParts.value) {
		return t('backup_schedule.pick_parts', 'Pick at least one thing to back up.');
	}

	if (badTo.value) {
		return t('backup_schedule.fix_address', 'Fix the email address to save.');
	}

	return dirty.value ? t('backup_schedule.unsaved', 'Unsaved changes') : '';
});

async function save(): Promise<void> {
	saving.value  = true;
	failure.value = '';

	try {
		const schedule = await saveSchedule(values.value);

		emit('saved', schedule);
		saved.value = true;
	} catch (caught) {
		failure.value = errorMessage(caught, t('backup_schedule.errors.save', 'The schedule couldn\'t be saved.'));
	} finally {
		saving.value = false;
	}
}

function useSiteEmail(): void {
	other.value   = false;
	form.value.to = '';
}

async function test(): Promise<void> {
	testing.value = true;

	try {
		const to = await sendTestEmail(values.value.to);

		toast(t('backup_schedule.test_sent', 'Sent a test email to {to}. If it hasn\'t come in a few minutes, check how the site sends mail.', { to }));
	} catch (caught) {
		toast(errorMessage(caught, t('backup_schedule.errors.test', 'The test email couldn\'t be sent.')), { kind: 'warn' });
	} finally {
		testing.value = false;
	}
}

// The preview: which email, and the email.
const previewing = ref<'done' | 'failed' | null>(null);
const preview    = ref<BackupEmail | null>(null);

async function showPreview(stage: 'done' | 'failed'): Promise<void> {
	previewing.value = stage;

	try {
		preview.value = await previewEmail(stage, values.value.to);
	} catch (caught) {
		previewing.value = null;
		toast(errorMessage(caught, t('backup_schedule.errors.preview', 'The email couldn\'t be previewed.')), { kind: 'warn' });
	}
}
</script>

<template>
	<section class="panel" aria-labelledby="schedule-heading">
		<header class="panel__header">
			<h2 id="schedule-heading">{{ t('backup_schedule.title', 'Schedule') }}</h2>
			<p class="panel__hint">{{ t('backup_schedule.hint', 'Scheduled backups run in the background.') }}</p>
		</header>

		<form id="backup-schedule" class="panel__body backup-schedule" novalidate @submit.prevent="save">
			<div class="field">
				<span id="schedule-frequency" class="field__label">{{ t('backup_schedule.how_often', 'How Often') }}</span>
				<div class="segmented" role="group" aria-labelledby="schedule-frequency">
					<button v-for="frequency in FREQUENCIES" :key="frequency.value" type="button" :aria-pressed="form.frequency === frequency.value" @click="form.frequency = frequency.value">{{ frequency.label }}</button>
				</div>
			</div>

			<template v-if="!off">
				<div class="backup-schedule__row">
					<div v-if="form.frequency === 'weekly'" class="field">
						<label for="schedule-day">{{ t('backup_schedule.day', 'Day') }}</label>
						<AdminSelect id="schedule-day" :model-value="String(form.day)" :options="days" @update:model-value="form.day = Number($event)" />
					</div>
					<div class="field">
						<label for="schedule-hour">{{ t('backup_schedule.time', 'Time') }}</label>
						<AdminSelect id="schedule-hour" :model-value="String(form.hour)" :options="hours" described-by="schedule-hour-help" @update:model-value="form.hour = Number($event)" />
						<span id="schedule-hour-help" class="field__help">{{ t('backup_schedule.time_help', 'In the site\'s time zone, {zone}.', { zone: schedule.timezone.replace(/_/g, ' ') }) }}</span>
					</div>
					<div class="field">
						<label for="schedule-keep">{{ t('backup_schedule.keep', 'Keep') }}</label>
						<AdminSelect id="schedule-keep" :model-value="String(form.keep)" :options="keeps" described-by="schedule-keep-help" @update:model-value="form.keep = Number($event)" />
						<span id="schedule-keep-help" class="field__help">{{ t('backup_schedule.keep_help', 'The oldest scheduled one goes when a new one is made. Backups made any other way stay.') }}</span>
					</div>
				</div>

				<div class="field">
					<span class="field__label">{{ t('backup_schedule.holds', 'What Each Holds') }}</span>
					<BackupPartChecks v-model="picked" :parts="parts" :groups="groups" />
				</div>

				<div class="backup-schedule__row">
					<div class="field">
						<label for="schedule-notice">{{ t('backup_schedule.email', 'Send an Email') }}</label>
						<AdminSelect id="schedule-notice" :model-value="form.notice" :options="NOTICES" described-by="schedule-notice-help" @update:model-value="form.notice = $event as BackupScheduleValues['notice']" />
						<span id="schedule-notice-help" class="field__help">{{ noticeHelp }}</span>
					</div>
					<div v-if="form.notice !== 'never'" class="field">
						<label for="schedule-to">{{ t('backup_schedule.to', 'To') }}</label>
						<div v-if="!other" class="backup-schedule__to">
							<span class="mono">{{ schedule.siteEmail }}</span>
							<span class="tag">{{ t('backup_schedule.site_email', 'Site Email') }}</span>
							<button id="schedule-to" type="button" class="button button--small button--ghost" @click="other = true">{{ t('backup_schedule.use_another', 'Use Another Address') }}</button>
						</div>
						<div v-else class="backup-schedule__to">
							<input id="schedule-to" v-model="form.to" class="mono" type="email" autocomplete="email" spellcheck="false" placeholder="ops@example.com" :aria-invalid="badTo || undefined" aria-describedby="schedule-to-help">
							<button type="button" class="button button--small button--ghost" @click="useSiteEmail">{{ t('backup_schedule.use_site', 'Use the Site Email') }}</button>
						</div>
						<span id="schedule-to-help" class="field__help">{{ badTo ? t('backup_schedule.bad_address', 'That isn\'t an email address. Check it for a typo.') : t('backup_schedule.to_help', 'The site\'s email address is set on Settings → General.') }}</span>
					</div>
				</div>

				<template v-if="form.notice !== 'never'">
					<p v-if="!schedule.canMail" class="notice notice--small notice--warn"><AdminIcon name="triangle-alert" /><span class="notice__text">{{ t('backup_schedule.no_mail', 'The site can\'t send email yet, so nothing will be sent. Set up how it sends mail first.') }}</span></p>
					<p class="notice notice--small"><AdminIcon name="lock" /><span class="notice__text">{{ t('backup_schedule.safe', 'The email says whether the backup worked, when, its size and what it holds, and links to this screen. It never attaches the backup or links to a download: a backup holds password hashes, and email is no place for them.') }}</span></p>
					<div class="backup-schedule__buttons">
						<button type="button" class="button button--small" :disabled="testing || badTo || !schedule.canMail" @click="test">{{ testing ? t('backup_schedule.sending', 'Sending…') : t('backup_schedule.test', 'Send a Test Email') }}</button>
						<button type="button" class="button button--small button--ghost" :disabled="badTo" @click="showPreview('done')">{{ t('backup_schedule.preview', 'Preview the Email') }}</button>
					</div>
				</template>
			</template>

			<p class="field__help"><MessageText :text="t('backup_schedule.kept_in', 'Backups are kept in {folder}, which the site never serves.')"><template #folder><span class="mono">{{ folder }}/</span></template></MessageText></p>
			<p v-if="failure" class="notice notice--error" role="alert">{{ failure }}</p>
		</form>

		<footer class="panel__foot">
			<span aria-live="polite">
				<template v-if="saved && !dirty"><AdminIcon name="check" /> {{ t('backup_schedule.saved', 'Saved') }}</template>
				<template v-else>{{ say }}</template>
			</span>
			<button type="submit" form="backup-schedule" class="button button--primary" :disabled="saving || !dirty || noParts || badTo">{{ t('backup_schedule.save', 'Save Schedule') }}</button>
		</footer>
	</section>

	<AdminModal :open="previewing !== null" :title="t('backup_schedule.preview_title', 'Preview the Email')" wide @close="previewing = null; preview = null">
		<div class="backup-preview">
			<div class="segmented" role="group" :aria-label="t('backup_schedule.which_email', 'Which email')">
				<button type="button" :aria-pressed="previewing === 'done'" @click="showPreview('done')">{{ t('backup_schedule.worked', 'Backup Worked') }}</button>
				<button type="button" :aria-pressed="previewing === 'failed'" @click="showPreview('failed')">{{ t('backup_schedule.failed', 'Backup Failed') }}</button>
			</div>
			<p v-if="!preview" class="loading">{{ t('common.loading', 'Loading…') }}</p>
			<template v-else>
				<dl class="backup-preview__head">
					<dt>{{ t('backup_schedule.from', 'From') }}</dt><dd class="mono">{{ preview.from || '—' }}</dd>
					<dt>{{ t('backup_schedule.to', 'To') }}</dt><dd class="mono">{{ preview.to }}</dd>
					<dt>{{ t('backup_schedule.subject', 'Subject') }}</dt><dd>{{ preview.subject }}</dd>
				</dl>
				<pre class="backup-preview__text">{{ preview.text }}</pre>
			</template>
		</div>
		<template #footer>
			<button type="button" class="button" @click="previewing = null; preview = null">{{ t('common.close', 'Close') }}</button>
		</template>
	</AdminModal>
</template>

<style scoped>
.backup-schedule {
	display: grid;
	gap: var(--s-5);
}

.backup-schedule > p {
	margin: 0;
}

/* Fields that sit side by side when there's room, each from the top: a
   field shorter than its neighbor (a line less of help) would otherwise
   be stretched, spreading its label and control down. */
.backup-schedule__row {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
	align-items: start;
	gap: var(--s-4);
}

.backup-schedule__to {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
}

.backup-schedule__to input {
	flex: 1;
	min-width: 12rem;
}

.backup-schedule__buttons {
	display: flex;
	flex-wrap: wrap;
	gap: var(--s-2);
}

.backup-preview {
	display: grid;
	gap: var(--s-4);
}

.backup-preview__head {
	display: grid;
	grid-template-columns: auto 1fr;
	gap: var(--s-1) var(--s-4);
	margin: 0;
}

.backup-preview__head dt {
	color: var(--color-text-muted);
}

.backup-preview__head dd {
	margin: 0;
	color: var(--color-text);
	overflow-wrap: anywhere;
}

.backup-preview__text {
	margin: 0;
	padding: var(--s-4);
	overflow-x: auto;
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-small);
	background: var(--color-surface-subtle);
	color: var(--color-text);
	font-family: var(--font-family-ui);
	font-size: var(--font-size-small);
	white-space: pre-wrap;
}
</style>
