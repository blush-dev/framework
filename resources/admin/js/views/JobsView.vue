<script setup lang="ts">
/**
 * Jobs (D-621; a Tools screen, D-900), with `site.jobs`, and whether cron
 * runs them, in two tabs (D-904): **Jobs**, the background jobs, newest
 * first, filtered by status in the address (`?status=failed`), each
 * opening its own screen (`JobView`), with Retry and Delete; and
 * **Schedule** (`?tab=schedule`), the scheduled tasks, when each last
 * ran and runs next, with Run Now. The Jobs tab counts the failed ones
 * while there are some.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect, { type SelectOption } from '../components/AdminSelect.vue';
import EmptyState from '../components/EmptyState.vue';
import { confirmAction } from '../confirm';
import { formatNumber, formatWhenInline } from '../format';
import { t } from '../messages';
import { follow, JOB_PILLS } from '../jobs';
import { errorMessage, request, type Job, type JobStatus, type JobsOverview, type ScheduledTask } from '../api';

const route  = useRoute();
const router = useRouter();

const tab    = computed<'jobs' | 'schedule'>(() => route.query.tab === 'schedule' ? 'schedule' : 'jobs');
const status = computed(() => typeof route.query.status === 'string' && route.query.status in JOB_PILLS ? route.query.status as JobStatus : '');

const statusOptions = computed<SelectOption[]>(() => [
	{ value: '', label: t('jobs_view.any_status', 'Any status') },
	...(Object.keys(JOB_PILLS) as JobStatus[]).map((key) => ({ value: key, label: JOB_PILLS[key].label }))
]);

function filter(value: string): void {
	void router.replace({ query: value === '' ? {} : { status: value } });
}

const overview = ref<JobsOverview | null>(null);
const error    = ref('');
const loading  = ref(false);
const busy     = ref<string | null>(null);

// Whether cron (or a worker) ran in the last ten minutes, and what to
// say about it.
const runner = computed(() => {
	const data = overview.value;

	if (data === null) {
		return null;
	}

	if (data.mode === 'sync') {
		return { kind: '', icon: 'info' as const, text: t('jobs_view.runner.sync', 'Jobs run when they\'re queued (the "sync" runner), so nothing waits here.'), cron: false };
	}

	const times = [data.runners.cron, data.runners.worker].filter((time): time is string => time !== null).map((time) => new Date(time).getTime());
	const last  = times.length ? Math.max(...times) : null;

	if (last !== null && Date.now() - last < 600_000) {
		return { kind: 'notice--success', icon: 'circle-check' as const, text: t('jobs_view.runner.ran', 'Cron last ran {when}.', { when: formatWhenInline(new Date(last).toISOString()) }), cron: false };
	}

	const fallback = data.mode === 'auto' && data.afterVisits
		? t('jobs_view.runner.after_visits', 'so background jobs run after page visits, and while you wait on them here.')
		: t('jobs_view.runner.while_waiting', 'so background jobs run only while you wait on them here.');

	return {
		kind: 'notice--warn',
		icon: 'triangle-alert' as const,
		text: last === null
			? t('jobs_view.runner.missing', 'Cron isn\'t set up, {fallback} To run them on time, add this line to the server\'s crontab:', { fallback })
			: t('jobs_view.runner.late', 'Cron last ran {when}, {fallback} To run them on time, add this line to the server\'s crontab:', { when: formatWhenInline(new Date(last).toISOString()), fallback }),
		cron: true
	};
});

async function load(): Promise<void> {
	loading.value = true;
	error.value   = '';

	try {
		overview.value = await request<JobsOverview>('GET', status.value === '' ? '/jobs' : `/jobs?status=${status.value}`);
	} catch (caught) {
		error.value = errorMessage(caught, t('jobs_view.errors.jobs', 'The jobs couldn\'t be loaded.'));
	} finally {
		loading.value = false;
	}
}

// Runs a job-changing request, then reloads the list.
async function manage(key: string, task: () => Promise<unknown>, fallback: string): Promise<void> {
	busy.value = key;

	try {
		await task();
	} catch (caught) {
		error.value = errorMessage(caught, fallback);
	} finally {
		busy.value = null;
		await load();
	}
}

function runTask(task: ScheduledTask): Promise<void> {
	return manage(task.job, async () => {
		const { job } = await request<{ job: Job }>('POST', `/jobs/schedule/${task.job}`);

		await follow(job.id);
	}, t('jobs_view.errors.task', '{task} failed.', { task: task.label }));
}

function retry(job: Job): Promise<void> {
	return manage(job.id, () => request('POST', `/jobs/${job.id}/retry`), t('jobs_view.errors.retry', 'The job couldn\'t be queued again.'));
}

async function remove(job: Job): Promise<void> {
	if (!await confirmAction({ title: t('jobs_view.delete_title', 'Delete This Job?'), body: job.status === 'queued' ? t('jobs_view.delete_body_queued', '"{job}" is removed from the list and won\'t run.', { job: job.label }) : t('jobs_view.delete_body', '"{job}" is removed from the list.', { job: job.label }), confirm: t('common.delete', 'Delete'), danger: true })) {
		return;
	}

	await manage(job.id, () => request('DELETE', `/jobs/${job.id}`), t('jobs_view.errors.delete', 'The job couldn\'t be deleted.'));
}

// What a job last said: its failure, else its message.
function said(job: Job): string {
	return job.error !== null && job.status !== 'done' ? job.error : job.message;
}

watch(status, () => {
	void load();
}, { immediate: true });
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ t('jobs_view.title', 'Jobs') }}</h1>
			<p class="page-header__hint">{{ t('jobs_view.hint', 'Work done in the background') }}</p>
		</div>
	</header>


	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-if="!overview" class="loading">{{ t('jobs_view.loading', 'Loading the jobs…') }}</p>
	<template v-else>
		<div v-if="runner" class="notice" :class="runner.kind">
			<AdminIcon :name="runner.icon" />
			<span class="notice__text">
				{{ runner.text }}
				<code v-if="runner.cron" class="jobs__cron">{{ overview.cron }}</code>
			</span>
		</div>

		<nav class="status-tabs" :aria-label="t('jobs_view.title', 'Jobs')">
			<RouterLink class="status-tabs__tab" :to="{ query: {} }" :aria-current="tab === 'jobs' ? 'page' : undefined">
				{{ t('jobs_view.tabs.jobs', 'Jobs') }}
				<span v-if="overview.counts.failed" class="status-tabs__count">{{ t('jobs_view.failed_count', '{count} failed', { count: formatNumber(overview.counts.failed) }) }}</span>
			</RouterLink>
			<RouterLink class="status-tabs__tab" :to="{ query: { tab: 'schedule' } }" :aria-current="tab === 'schedule' ? 'page' : undefined">{{ t('jobs_view.tabs.schedule', 'Schedule') }}</RouterLink>
		</nav>

		<template v-if="tab === 'jobs'">
			<div class="toolbar">
				<div class="toolbar__filter">
					<label class="visually-hidden" for="jobs-status">{{ t('jobs_view.status', 'Status') }}</label>
					<AdminSelect id="jobs-status" :model-value="status" :options="statusOptions" @update:model-value="filter($event)" />
				</div>
			</div>

			<section class="panel" aria-labelledby="jobs-heading" :aria-busy="loading">
				<header class="panel__header">
					<h2 id="jobs-heading">{{ t('jobs_view.jobs', 'Jobs') }}</h2>
					<p class="panel__hint">{{ t('jobs_view.jobs_hint', 'Waiting, running, and recently finished, newest first') }}</p>
					<div class="panel__actions">
						<button type="button" class="button button--small" :disabled="loading" @click="load"><AdminIcon name="refresh-cw" />{{ t('jobs_view.refresh', 'Refresh') }}</button>
					</div>
				</header>
				<EmptyState v-if="!overview.jobs.length" icon="list-checks" :heading="t('jobs_view.no_jobs', 'No Jobs')" :text="status ? t('jobs_view.none_fit', 'No job has that status.') : t('jobs_view.no_jobs_text', 'Nothing is waiting or running, and nothing finished lately.')" />
				<ul v-else class="rows">
					<li v-for="job in overview.jobs" :key="job.id" class="rows__item">
						<div class="rows__main">
							<RouterLink class="rows__title" :to="{ name: 'job', params: { id: job.id } }">{{ job.label }}</RouterLink>
							<span v-if="said(job)" class="rows__text">{{ said(job) }}</span>
							<progress v-if="job.status === 'running' || (job.status === 'queued' && job.progress !== null)" class="progress" max="100" :value="job.progress ?? undefined" :aria-label="t('jobs_view.progress', '{name}: how far along', { name: job.label })" />
							<span class="rows__sub">
								{{ t('jobs_view.queued', 'Queued {when} by {who}', { when: formatWhenInline(job.queued), who: job.by?.name ?? t('jobs_view.the_schedule', 'the schedule') }) }}<template v-if="job.attempts"> · {{ t('jobs_view.attempts', '{count, plural, one {# failed attempt} other {# failed attempts} }', { count: job.attempts }) }}</template><template v-if="job.status === 'queued' && !job.due"> · {{ t('jobs_view.tries_again', 'tries again {when}', { when: formatWhenInline(job.available) }) }}</template>
							</span>
						</div>
						<div class="rows__side">
							<span class="pill" :class="JOB_PILLS[job.status].kind">{{ JOB_PILLS[job.status].label }}</span>
							<button v-if="job.status === 'failed'" type="button" class="button button--small" :disabled="busy !== null" @click="retry(job)">{{ t('jobs_view.retry', 'Retry') }}</button>
							<button v-if="job.status !== 'running'" type="button" class="button button--small button--ghost" :disabled="busy !== null" @click="remove(job)">{{ t('common.delete', 'Delete') }}</button>
						</div>
					</li>
				</ul>
			</section>
		</template>

		<section v-else class="panel" aria-labelledby="tasks-heading">
			<header class="panel__header">
				<h2 id="tasks-heading">{{ t('jobs_view.tasks', 'Scheduled Tasks') }}</h2>
				<p class="panel__hint">{{ t('jobs_view.tasks_hint', 'Queued on their own when they\'re due') }}</p>
			</header>
			<p v-if="!overview.tasks.length" class="panel__body muted">{{ t('jobs_view.no_tasks', 'Nothing is scheduled.') }}</p>
			<ul v-else class="rows">
				<li v-for="task in overview.tasks" :key="task.job" class="rows__item">
					<div class="rows__main">
						<span class="rows__title">{{ task.label }}</span>
						<span class="rows__sub">{{ task.frequency }} · <span class="mono">{{ task.job }}</span></span>
					</div>
					<span class="rows__when">{{ task.last ? t('jobs_view.ran', 'Ran {when}', { when: formatWhenInline(task.last) }) : t('jobs_view.never_run', 'Never run') }}<template v-if="task.next"> · {{ t('jobs_view.next', 'next {when}', { when: formatWhenInline(task.next) }) }}</template></span>
					<div class="rows__side">
						<button type="button" class="button button--small" :disabled="busy !== null" @click="runTask(task)">
							<span v-if="busy === task.job" class="spin" aria-hidden="true" />{{ busy === task.job ? t('jobs_view.working', 'Working…') : t('jobs_view.run_now', 'Run Now') }}
						</button>
					</div>
				</li>
			</ul>
		</section>
	</template>
</template>

<style scoped>
.rows__side .spin {
	margin-right: 6px;
}

/* The cron line, on its own line, to copy. */
.jobs__cron {
	display: block;
	margin-top: var(--s-2);
	overflow-wrap: anywhere;
	user-select: all;
}
</style>
