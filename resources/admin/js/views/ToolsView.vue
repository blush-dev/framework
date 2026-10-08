<script setup lang="ts">
/**
 * Tools (D-540, from the Home sketch): tasks run by hand, and the log
 * they write to. **Actions** turn command-line tasks into buttons for
 * anyone without shell access; they're described by the server
 * (`AdminAction` classes, D-222), so a plugin's show up here with no
 * JavaScript, grouped by where they come from. An action the account
 * may not run isn't listed. A result stays in its row, since on a host
 * with no shell it's all there is, and is announced in a polite live
 * region. An action that queues a job (D-621) is followed to its end
 * here, with its progress. **Jobs**, with `site.jobs`, lists the
 * scheduled tasks (with Run Now) and the background jobs (with Retry and
 * Delete), and says whether cron runs them. **Logs** shows the end of
 * the site's log, read only, with `site.logs` (D-541).
 */

import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import { config } from '../config';
import { confirmAction } from '../confirm';
import { formatSize, formatWhenInline, plural, titleCase } from '../format';
import { follow, jobResult, JOB_PILLS } from '../jobs';
import { can } from '../session';
import { errorMessage, request, type ActionAnswer, type ActionDescription, type ActionGroup, type ActionResult, type Job, type JobsOverview, type LogEntry, type LogTail, type ScheduledTask } from '../api';

type Tab = 'actions' | 'jobs' | 'logs';

const groups  = ref<ActionGroup[] | null>(null);
const error   = ref('');
const running = ref<string | null>(null);
const results = ref<Record<string, ActionResult>>({});
// The job each running action queued, as it goes.
const following = ref<Record<string, Job>>({});

const overview    = ref<JobsOverview | null>(null);
const jobsError   = ref('');
const jobsLoading = ref(false);
const jobBusy     = ref<string | null>(null);

const log        = ref<LogTail | null>(null);
const logError   = ref('');
const logLoading = ref(false);

const tabs = computed(() => [
	{ key: 'actions' as const, label: 'Actions' },
	...(can('site.jobs') ? [{ key: 'jobs' as const, label: 'Jobs' }] : []),
	...(can('site.logs') ? [{ key: 'logs' as const, label: 'Logs' }] : [])
]);
// The tab is in the address (`?tab=logs`), as the entries list's are.
const route = useRoute();
const tab   = computed<Tab>(() => {
	if (route.query.tab === 'jobs' && can('site.jobs')) {
		return 'jobs';
	}

	return route.query.tab === 'logs' && can('site.logs') ? 'logs' : 'actions';
});

const NOTES: Record<Tab, string> = {
	actions: 'An action turns a command-line task into a button, for anyone without shell access. Blush registers some, and a plugin can register its own. An action you don\'t have the capability for isn\'t listed.',
	jobs: 'Work done in the background: tasks on a schedule, and long work someone started, such as a publish. A job that fails is tried again a few times before it\'s marked failed.',
	logs: 'Read only. This is for the moment something says it failed on a host with no way in over SSH.'
};

// Whether cron (or a worker) ran in the last ten minutes, and what to
// say about it.
const runner = computed(() => {
	const data = overview.value;

	if (data === null) {
		return null;
	}

	if (data.mode === 'sync') {
		return { kind: '', icon: 'info' as const, text: 'Jobs run when they\'re queued (the "sync" runner), so nothing waits here.', cron: false };
	}

	const times = [data.runners.cron, data.runners.worker].filter((time): time is string => time !== null).map((time) => new Date(time).getTime());
	const last  = times.length ? Math.max(...times) : null;

	if (last !== null && Date.now() - last < 600_000) {
		return { kind: 'notice--success', icon: 'circle-check' as const, text: `Cron last ran ${formatWhenInline(new Date(last).toISOString())}.`, cron: false };
	}

	const fallback = data.mode === 'auto' && data.afterVisits
		? 'so background jobs run after page visits, and while you wait on them here.'
		: 'so background jobs run only while you wait on them here.';

	return {
		kind: 'notice--warn',
		icon: 'triangle-alert' as const,
		text: `${last === null ? 'Cron isn\'t set up' : `Cron last ran ${formatWhenInline(new Date(last).toISOString())}`}, ${fallback} To run them on time, add this line to the server's crontab:`,
		cron: true
	};
});

const SOURCES: Record<ActionGroup['source']['kind'], string> = {
	core: 'Registered by Blush',
	site: 'Registered by this site',
	plugin: 'Registered by a plugin',
	other: 'Registered elsewhere'
};

async function load(): Promise<void> {
	try {
		groups.value = (await request<{ groups: ActionGroup[] }>('GET', '/actions')).groups;
	} catch (caught) {
		error.value = errorMessage(caught, 'The actions couldn\'t be loaded.');
	}
}

async function loadLog(): Promise<void> {
	logLoading.value = true;
	logError.value   = '';

	try {
		log.value = await request<LogTail>('GET', '/logs');
	} catch (caught) {
		logError.value = errorMessage(caught, 'The log couldn\'t be read.');
	} finally {
		logLoading.value = false;
	}
}

async function run(action: ActionDescription): Promise<void> {
	if (action.confirm !== null && !await confirmAction({ title: `${action.label}?`, body: action.confirm, confirm: action.label })) {
		return;
	}

	running.value = action.name;

	try {
		const answer = await request<ActionAnswer>('POST', `/actions/${encodeURIComponent(action.name)}`);

		results.value[action.name] = 'job' in answer
			? jobResult(await follow(answer.job, (job) => {
				following.value[action.name] = job;
			}))
			: answer;
	} catch (caught) {
		results.value[action.name] = {
			successful: false,
			message: errorMessage(caught, `${action.label} failed.`),
			details: []
		};
	} finally {
		running.value = null;
		delete following.value[action.name];
	}
}

async function loadJobs(): Promise<void> {
	jobsLoading.value = true;
	jobsError.value   = '';

	try {
		overview.value = await request<JobsOverview>('GET', '/jobs');
	} catch (caught) {
		jobsError.value = errorMessage(caught, 'The jobs couldn\'t be loaded.');
	} finally {
		jobsLoading.value = false;
	}
}

// Runs a job-changing request, then reloads the list.
async function manage(key: string, task: () => Promise<unknown>, fallback: string): Promise<void> {
	jobBusy.value = key;

	try {
		await task();
	} catch (caught) {
		jobsError.value = errorMessage(caught, fallback);
	} finally {
		jobBusy.value = null;
		await loadJobs();
	}
}

function runTask(task: ScheduledTask): Promise<void> {
	return manage(task.job, async () => {
		const { job } = await request<{ job: Job }>('POST', `/jobs/schedule/${task.job}`);

		await follow(job.id);
	}, `${task.label} failed.`);
}

function retry(job: Job): Promise<void> {
	return manage(job.id, () => request('POST', `/jobs/${job.id}/retry`), 'The job couldn\'t be queued again.');
}

async function remove(job: Job): Promise<void> {
	if (!await confirmAction({ title: 'Delete This Job?', body: `"${job.label}" is removed from the list${job.status === 'queued' ? ' and won\'t run' : ''}.`, confirm: 'Delete', danger: true })) {
		return;
	}

	await manage(job.id, () => request('DELETE', `/jobs/${job.id}`), 'The job couldn\'t be deleted.');
}

// What a job last said: its failure, else its message.
function said(job: Job): string {
	return job.error !== null && job.status !== 'done' ? job.error : job.message;
}

// Levels in five letters, so messages line up.
const LEVELS: Record<string, string> = { emergency: 'EMERG', critical: 'CRIT', warning: 'WARN', notice: 'NOTE' };

function levelName(entry: LogEntry): string {
	return (LEVELS[entry.level] ?? entry.level.toUpperCase()).padEnd(5);
}

function tone(entry: LogEntry): string {
	return ['emergency', 'alert', 'critical', 'error'].includes(entry.level) ? 'is-error' : (entry.level === 'warning' ? 'is-warn' : 'is-info');
}

// "2026-10-05 17:22:46", as the logger wrote it.
function stamp(entry: LogEntry): string {
	return entry.time.slice(0, 19).replace('T', ' ');
}

// Jobs and logs load when first shown.
watch(tab, (value) => {
	if (value === 'logs' && log.value === null && !logLoading.value) {
		void loadLog();
	}

	if (value === 'jobs' && overview.value === null && !jobsLoading.value) {
		void loadJobs();
	}
}, { immediate: true });

onMounted(() => {
	void load();
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Tools</h1>
			<p class="page-header__hint">Tasks you run by hand{{ can('site.jobs') ? ', work done in the background' : '' }}{{ can('site.logs') ? ', and the log they write to' : '' }}</p>
		</div>
	</header>

	<nav v-if="tabs.length > 1" class="status-tabs" aria-label="Tools">
		<RouterLink v-for="item in tabs" :key="item.key" class="status-tabs__tab" :to="{ query: item.key === 'actions' ? {} : { tab: item.key } }" :aria-current="tab === item.key ? 'page' : undefined">{{ item.label }}</RouterLink>
	</nav>

	<p class="notice"><AdminIcon name="info" /><span class="notice__text">{{ NOTES[tab] }}</span></p>

	<template v-if="tab === 'actions'">
		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
		<p v-else-if="!groups" class="loading">Loading the actions…</p>
		<div v-else-if="!groups.length" class="panel">
			<EmptyState icon="wrench" heading="No Actions for You" text="Your role can't run any of the actions on this site." />
		</div>
		<section v-for="(group, index) in groups ?? []" :key="`${group.source.kind}:${group.source.label}`" class="panel" :aria-labelledby="`actions-${index}`">
			<header class="panel__header">
				<h2 :id="`actions-${index}`">{{ group.source.kind === 'core' ? 'Core' : group.source.label }}</h2>
				<p class="panel__hint">{{ SOURCES[group.source.kind] }}</p>
			</header>
			<ul class="actions">
				<li v-for="action in group.actions" :key="action.name" class="action">
					<p class="action__text">
						<span class="action__name">{{ titleCase(action.label) }}<span v-if="action.confirm" class="action__asks"> · asks first</span></span>
						<span :id="`action-${action.name}`" class="action__description">{{ action.description }}</span>
					</p>
					<button type="button" class="button" :disabled="running !== null" :aria-describedby="`action-${action.name}`" @click="run(action)">
						<span v-if="running === action.name" class="spin" aria-hidden="true" />{{ running === action.name ? 'Working…' : titleCase(action.label) }}
					</button>
					<div class="action__result" aria-live="polite">
						<div v-if="following[action.name]" class="action__progress">
							<progress class="progress" max="100" :value="following[action.name]?.progress ?? undefined" :aria-label="`${action.label}: how far along`" />
							<span class="action__description">{{ following[action.name]?.message || (following[action.name]?.status === 'queued' ? 'Waiting its turn…' : 'Working…') }}</span>
						</div>
						<div v-else-if="results[action.name]" class="notice notice--small" :class="results[action.name]?.successful ? 'notice--success' : 'notice--error'">
							<p>{{ results[action.name]?.message }}</p>
							<ul v-if="results[action.name]?.details.length">
								<li v-for="detail in results[action.name]?.details" :key="detail">{{ detail }}</li>
							</ul>
						</div>
					</div>
				</li>
			</ul>
			<p v-if="index === (groups?.length ?? 0) - 1 && can('site.health')" class="panel__foot">
				<RouterLink class="lnk" :to="{ name: 'health' }">Open Site Health<AdminIcon name="arrow-right" /></RouterLink>
			</p>
		</section>
	</template>

	<template v-else-if="tab === 'jobs'">
		<p v-if="jobsError" class="notice notice--error" role="alert">{{ jobsError }}</p>
		<p v-if="!overview" class="loading">Loading the jobs…</p>
		<template v-else>
			<div v-if="runner" class="notice" :class="runner.kind">
				<AdminIcon :name="runner.icon" />
				<span class="notice__text">
					{{ runner.text }}
					<code v-if="runner.cron" class="tools__cron">{{ overview.cron }}</code>
				</span>
			</div>

			<section class="panel" aria-labelledby="tasks-heading">
				<header class="panel__header">
					<h2 id="tasks-heading">Scheduled Tasks</h2>
					<p class="panel__hint">Queued on their own when they're due</p>
				</header>
				<p v-if="!overview.tasks.length" class="panel__body muted">Nothing is scheduled.</p>
				<ul v-else class="rows">
					<li v-for="task in overview.tasks" :key="task.job" class="rows__item">
						<div class="rows__main">
							<span class="rows__title">{{ task.label }}</span>
							<span class="rows__sub">{{ task.frequency }} · <span class="mono">{{ task.job }}</span></span>
						</div>
						<span class="rows__when">{{ task.last ? `Ran ${formatWhenInline(task.last)}` : 'Never run' }}</span>
						<div class="rows__side">
							<button type="button" class="button button--small" :disabled="jobBusy !== null" @click="runTask(task)">
								<span v-if="jobBusy === task.job" class="spin" aria-hidden="true" />{{ jobBusy === task.job ? 'Working…' : 'Run Now' }}
							</button>
						</div>
					</li>
				</ul>
			</section>

			<section class="panel" aria-labelledby="jobs-heading">
				<header class="panel__header">
					<h2 id="jobs-heading">Jobs</h2>
					<p class="panel__hint">Waiting, running, and recently finished, newest first</p>
					<div class="panel__actions">
						<button type="button" class="button button--small" :disabled="jobsLoading" @click="loadJobs"><AdminIcon name="refresh-cw" />Refresh</button>
					</div>
				</header>
				<EmptyState v-if="!overview.jobs.length" icon="list-checks" heading="No Jobs" text="Nothing is waiting or running, and nothing finished lately." />
				<ul v-else class="rows">
					<li v-for="job in overview.jobs" :key="job.id" class="rows__item">
						<div class="rows__main">
							<span class="rows__title">{{ job.label }}</span>
							<span v-if="said(job)" class="rows__text">{{ said(job) }}</span>
							<progress v-if="job.status === 'running' || (job.status === 'queued' && job.progress !== null)" class="progress" max="100" :value="job.progress ?? undefined" :aria-label="`${job.label}: how far along`" />
							<span class="rows__sub">
								Queued {{ formatWhenInline(job.queued) }} by {{ job.account ?? 'the schedule' }}<template v-if="job.attempts"> · {{ plural(job.attempts, 'failed attempt') }}</template><template v-if="job.status === 'queued' && !job.due"> · tries again {{ formatWhenInline(job.available) }}</template>
							</span>
						</div>
						<div class="rows__side">
							<span class="pill" :class="JOB_PILLS[job.status].kind">{{ JOB_PILLS[job.status].label }}</span>
							<button v-if="job.status === 'failed'" type="button" class="button button--small" :disabled="jobBusy !== null" @click="retry(job)">Retry</button>
							<button v-if="job.status !== 'running'" type="button" class="button button--small button--ghost" :disabled="jobBusy !== null" @click="remove(job)">Delete</button>
						</div>
					</li>
				</ul>
			</section>
		</template>
	</template>

	<section v-else class="panel" aria-labelledby="log-heading">
			<header class="panel__header">
				<h2 id="log-heading">Log</h2>
				<p v-if="log?.file" class="panel__hint">The last {{ plural(log.entries.length, 'entry', 'entries') }} in <span class="mono">{{ log.file }}</span>, newest first ({{ formatSize(log.size) }})</p>
				<div class="panel__actions">
					<button type="button" class="button button--small" :disabled="logLoading" @click="loadLog"><AdminIcon name="refresh-cw" />Refresh</button>
					<a v-if="log?.file && log.size" class="button button--small" :href="`${config.api}/logs/download`" download><AdminIcon name="download" />Download</a>
				</div>
			</header>
			<p v-if="logError" class="panel__body notice notice--error" role="alert">{{ logError }}</p>
			<p v-else-if="!log" class="panel__body loading">Reading the log…</p>
			<p v-else-if="log.driver !== 'file'" class="panel__body muted">{{ log.driver === 'stderr' ? 'The site logs to the server\'s error output, so there\'s no file to read here.' : 'Logging is off, so there\'s nothing to read.' }}</p>
			<p v-else-if="!log.entries.length" class="panel__body muted">Nothing has been logged.</p>
			<ol v-else class="tools__log">
				<li v-for="(entry, index) in log.entries" :key="index">
					<details v-if="entry.details" class="tools__entry">
						<summary class="tools__line"><span class="tools__time">{{ stamp(entry) }}</span> <span :class="tone(entry)">{{ levelName(entry) }}</span> <span class="tools__message">{{ entry.message }}</span></summary>
						<pre class="tools__details">{{ entry.details }}</pre>
					</details>
					<p v-else class="tools__line"><span class="tools__time">{{ stamp(entry) }}</span> <span :class="tone(entry)">{{ levelName(entry) }}</span> <span class="tools__message">{{ entry.message }}</span></p>
				</li>
			</ol>
	</section>
</template>

<style scoped>
.actions {
	margin: 0;
	padding: 0;
	list-style: none;
}

.action {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	align-items: start;
	gap: 0 16px;
	padding: var(--pad-row) var(--pad-x);
}

.action + .action {
	border-top: 1px solid var(--border);
}

.action__text {
	display: grid;
	gap: 2px;
	margin: 0;
}

.action__name {
	font-weight: 500;
}

.action__asks {
	color: var(--fg-3);
	font-size: var(--text-sm);
	font-weight: 400;
}

.action__description {
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.action__result {
	grid-column: 1 / -1;
}

.action__result:not(:empty) {
	margin-top: 10px;
}

.action .spin,
.rows__side .spin {
	margin-right: 6px;
}

.action__progress {
	display: grid;
	gap: 6px;
}

/* The cron line, on its own line, to copy. */
.tools__cron {
	display: block;
	margin-top: var(--s-2);
	overflow-wrap: anywhere;
	user-select: all;
}

.tools__log {
	max-height: 60vh;
	margin: 0;
	padding: var(--s-4) var(--pad-x);
	overflow: auto;
	list-style: none;
	color: var(--fg-2);
	font-family: var(--font-mono);
	font-size: var(--text-sm);
	line-height: 2;
}

.tools__line {
	display: flex;
	gap: 1ch;
	margin: 0;
	white-space: pre;
}

summary.tools__line {
	cursor: pointer;
	list-style: none;
}

summary.tools__line::-webkit-details-marker {
	display: none;
}

summary.tools__line:hover .tools__message {
	color: var(--fg);
}

.tools__message {
	overflow: hidden;
	text-overflow: ellipsis;
}

/* An entry with a trace says so after its message, and opens to show it. */
summary.tools__line .tools__message::after {
	content: " …";
	color: var(--fg-3);
}

.tools__entry[open] .tools__message {
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}

.tools__details {
	margin: 2px 0 var(--s-2) 2ch;
	padding-left: var(--s-3);
	overflow-x: auto;
	border-left: 1px solid var(--border);
	color: var(--fg-3);
	font: inherit;
}

.tools__time,
.is-info {
	color: var(--fg-3);
}

.is-error {
	color: var(--danger);
}

.is-warn {
	color: var(--warn);
}
</style>
