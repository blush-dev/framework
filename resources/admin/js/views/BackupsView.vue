<script setup lang="ts">
/**
 * Backups (the backups sketch, D-881 to D-886; a Tools screen, D-900), with
 * `site.backups`, in two tabs: the backups, and their schedule
 * (`BackupSchedule`, D-903). The backups are copies of the site's
 * stored data, kept on this server.
 * Three figures say when the last backup was (or that it failed, and the
 * newest good one), how many are kept and where, and the space they take;
 * with no backups, an empty state takes their place.
 *
 * A backup being made is a row, not a toast: Back Up Now puts it at the
 * top with a bar that names the part being copied, and the admin runs its
 * job while the tab is open, as it follows any job (D-621). It shows its
 * own result, so finishing doesn't toast; a failure says why on the row.
 * A note is a light line under its date and file name. Each row's menu has Download,
 * its note, Restore, and, below a rule, Delete, which a toast can undo
 * for a day; ticking rows (all with the header's box) brings the bulk
 * bar's Delete, undone the same way (D-895).
 *
 * Restoring (`site.backups.restore`) picks parts from those the backup
 * holds, the Users ones unticked, and says what refuses it and what it
 * will do, as the server checks it. It runs as a job, followed in a
 * notice over the list; the site is backed up first, so the toast it
 * ends with offers Undo, which restores that backup. Restoring accounts
 * signs you out when it's done, as it signs everyone else out.
 */

import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import AdminModal from '../components/AdminModal.vue';
import BackupPartChecks from '../components/BackupPartChecks.vue';
import BackupSchedule from '../components/BackupSchedule.vue';
import BackupUpload from '../components/BackupUpload.vue';
import BulkBar from '../components/BulkBar.vue';
import EmptyState from '../components/EmptyState.vue';
import MenuButton from '../components/MenuButton.vue';
import { confirmAction } from '../confirm';
import { useSelectAll } from '../select';
import { siteDateTime, weekdayName } from '../dates';
import { formatSize, formatWhen } from '../format';
import { follow } from '../jobs';
import { t } from '../messages';
import { can, loadSession, signOut } from '../session';
import { toast } from '../toast';
import { errorMessage, type Job } from '../api';
import { checkRestore, deleteBackup, downloadUrl, holds, loadBackups, noteBackup, startBackup, startRestore, undeleteBackup, type BackupInfo, type BackupsOverview, type RestoreCheck } from '../backups';

const NOTE_LENGTH = 60;

const overview = ref<BackupsOverview | null>(null);
const error    = ref('');
const loading  = ref(false);
// What each backup being made last said, by name.
const progress = ref<Record<string, Job>>({});
const followed = new Set<string>();

const choosing = ref(false);
const picked   = ref<string[]>([]);
const note     = ref('');
const starting = ref(false);
const failure  = ref('');

// The backup being restored from, its parts picked, and what the server
// says of them.
const restoring  = ref<BackupInfo | null>(null);
const restoreAll = ref<string[]>([]);
const checked    = ref<RestoreCheck | null>(null);
const asking     = ref(false);
// The restore running: its job, the backup, its parts, and what it last
// said.
const restore    = ref<{ job: string; backup: string; parts: string[]; state: Job | null } | null>(null);
const restoreSay = ref('');

const router = useRouter();
const route  = useRoute();

// Two tabs (D-903): the backups, and the schedule, in the address
// (`?tab=schedule`), as Redirects' are.
const tab  = computed<'backups' | 'schedule'>(() => route.query.tab === 'schedule' ? 'schedule' : 'backups');
const tabs = computed(() => [
	{ key: 'backups' as const, label: t('backups_view.tabs.backups', 'Backups') },
	{ key: 'schedule' as const, label: t('backups_view.tabs.schedule', 'Schedule') }
]);

const uploading = ref(false);

const noting   = ref<BackupInfo | null>(null);
const noteText = ref('');
const saving   = ref(false);

const backups = computed(() => overview.value?.backups ?? []);
const running = computed(() => backups.value.find((backup) => backup.status === 'running') ?? null);
const last    = computed(() => backups.value.find((backup) => backup.status !== 'running') ?? null);
const newest  = computed(() => backups.value.find((backup) => backup.status === 'done') ?? null);

// The backups picked for Delete; one being made can't be.
const selected = ref<string[]>([]);
const choosable = computed(() => backups.value.filter((backup) => backup.status !== 'running').map((backup) => backup.name));
const { state: allState, isChosen, toggle: choose, toggleAll: chooseAll } = useSelectAll(() => choosable.value, selected);

// What the schedule does, in a line, under Next Backup.
const scheduleSummary = computed(() => {
	const schedule = overview.value?.schedule;

	if (!schedule || schedule.frequency === 'off') {
		return t('backups_view.next_none', 'Nothing is scheduled. Turn it on under Schedule.');
	}

	return schedule.frequency === 'daily'
		? t('backups_view.next_daily', 'Daily, keeping the {count} newest', { count: schedule.keep })
		: t('backups_view.next_weekly', 'Weekly on {day}, keeping the {count} newest', { day: weekdayName(schedule.day), count: schedule.keep });
});

const pickedSize = computed(() => (overview.value?.parts ?? [])
	.filter((part) => picked.value.includes(part.name))
	.reduce((sum, part) => sum + part.bytes, 0));

async function load(): Promise<void> {
	loading.value = true;
	error.value   = '';

	try {
		overview.value = await loadBackups();
		selected.value = selected.value.filter((name) => choosable.value.includes(name));
		followRunning();

		const running = overview.value.restoring;

		if (running !== null && restore.value === null) {
			void followRestore(running.job, running.backup, running.parts);
		}
	} catch (caught) {
		error.value = errorMessage(caught, t('backups_view.errors.load', 'The backups couldn\'t be loaded.'));
	} finally {
		loading.value = false;
	}
}

// The admin runs the job of a backup being made while the tab is open,
// so it finishes on a host with no cron.
function followRunning(): void {
	const backup = running.value;

	if (backup === null || backup.job === null || followed.has(backup.name)) {
		return;
	}

	followed.add(backup.name);

	void follow(backup.job, (job) => {
		progress.value[backup.name] = job;
	}).catch(() => undefined).finally(() => {
		delete progress.value[backup.name];
		void load();
	});
}

function openChooser(): void {
	picked.value   = (overview.value?.parts ?? []).map((part) => part.name);
	note.value     = '';
	failure.value  = '';
	choosing.value = true;
}

async function start(): Promise<void> {
	starting.value = true;
	failure.value  = '';

	try {
		await startBackup(picked.value, note.value);
		choosing.value = false;
		await load();
	} catch (caught) {
		failure.value = errorMessage(caught, t('backups_view.errors.start', 'The backup couldn\'t be started.'));
	} finally {
		starting.value = false;
	}
}

async function cancel(backup: BackupInfo): Promise<void> {
	try {
		await deleteBackup(backup.name);
		toast(t('backups_view.canceled', 'Canceled the backup. Nothing was kept.'), { kind: 'info' });
	} catch (caught) {
		toast(errorMessage(caught, t('backups_view.errors.cancel', 'The backup couldn\'t be canceled.')), { kind: 'warn' });
	}
}

const users = computed(() => new Set((overview.value?.parts ?? []).filter((part) => part.group === 'users').map((part) => part.name)));

function openRestore(backup: BackupInfo): void {
	restoring.value  = backup;
	checked.value    = null;
	restoreAll.value = backup.parts.filter((name) => !users.value.has(name));
}

// The server checks the parts picked whenever they change.
watch([restoring, restoreAll], async ([backup, parts]) => {
	if (backup === null) {
		return;
	}

	try {
		const answer = await checkRestore(backup.name, parts);

		if (restoring.value === backup && restoreAll.value === parts) {
			checked.value = answer;
		}
	} catch (caught) {
		checked.value = { problems: [errorMessage(caught, t('backups_view.errors.check', 'The restore couldn\'t be checked.'))], warnings: [] };
	}
}, { deep: true });

const restoreParts = computed(() => (overview.value?.parts ?? []).filter((part) => restoring.value?.parts.includes(part.name)));

function partLabels(names: string[]): string {
	return names.map((name) => overview.value?.parts.find((part) => part.name === name)?.label ?? name).join(', ');
}

async function confirmRestore(): Promise<void> {
	const backup = restoring.value;

	if (backup === null) {
		return;
	}

	asking.value = true;

	try {
		const job = await startRestore(backup.name, restoreAll.value);

		restoring.value = null;
		void followRestore(job, backup.name, [...restoreAll.value]);
	} catch (caught) {
		checked.value = { problems: [errorMessage(caught, t('backups_view.errors.restore', 'The restore couldn\'t be started.'))], warnings: checked.value?.warnings ?? [] };
	} finally {
		asking.value = false;
	}
}

// Runs a restore's job to its end, then says how it went: a toast with
// Undo, or a notice saying what stopped it.
async function followRestore(job: string, backup: string, parts: string[]): Promise<void> {
	restore.value    = { job, backup, parts, state: null };
	restoreSay.value = '';

	try {
		const done   = await follow(job, (state) => {
			if (restore.value !== null) {
				restore.value.state = state;
			}
		});
		const safety = typeof done.result.safety === 'string' ? done.result.safety : null;

		if (done.status !== 'done') {
			restoreSay.value = done.error ?? done.message ?? t('backups_view.errors.restore_failed', 'The restore failed.');

			return;
		}

		// Everyone is signed out; a session the restore didn't end (this
		// one, written back as its request ended) ends here.
		if (done.result.signedOut === true) {
			await signOut().catch(() => loadSession(true));
			await router.push({ name: 'sign-in' });

			return;
		}

		toast(t('backups_view.restored', 'Restored {parts} from {name}', { parts: partLabels(parts), name: backup }), {
			undo: safety === null ? undefined : () => void startRestore(safety, parts).then((undo) => followRestore(undo, safety, parts), (caught: unknown) => {
				toast(errorMessage(caught, t('backups_view.errors.undo', 'The restore couldn\'t be undone.')), { kind: 'warn' });
			})
		});
	} catch (caught) {
		restoreSay.value = errorMessage(caught, t('backups_view.errors.restore_failed', 'The restore failed.'));
	} finally {
		restore.value = null;
		void load();
	}
}

function openNote(backup: BackupInfo): void {
	noting.value   = backup;
	noteText.value = backup.note;
}

async function saveNote(): Promise<void> {
	if (noting.value === null) {
		return;
	}

	saving.value = true;

	try {
		await noteBackup(noting.value.name, noteText.value);
		noting.value = null;
		await load();
	} catch (caught) {
		toast(errorMessage(caught, t('backups_view.errors.note', 'The note couldn\'t be saved.')), { kind: 'warn' });
	} finally {
		saving.value = false;
	}
}

async function remove(backup: BackupInfo): Promise<void> {
	const ok = await confirmAction({
		title: t('backups_view.delete_title', 'Delete This Backup?'),
		body: backup.status === 'failed'
			? t('backups_view.delete_failed_body', 'The failed backup from {date} is removed from the list.', { date: siteDateTime(backup.created) })
			: t('backups_view.delete_body', 'The backup from {date} ({size}) is deleted from this server. A copy you downloaded isn\'t touched.', { date: siteDateTime(backup.created), size: formatSize(backup.size) }),
		confirm: t('backups_view.delete_backup', 'Delete Backup'),
		cancel: t('backups_view.keep_it', 'Keep It'),
		danger: true
	});

	if (!ok) {
		return;
	}

	try {
		await deleteBackup(backup.name);
		await load();
		toast(t('backups_view.deleted', 'Deleted the backup from {date}', { date: siteDateTime(backup.created) }), {
			kind: 'danger',
			undo: () => void undeleteBackup(backup.name).then(load, (caught: unknown) => {
				toast(errorMessage(caught, t('backups_view.errors.undelete', 'The backup couldn\'t be put back.')), { kind: 'warn' });
			})
		});
	} catch (caught) {
		toast(errorMessage(caught, t('backups_view.errors.delete', 'The backup couldn\'t be deleted.')), { kind: 'warn' });
	}
}

async function removeSelected(): Promise<void> {
	const names = [...selected.value];
	const ok    = await confirmAction({
		title: t('backups_view.delete_many_title', '{count, plural, one {Delete This Backup?} other {Delete # Backups?} }', { count: names.length }),
		body: t('backups_view.delete_many_body', '{count, plural, one {The backup is deleted from this server.} other {The # backups are deleted from this server.} } A copy you downloaded isn\'t touched.', { count: names.length }),
		confirm: t('backups_view.delete_many', '{count, plural, one {Delete Backup} other {Delete # Backups} }', { count: names.length }),
		cancel: t('backups_view.keep_them', 'Keep Them'),
		danger: true
	});

	if (!ok) {
		return;
	}

	const deleted: string[] = [];

	try {
		for (const name of names) {
			await deleteBackup(name);
			deleted.push(name);
		}
	} catch (caught) {
		toast(errorMessage(caught, t('backups_view.errors.delete', 'The backup couldn\'t be deleted.')), { kind: 'warn' });
	}

	selected.value = [];
	await load();

	if (deleted.length) {
		toast(t('backups_view.deleted_many', '{count, plural, one {Deleted a backup} other {Deleted # backups} }', { count: deleted.length }), {
			kind: 'danger',
			undo: () => void Promise.all(deleted.map((name) => undeleteBackup(name))).then(load, (caught: unknown) => {
				toast(errorMessage(caught, t('backups_view.errors.undelete', 'The backup couldn\'t be put back.')), { kind: 'warn' });
				void load();
			})
		});
	}
}

function madeBy(backup: BackupInfo): string {
	if (backup.by !== null) {
		return backup.by;
	}

	return backup.kind === 'scheduled' ? t('backups_view.by_schedule', 'Schedule') : t('backups_view.by_cli', 'Command line');
}

function step(backup: BackupInfo): string {
	const job = progress.value[backup.name];

	return job?.message || (job?.status === 'queued' && job.progress === null ? t('backups_view.waiting', 'Waiting its turn…') : t('backups_view.starting', 'Starting…'));
}

onMounted(() => {
	void load();
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ t('backups_view.title', 'Backups') }}</h1>
			<p class="page-header__hint">{{ t('backups_view.hint', 'Copies of the site\'s stored data') }}</p>
		</div>
	</header>


	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-if="!overview" class="loading">{{ t('backups_view.loading', 'Loading the backups…') }}</p>
	<template v-else>
		<div v-if="restore" class="notice backups-restore" role="status">
			<AdminIcon name="undo-2" />
			<span class="notice__text">
				{{ t('backups_view.restoring', 'Restoring {parts} from {name}…', { parts: partLabels(restore.parts), name: restore.backup }) }}
				<progress class="progress" max="100" :value="restore.state?.progress ?? undefined" :aria-label="t('backups_view.restore_progress', 'Restore progress')" />
				<span class="table__meta">{{ restore.state?.message || t('backups_view.starting', 'Starting…') }}</span>
			</span>
		</div>
		<p v-else-if="restoreSay" class="notice notice--error" role="alert"><AdminIcon name="triangle-alert" /><span class="notice__text">{{ restoreSay }}</span></p>

		<nav class="status-tabs" :aria-label="t('backups_view.title', 'Backups')">
			<RouterLink v-for="item in tabs" :key="item.key" class="status-tabs__tab" :to="{ query: item.key === 'backups' ? {} : { tab: item.key } }" :aria-current="tab === item.key ? 'page' : undefined">{{ item.label }}</RouterLink>
		</nav>

		<template v-if="tab === 'backups'">
			<dl v-if="backups.length" class="stats stats--three">
				<div v-if="last?.status === 'failed'" class="stat stat--danger">
					<dt class="stat__label"><AdminIcon name="triangle-alert" />{{ t('backups_view.last_failed', 'Last Backup Failed') }}</dt>
					<dd class="stat__value">{{ formatWhen(last.created) }}</dd>
					<dd class="stat__context">{{ newest ? t('backups_view.newest_good', 'Newest good copy: {date}', { date: siteDateTime(newest.created) }) : t('backups_view.no_good', 'There\'s no good copy yet.') }}</dd>
				</div>
				<div v-else class="stat">
					<dt class="stat__label"><AdminIcon name="circle-check" />{{ t('backups_view.last', 'Last Backup') }}</dt>
					<dd class="stat__value">{{ last ? formatWhen(last.created) : t('backups_view.being_made', 'Being made') }}</dd>
					<dd v-if="last" class="stat__context">{{ siteDateTime(last.created) }} · {{ formatSize(last.size) }}</dd>
				</div>
				<div class="stat">
					<dt class="stat__label"><AdminIcon name="clock" />{{ t('backups_view.next', 'Next Backup') }}</dt>
					<dd class="stat__value">{{ overview.schedule.next ? formatWhen(overview.schedule.next) : t('backups_view.next_off', 'Off') }}</dd>
					<dd class="stat__context">{{ scheduleSummary }}</dd>
				</div>
				<div class="stat">
					<dt class="stat__label"><AdminIcon name="hard-drive" />{{ t('backups_view.space', 'Space Used') }}</dt>
					<dd class="stat__value">{{ formatSize(overview.used) }}</dd>
					<dd v-if="overview.free !== null" class="stat__context">{{ t('backups_view.free', '{size} free on the disk', { size: formatSize(overview.free) }) }}</dd>
				</div>
			</dl>

			<section class="panel" aria-labelledby="backups-heading" :aria-busy="loading">
				<header class="panel__header">
					<h2 id="backups-heading">{{ t('backups_view.backups', 'Backups') }}</h2>
					<p v-if="backups.length" class="panel__hint">{{ t('backups_view.count', '{count, plural, one {# kept} other {# kept} }, newest first', { count: backups.length }) }}</p>
					<div v-if="backups.length" class="panel__actions">
						<button type="button" class="button button--small" @click="uploading = true"><AdminIcon name="upload" />{{ t('backups_view.upload', 'Upload a Backup') }}</button>
						<button type="button" class="button button--small button--primary" :disabled="running !== null || restore !== null" @click="openChooser"><AdminIcon name="archive" />{{ t('backups_view.back_up_now', 'Back Up Now') }}</button>
					</div>
				</header>

				<EmptyState v-if="!backups.length" icon="archive" tag="h3" :heading="t('backups_view.none', 'No Backups Yet')" :text="t('backups_view.none_text', 'Nothing has been backed up on this site. A backup is a copy of its content, settings, and accounts, kept on this server to download or restore.')">
					<template #actions>
						<button type="button" class="button button--primary" @click="openChooser"><AdminIcon name="archive" />{{ t('backups_view.back_up_now', 'Back Up Now') }}</button>
						<button type="button" class="button" @click="uploading = true"><AdminIcon name="upload" />{{ t('backups_view.upload', 'Upload a Backup') }}</button>
					</template>
				</EmptyState>

				<div v-else class="table-wrap">
					<table class="table backups-table" aria-labelledby="backups-heading">
						<thead>
							<tr>
								<th scope="col" class="table__check">
									<button type="button" class="check" role="checkbox" :aria-checked="allState" :aria-label="t('backups_view.select_all', 'Select every backup')" :disabled="!choosable.length" @click="chooseAll"><AdminIcon :name="allState === 'mixed' ? 'minus' : 'check'" /></button>
								</th>
								<th scope="col">{{ t('backups_view.col_backup', 'Backup') }}</th>
								<th scope="col">{{ t('backups_view.col_holds', 'Holds') }}</th>
								<th scope="col" class="backups-table__size">{{ t('backups_view.col_size', 'Size') }}</th>
								<th scope="col">{{ t('backups_view.col_by', 'Made by') }}</th>
								<th scope="col">{{ t('backups_view.col_status', 'Status') }}</th>
								<th scope="col" class="table__actions"><span class="visually-hidden">{{ t('backups_view.col_actions', 'Actions') }}</span></th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="backup in backups" :key="backup.name" :class="{ 'is-selected': isChosen(backup.name) }">
								<td class="table__check">
									<button v-if="backup.status !== 'running'" type="button" class="check" role="checkbox" :aria-checked="isChosen(backup.name) ? 'true' : 'false'" :aria-label="t('backups_view.select', 'Select the backup from {date}', { date: siteDateTime(backup.created) })" @click="choose(backup.name)"><AdminIcon name="check" /></button>
								</td>
								<th scope="row">
									<span class="backups-table__backup">
										<span>{{ siteDateTime(backup.created) }}</span>
										<span v-if="backup.status === 'failed'" class="table__meta backups-table__reason">{{ backup.reason }}</span>
										<span v-else class="table__meta mono">{{ backup.file }}</span>
										<span v-if="backup.note" class="backups-table__note">{{ backup.note }}</span>
									</span>
								</th>
								<td class="muted">{{ holds(backup, overview.parts).join(', ') }}</td>
								<td class="muted backups-table__size">{{ backup.status === 'done' ? formatSize(backup.size) : '—' }}</td>
								<td class="muted">{{ madeBy(backup) }}</td>
								<td>
									<div v-if="backup.status === 'running'" class="backups-table__progress">
										<progress class="progress" max="100" :value="progress[backup.name]?.progress ?? undefined" :aria-label="t('backups_view.progress', 'Backup progress')" />
										<span class="table__meta" aria-live="polite">{{ step(backup) }}</span>
									</div>
									<span v-else-if="backup.status === 'failed'" class="pill pill--danger">{{ t('backups_view.failed', 'Failed') }}</span>
									<span v-else class="pill pill--good">{{ t('backups_view.complete', 'Complete') }}</span>
								</td>
								<td class="table__actions">
									<button v-if="backup.status === 'running'" type="button" class="button button--small button--ghost" @click="cancel(backup)">{{ t('common.cancel', 'Cancel') }}</button>
									<MenuButton v-else button-class="row-more" :label="t('backups_view.actions_for', 'Actions for the backup from {date}', { date: siteDateTime(backup.created) })" floating>
										<template #button>
											<AdminIcon name="ellipsis" />
										</template>
										<template v-if="backup.status === 'done'">
											<a class="menu-item" :href="downloadUrl(backup.name)" download><AdminIcon name="download" />{{ t('backups_view.download', 'Download {size}', { size: formatSize(backup.size) }) }}</a>
											<button v-if="can('site.backups.restore')" type="button" class="menu-item" :disabled="running !== null || restore !== null" @click="openRestore(backup)"><AdminIcon name="undo-2" />{{ t('backups_view.restore', 'Restore…') }}</button>
											<button type="button" class="menu-item" @click="openNote(backup)"><AdminIcon name="pen-line" />{{ backup.note ? t('backups_view.edit_note', 'Edit note') : t('backups_view.add_note', 'Add a note') }}</button>
										</template>
										<RouterLink v-else-if="can('site.logs')" class="menu-item" :to="{ name: 'logs' }"><AdminIcon name="list" />{{ t('backups_view.show_logs', 'Show in Logs') }}</RouterLink>
										<div class="menu-divider" />
										<button type="button" class="menu-item menu-item--danger" @click="remove(backup)"><AdminIcon name="trash-2" />{{ t('common.delete', 'Delete') }}</button>
									</MenuButton>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<p v-if="backups.length" class="panel__note">{{ t('backups_view.note', 'A backup holds password hashes, so keep a downloaded copy somewhere private. Media files aren\'t in it: copy user/media as you would any files.') }}</p>
			</section>
		</template>

		<!-- Kept while the other tab shows, so unsaved changes stay; shown
		     and hidden by a wrapper, since the schedule has two roots (its
		     panel and the preview's modal), which v-show can't reach. -->
		<div v-show="tab === 'schedule'">
			<BackupSchedule :schedule="overview.schedule" :parts="overview.parts" :groups="overview.groups" :folder="overview.folder" @saved="overview.schedule = $event" />
		</div>

		<BackupUpload :open="uploading" :limit="overview.upload.limit" @close="uploading = false" @added="load" />

		<BulkBar v-if="tab === 'backups' && selected.length" :count="selected.length" :label="t('backups_view.bulk_actions', 'Bulk actions')" @clear="selected = []">
			<button type="button" class="button button--ghost button--small button--danger" @click="removeSelected"><AdminIcon name="trash-2" />{{ t('common.delete', 'Delete') }}</button>
		</BulkBar>
	</template>

	<AdminModal :open="choosing" :title="t('backups_view.back_up_now', 'Back Up Now')" wide @close="choosing = false">
		<form id="backup-form" class="backup-form" novalidate @submit.prevent="start">
			<p>{{ t('backups_view.choose_lead', 'Pick what goes in. Before changing a theme or a plugin, Settings on its own is often enough.') }}</p>
			<BackupPartChecks v-if="overview" v-model="picked" :parts="overview.parts" :groups="overview.groups" measure :disabled="starting" />
			<div class="field">
				<label for="backup-note">{{ t('backups_view.note_label', 'Note') }}</label>
				<input id="backup-note" v-model="note" :maxlength="NOTE_LENGTH" autocomplete="off" :placeholder="t('backups_view.note_placeholder', 'Before switching themes')" aria-describedby="backup-note-help">
				<span id="backup-note-help" class="field__help">{{ t('backups_view.note_help', 'Optional. Shown beside its date.') }}</span>
			</div>
			<p v-if="failure" class="notice notice--error" role="alert">{{ failure }}</p>
		</form>
		<template #footer>
			<button type="button" class="button" @click="choosing = false">{{ t('common.cancel', 'Cancel') }}</button>
			<button type="submit" form="backup-form" class="button button--primary" :disabled="starting || !picked.length">{{ picked.length ? t('backups_view.back_up_size', 'Back Up {size}', { size: formatSize(pickedSize) }) : t('backups_view.back_up', 'Back Up') }}</button>
		</template>
	</AdminModal>

	<AdminModal :open="restoring !== null" :title="restoring ? t('backups_view.restore_title', 'Restore the Backup from {date}?', { date: siteDateTime(restoring.created) }) : ''" @close="restoring = null">
		<form v-if="restoring && overview" id="restore-form" class="backup-form" novalidate @submit.prevent="confirmRestore">
			<p>{{ t('backups_view.restore_lead', 'The parts you pick go back to how they were then, replacing anything changed in them since. Parts you leave out stay as they are now.') }}</p>
			<BackupPartChecks v-model="restoreAll" :parts="restoreParts" :groups="overview.groups" :disabled="asking" />
			<p class="notice notice--small notice--success"><AdminIcon name="circle-check" /><span class="notice__text">{{ t('backups_view.restore_safety', 'The site is backed up as it is now before anything is replaced, so a restore can be undone.') }}</span></p>
			<p v-for="warning in checked?.warnings ?? []" :key="warning" class="notice notice--small notice--warn"><AdminIcon name="triangle-alert" /><span class="notice__text">{{ warning }}</span></p>
			<p v-for="problem in checked?.problems ?? []" :key="problem" class="notice notice--small notice--error" role="alert"><AdminIcon name="triangle-alert" /><span class="notice__text">{{ problem }}</span></p>
		</form>
		<template #footer>
			<button type="button" class="button" @click="restoring = null">{{ t('common.cancel', 'Cancel') }}</button>
			<button type="submit" form="restore-form" class="button button--danger" :disabled="asking || !restoreAll.length || checked === null || (checked?.problems.length ?? 0) > 0">{{ t('backups_view.restore_button', 'Restore') }}</button>
		</template>
	</AdminModal>

	<AdminModal :open="noting !== null" :title="noting?.note ? t('backups_view.edit_note_title', 'Edit Note') : t('backups_view.add_note_title', 'Add a Note')" @close="noting = null">
		<form id="backup-note-form" novalidate @submit.prevent="saveNote">
			<div class="field">
				<label for="backup-note-text">{{ t('backups_view.note_label', 'Note') }}</label>
				<input id="backup-note-text" v-model="noteText" :maxlength="NOTE_LENGTH" autocomplete="off" autofocus aria-describedby="backup-note-text-help">
				<span id="backup-note-text-help" class="field__help">{{ t('backups_view.note_help', 'Optional. Shown beside its date.') }}</span>
			</div>
		</form>
		<template #footer>
			<button type="button" class="button" @click="noting = null">{{ t('common.cancel', 'Cancel') }}</button>
			<button type="submit" form="backup-note-form" class="button button--primary" :disabled="saving">{{ t('backups_view.save_note', 'Save Note') }}</button>
		</template>
	</AdminModal>
</template>

<style scoped>
.backups-table__backup {
	display: grid;
	gap: 2px;
}

/* A note can be long: a light line under the date and file, wrapping. */
.backups-table__note {
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
	overflow-wrap: anywhere;
}

.backups-table__reason {
	color: var(--color-danger);
	white-space: normal;
}

.backups-table__size {
	text-align: right;
	font-variant-numeric: tabular-nums;
}

.backups-table__progress {
	display: grid;
	gap: var(--s-1);
	min-width: 10rem;
}

.backup-form {
	display: grid;
	gap: var(--s-4);
}

.backup-form p {
	margin: 0;
}

/* The restore running: what it's doing under its bar. */
.backups-restore .notice__text {
	display: grid;
	gap: var(--s-2);
}
</style>
