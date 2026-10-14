<script setup lang="ts">
/**
 * Tools (D-540, from the Home sketch): tasks run by hand, and the log
 * they write to. The **Tools** tab has a button for each tool that
 * needs no input and is a button (D-761), for anyone without shell access;
 * they're described by the server (`Blush\Tool\Tool` classes), so a
 * plugin's show up here with no JavaScript, grouped by where they come
 * from. A tool the account may not use isn't listed. A result stays in
 * its row, since on a host with no shell it's all there is, and is
 * announced in a polite live region. A tool that runs in the background
 * (D-621) is followed to its end here, with its progress. **Jobs**, with `site.jobs`, lists the
 * scheduled tasks (with Run Now) and the background jobs (with Retry and
 * Delete), and says whether cron runs them. **Logs** shows the end of
 * the site's log, read only, with `site.logs` (D-541). **Activity**, with
 * `site.activity`, lists every change to entries and media, by whom and
 * how (the admin, an app, the command line, the API), filtered in the
 * address (`?tab=activity&client=Claude`), with Undo (D-758).
 */

import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import ActivityRows from '../components/ActivityRows.vue';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect, { type SelectOption } from '../components/AdminSelect.vue';
import EmptyState from '../components/EmptyState.vue';
import { config } from '../config';
import { confirmAction } from '../confirm';
import { formatSize, formatWhenInline, titleCase } from '../format';
import { t } from '../messages';
import MessageText from '../components/MessageText.vue';
import { follow, jobResult, JOB_PILLS } from '../jobs';
import { loadActivity, type ActivityPage } from '../activity';
import { can } from '../session';
import { errorMessage, request, type ToolAnswer, type ToolButton, type ToolGroup, type ToolResult, type Job, type JobsOverview, type LogEntry, type LogTail, type ScheduledTask } from '../api';

type Tab = 'tools' | 'jobs' | 'logs' | 'activity';

const groups  = ref<ToolGroup[] | null>(null);
const error   = ref('');
const running = ref<string | null>(null);
const results = ref<Record<string, ToolResult>>({});
// The job each running tool queued, as it goes.
const following = ref<Record<string, Job>>({});

const overview    = ref<JobsOverview | null>(null);
const jobsError   = ref('');
const jobsLoading = ref(false);
const jobBusy     = ref<string | null>(null);

const log        = ref<LogTail | null>(null);
const logError   = ref('');
const logLoading = ref(false);

const activity        = ref<ActivityPage | null>(null);
const activityError   = ref('');
const activityLoading = ref(false);

const tabs = computed(() => [
	{ key: 'tools' as const, label: t('tools_view.tabs.tools', 'Tools') },
	...(can('site.jobs') ? [{ key: 'jobs' as const, label: t('tools_view.tabs.jobs', 'Jobs') }] : []),
	...(can('site.logs') ? [{ key: 'logs' as const, label: t('tools_view.tabs.logs', 'Logs') }] : []),
	...(can('site.activity') ? [{ key: 'activity' as const, label: t('tools_view.tabs.activity', 'Activity') }] : [])
]);
// The tab is in the address (`?tab=logs`), as the entries list's are.
const route  = useRoute();
const router = useRouter();
const tab    = computed<Tab>(() => {
	if (route.query.tab === 'jobs' && can('site.jobs')) {
		return 'jobs';
	}

	if (route.query.tab === 'activity' && can('site.activity')) {
		return 'activity';
	}

	return route.query.tab === 'logs' && can('site.logs') ? 'logs' : 'tools';
});

const NOTES: Record<Tab, string> = {
	tools: t('tools_view.notes.tools', 'A tool is something the site can do: here, as a button, for anyone without shell access, and for connected apps and the command line too. Blush registers some, and a plugin can register its own. A tool you don\'t have the capability for isn\'t listed.'),
	jobs: t('tools_view.notes.jobs', 'Work done in the background: tasks on a schedule, and long work someone started, such as a publish. A job that fails is tried again a few times before it\'s marked failed.'),
	logs: t('tools_view.notes.logs', 'Read only. This is for the moment something says it failed on a host with no way in over SSH.'),
	activity: t('tools_view.notes.activity', 'Every change to entries and media, made here, by a connected app, on the command line, or through the API. What a change replaced is kept for 30 days, for Undo; the change is listed for 90.')
};

// The Activity tab's filters, from the address.
const query = (key: string): string => typeof route.query[key] === 'string' ? route.query[key] : '';

const activityQuery = computed(() => ({
	account: query('account'),
	via: query('via'),
	client: query('client'),
	action: query('action'),
	page: Number(query('page')) || 1
}));

// How a change came: a way, or an app by its name.
const howValue = computed(() => query('client') !== '' ? `client:${query('client')}` : query('via'));

const accountOptions = computed<SelectOption[]>(() => [
	{ value: '', label: t('tools_view.filters.anyone', 'Anyone') },
	...(activity.value?.accounts ?? []).map((account) => ({ value: account.username, label: account.name }))
]);

const howOptions = computed<SelectOption[]>(() => [
	{ value: '', label: t('tools_view.filters.any_way', 'Any way') },
	...(activity.value?.via ?? []).map((via) => ({ value: via.value, label: via.label })),
	...(activity.value?.clients ?? []).map((client) => ({ value: `client:${client}`, label: client }))
]);

const actionOptions = computed<SelectOption[]>(() => [
	{ value: '', label: t('tools_view.filters.any_change', 'Any change') },
	...(activity.value?.actions ?? [])
]);

function filterActivity(changes: Record<string, string>): void {
	const next: Record<string, string> = { tab: 'activity' };

	for (const [key, value] of Object.entries({ account: query('account'), via: query('via'), client: query('client'), action: query('action'), ...changes })) {
		if (value !== '') {
			next[key] = value;
		}
	}

	void router.replace({ query: next });
}

function chooseHow(value: string): void {
	filterActivity(value.startsWith('client:') ? { via: '', client: value.slice(7) } : { via: value, client: '' });
}

async function loadChanges(): Promise<void> {
	activityLoading.value = true;
	activityError.value   = '';

	try {
		activity.value = await loadActivity(activityQuery.value);
	} catch (caught) {
		activityError.value = errorMessage(caught, t('tools_view.errors.activity', 'The activity couldn\'t be loaded.'));
	} finally {
		activityLoading.value = false;
	}
}

// Whether cron (or a worker) ran in the last ten minutes, and what to
// say about it.
const runner = computed(() => {
	const data = overview.value;

	if (data === null) {
		return null;
	}

	if (data.mode === 'sync') {
		return { kind: '', icon: 'info' as const, text: t('tools_view.runner.sync', 'Jobs run when they\'re queued (the "sync" runner), so nothing waits here.'), cron: false };
	}

	const times = [data.runners.cron, data.runners.worker].filter((time): time is string => time !== null).map((time) => new Date(time).getTime());
	const last  = times.length ? Math.max(...times) : null;

	if (last !== null && Date.now() - last < 600_000) {
		return { kind: 'notice--success', icon: 'circle-check' as const, text: t('tools_view.runner.ran', 'Cron last ran {when}.', { when: formatWhenInline(new Date(last).toISOString()) }), cron: false };
	}

	const fallback = data.mode === 'auto' && data.afterVisits
		? t('tools_view.runner.after_visits', 'so background jobs run after page visits, and while you wait on them here.')
		: t('tools_view.runner.while_waiting', 'so background jobs run only while you wait on them here.');

	return {
		kind: 'notice--warn',
		icon: 'triangle-alert' as const,
		text: last === null
			? t('tools_view.runner.missing', 'Cron isn\'t set up, {fallback} To run them on time, add this line to the server\'s crontab:', { fallback })
			: t('tools_view.runner.late', 'Cron last ran {when}, {fallback} To run them on time, add this line to the server\'s crontab:', { when: formatWhenInline(new Date(last).toISOString()), fallback }),
		cron: true
	};
});

const SOURCES: Record<ToolGroup['source']['kind'], string> = {
	core: t('tools_view.sources.core', 'Registered by Blush'),
	site: t('tools_view.sources.site', 'Registered by this site'),
	plugin: t('tools_view.sources.plugin', 'Registered by a plugin'),
	other: t('tools_view.sources.other', 'Registered elsewhere')
};

async function load(): Promise<void> {
	try {
		groups.value = (await request<{ groups: ToolGroup[] }>('GET', '/tools')).groups;
	} catch (caught) {
		error.value = errorMessage(caught, t('tools_view.errors.tools', 'The tools couldn\'t be loaded.'));
	}
}

async function loadLog(): Promise<void> {
	logLoading.value = true;
	logError.value   = '';

	try {
		log.value = await request<LogTail>('GET', '/logs');
	} catch (caught) {
		logError.value = errorMessage(caught, t('tools_view.errors.log', 'The log couldn\'t be read.'));
	} finally {
		logLoading.value = false;
	}
}

async function run(tool: ToolButton): Promise<void> {
	if (tool.confirm !== null && !await confirmAction({ title: t('tools_view.run_title', '{tool}?', { tool: tool.title }), body: tool.confirm, confirm: tool.title })) {
		return;
	}

	running.value = tool.key;

	try {
		const answer = await request<ToolAnswer>('POST', `/tools/${tool.key.split('/').map(encodeURIComponent).join('/')}`);

		results.value[tool.key] = 'job' in answer
			? jobResult(await follow(answer.job, (job) => {
				following.value[tool.key] = job;
			}))
			: answer;
	} catch (caught) {
		results.value[tool.key] = {
			successful: false,
			message: errorMessage(caught, t('tools_view.errors.tool', '{tool} failed.', { tool: tool.title })),
			details: []
		};
	} finally {
		running.value = null;
		delete following.value[tool.key];
	}
}

async function loadJobs(): Promise<void> {
	jobsLoading.value = true;
	jobsError.value   = '';

	try {
		overview.value = await request<JobsOverview>('GET', '/jobs');
	} catch (caught) {
		jobsError.value = errorMessage(caught, t('tools_view.errors.jobs', 'The jobs couldn\'t be loaded.'));
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
	}, t('tools_view.errors.task', '{task} failed.', { task: task.label }));
}

function retry(job: Job): Promise<void> {
	return manage(job.id, () => request('POST', `/jobs/${job.id}/retry`), t('tools_view.errors.retry', 'The job couldn\'t be queued again.'));
}

async function remove(job: Job): Promise<void> {
	if (!await confirmAction({ title: t('tools_view.delete_title', 'Delete This Job?'), body: job.status === 'queued' ? t('tools_view.delete_body_queued', '"{job}" is removed from the list and won\'t run.', { job: job.label }) : t('tools_view.delete_body', '"{job}" is removed from the list.', { job: job.label }), confirm: t('common.delete', 'Delete'), danger: true })) {
		return;
	}

	await manage(job.id, () => request('DELETE', `/jobs/${job.id}`), t('tools_view.errors.delete', 'The job couldn\'t be deleted.'));
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

watch([tab, activityQuery], ([value]) => {
	if (value === 'activity') {
		void loadChanges();
	}
}, { immediate: true });

onMounted(() => {
	void load();
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ t('tools_view.title', 'Tools') }}</h1>
			<p class="page-header__hint">{{ t('tools_view.hint', 'Tasks you run by hand{jobs}{logs}{activity}', { jobs: can('site.jobs') ? t('tools_view.hint_jobs', ', work done in the background') : '', logs: can('site.logs') ? t('tools_view.hint_logs', ', the log they write to') : '', activity: can('site.activity') ? t('tools_view.hint_activity', ', and what changed') : '' }) }}</p>
		</div>
	</header>

	<nav v-if="tabs.length > 1" class="status-tabs" :aria-label="t('tools_view.title', 'Tools')">
		<RouterLink v-for="item in tabs" :key="item.key" class="status-tabs__tab" :to="{ query: item.key === 'tools' ? {} : { tab: item.key } }" :aria-current="tab === item.key ? 'page' : undefined">{{ item.label }}</RouterLink>
	</nav>

	<p class="notice"><AdminIcon name="info" /><span class="notice__text">{{ NOTES[tab] }}</span></p>

	<template v-if="tab === 'tools'">
		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
		<p v-else-if="!groups" class="loading">{{ t('tools_view.loading_tools', 'Loading the tools…') }}</p>
		<div v-else-if="!groups.length" class="panel">
			<EmptyState icon="wrench" :heading="t('tools_view.no_tools', 'No Tools for You')" :text="t('tools_view.no_tools_text', 'Your role can\'t use any of the tools on this site.')" />
		</div>
		<section v-for="(group, index) in groups ?? []" :key="`${group.source.kind}:${group.source.label}`" class="panel" :aria-labelledby="`tools-${index}`">
			<header class="panel__header">
				<h2 :id="`tools-${index}`">{{ group.source.kind === 'core' ? t('tools_view.core', 'Core') : group.source.label }}</h2>
				<p class="panel__hint">{{ SOURCES[group.source.kind] }}</p>
			</header>
			<ul class="tools-list">
				<li v-for="tool in group.tools" :key="tool.key" class="tool">
					<p class="tool__text">
						<span class="tool__name">{{ titleCase(tool.title) }}<span v-if="tool.confirm" class="tool__asks"> {{ t('tools_view.asks_first', '· asks first') }}</span></span>
						<span :id="`tool-${tool.key}`" class="tool__description">{{ tool.description }}</span>
					</p>
					<button type="button" class="button" :disabled="running !== null" :aria-describedby="`tool-${tool.key}`" @click="run(tool)">
						<span v-if="running === tool.key" class="spin" aria-hidden="true" />{{ running === tool.key ? t('tools_view.working', 'Working…') : titleCase(tool.title) }}
					</button>
					<div class="tool__result" aria-live="polite">
						<div v-if="following[tool.key]" class="tool__progress">
							<progress class="progress" max="100" :value="following[tool.key]?.progress ?? undefined" :aria-label="t('tools_view.progress', '{name}: how far along', { name: tool.title })" />
							<span class="tool__description">{{ following[tool.key]?.message || (following[tool.key]?.status === 'queued' ? t('tools_view.waiting', 'Waiting its turn…') : t('tools_view.working', 'Working…')) }}</span>
						</div>
						<div v-else-if="results[tool.key]" class="notice notice--small" :class="results[tool.key]?.successful ? 'notice--success' : 'notice--error'">
							<p>{{ results[tool.key]?.message }}</p>
							<ul v-if="results[tool.key]?.details.length">
								<li v-for="detail in results[tool.key]?.details" :key="detail">{{ detail }}</li>
							</ul>
						</div>
					</div>
				</li>
			</ul>
			<p v-if="index === (groups?.length ?? 0) - 1 && can('site.health')" class="panel__foot">
				<RouterLink class="lnk" :to="{ name: 'health' }">{{ t('tools_view.open_health', 'Open Site Health') }}<AdminIcon name="arrow-right" /></RouterLink>
			</p>
		</section>
	</template>

	<template v-else-if="tab === 'jobs'">
		<p v-if="jobsError" class="notice notice--error" role="alert">{{ jobsError }}</p>
		<p v-if="!overview" class="loading">{{ t('tools_view.loading_jobs', 'Loading the jobs…') }}</p>
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
					<h2 id="tasks-heading">{{ t('tools_view.tasks', 'Scheduled Tasks') }}</h2>
					<p class="panel__hint">{{ t('tools_view.tasks_hint', 'Queued on their own when they\'re due') }}</p>
				</header>
				<p v-if="!overview.tasks.length" class="panel__body muted">{{ t('tools_view.no_tasks', 'Nothing is scheduled.') }}</p>
				<ul v-else class="rows">
					<li v-for="task in overview.tasks" :key="task.job" class="rows__item">
						<div class="rows__main">
							<span class="rows__title">{{ task.label }}</span>
							<span class="rows__sub">{{ task.frequency }} · <span class="mono">{{ task.job }}</span></span>
						</div>
						<span class="rows__when">{{ task.last ? t('tools_view.ran', 'Ran {when}', { when: formatWhenInline(task.last) }) : t('tools_view.never_run', 'Never run') }}</span>
						<div class="rows__side">
							<button type="button" class="button button--small" :disabled="jobBusy !== null" @click="runTask(task)">
								<span v-if="jobBusy === task.job" class="spin" aria-hidden="true" />{{ jobBusy === task.job ? t('tools_view.working', 'Working…') : t('tools_view.run_now', 'Run Now') }}
							</button>
						</div>
					</li>
				</ul>
			</section>

			<section class="panel" aria-labelledby="jobs-heading">
				<header class="panel__header">
					<h2 id="jobs-heading">{{ t('tools_view.jobs', 'Jobs') }}</h2>
					<p class="panel__hint">{{ t('tools_view.jobs_hint', 'Waiting, running, and recently finished, newest first') }}</p>
					<div class="panel__actions">
						<button type="button" class="button button--small" :disabled="jobsLoading" @click="loadJobs"><AdminIcon name="refresh-cw" />{{ t('tools_view.refresh', 'Refresh') }}</button>
					</div>
				</header>
				<EmptyState v-if="!overview.jobs.length" icon="list-checks" :heading="t('tools_view.no_jobs', 'No Jobs')" :text="t('tools_view.no_jobs_text', 'Nothing is waiting or running, and nothing finished lately.')" />
				<ul v-else class="rows">
					<li v-for="job in overview.jobs" :key="job.id" class="rows__item">
						<div class="rows__main">
							<span class="rows__title">{{ job.label }}</span>
							<span v-if="said(job)" class="rows__text">{{ said(job) }}</span>
							<progress v-if="job.status === 'running' || (job.status === 'queued' && job.progress !== null)" class="progress" max="100" :value="job.progress ?? undefined" :aria-label="t('tools_view.progress', '{name}: how far along', { name: job.label })" />
							<span class="rows__sub">
								{{ t('tools_view.queued', 'Queued {when} by {who}', { when: formatWhenInline(job.queued), who: job.account ?? t('tools_view.the_schedule', 'the schedule') }) }}<template v-if="job.attempts"> · {{ t('tools_view.attempts', '{count, plural, one {# failed attempt} other {# failed attempts} }', { count: job.attempts }) }}</template><template v-if="job.status === 'queued' && !job.due"> · {{ t('tools_view.tries_again', 'tries again {when}', { when: formatWhenInline(job.available) }) }}</template>
							</span>
						</div>
						<div class="rows__side">
							<span class="pill" :class="JOB_PILLS[job.status].kind">{{ JOB_PILLS[job.status].label }}</span>
							<button v-if="job.status === 'failed'" type="button" class="button button--small" :disabled="jobBusy !== null" @click="retry(job)">{{ t('tools_view.retry', 'Retry') }}</button>
							<button v-if="job.status !== 'running'" type="button" class="button button--small button--ghost" :disabled="jobBusy !== null" @click="remove(job)">{{ t('common.delete', 'Delete') }}</button>
						</div>
					</li>
				</ul>
			</section>
		</template>
	</template>

	<template v-else-if="tab === 'activity'">
		<div class="toolbar">
			<div class="toolbar__filter">
				<label class="visually-hidden" for="activity-account">{{ t('tools_view.filters.who', 'Who') }}</label>
				<AdminSelect id="activity-account" :model-value="query('account')" :options="accountOptions" @update:model-value="filterActivity({ account: $event, page: '' })" />
			</div>
			<div class="toolbar__filter">
				<label class="visually-hidden" for="activity-how">{{ t('tools_view.filters.how', 'How') }}</label>
				<AdminSelect id="activity-how" :model-value="howValue" :options="howOptions" @update:model-value="chooseHow($event)" />
			</div>
			<div class="toolbar__filter">
				<label class="visually-hidden" for="activity-action">{{ t('tools_view.filters.change', 'Change') }}</label>
				<AdminSelect id="activity-action" :model-value="query('action')" :options="actionOptions" @update:model-value="filterActivity({ action: $event, page: '' })" />
			</div>
			<span class="toolbar__count" aria-live="polite">{{ activity ? t('tools_view.changes', '{count, plural, one {# change} other {# changes} }', { count: activity.total }) : t('common.loading', 'Loading…') }}</span>
		</div>

		<p v-if="activityError" class="notice notice--error" role="alert">{{ activityError }}</p>

		<section class="panel" aria-labelledby="activity-heading" :aria-busy="activityLoading">
			<header class="panel__header">
				<h2 id="activity-heading">{{ t('tools_view.activity', 'Activity') }}</h2>
				<p class="panel__hint">{{ t('tools_view.newest_first', 'Newest first') }}</p>
				<div class="panel__actions">
					<button type="button" class="button button--small" :disabled="activityLoading" @click="loadChanges"><AdminIcon name="refresh-cw" />{{ t('tools_view.refresh', 'Refresh') }}</button>
				</div>
			</header>
			<p v-if="!activity" class="panel__body loading">{{ t('tools_view.loading_activity', 'Loading the activity…') }}</p>
			<EmptyState v-else-if="!activity.changes.length" icon="clock" :heading="t('tools_view.nothing_changed', 'Nothing Changed')" :text="activityQuery.account || activityQuery.via || activityQuery.client || activityQuery.action ? t('tools_view.no_change_fits', 'No change fits the filters.') : t('tools_view.no_changes', 'Changes to entries and media are listed here as they\'re made.')" />
			<ActivityRows v-else :changes="activity.changes" @changed="loadChanges" />
			<p v-if="activity && activity.pages > 1" class="panel__foot tools__pages">
				<RouterLink v-if="activity.page > 1" class="lnk" :to="{ query: { ...route.query, page: String(activity.page - 1) } }">{{ t('tools_view.newer', 'Newer') }}</RouterLink>
				<span class="muted">{{ t('tools_view.page', 'Page {page} of {pages}', { page: activity.page, pages: activity.pages }) }}</span>
				<RouterLink v-if="activity.page < activity.pages" class="lnk" :to="{ query: { ...route.query, page: String(activity.page + 1) } }">{{ t('tools_view.older', 'Older') }}</RouterLink>
			</p>
		</section>
	</template>

	<section v-else class="panel" aria-labelledby="log-heading">
			<header class="panel__header">
				<h2 id="log-heading">{{ t('tools_view.log', 'Log') }}</h2>
				<p v-if="log?.file" class="panel__hint"><MessageText :text="t('tools_view.log_hint', 'The last {count, plural, one {# entry} other {# entries} } in {file}, newest first ({size})', { count: log.entries.length, size: formatSize(log.size) })"><template #file><span class="mono">{{ log.file }}</span></template></MessageText></p>
				<div class="panel__actions">
					<button type="button" class="button button--small" :disabled="logLoading" @click="loadLog"><AdminIcon name="refresh-cw" />{{ t('tools_view.refresh', 'Refresh') }}</button>
					<a v-if="log?.file && log.size" class="button button--small" :href="`${config.api}/logs/download`" download><AdminIcon name="download" />{{ t('tools_view.download', 'Download') }}</a>
				</div>
			</header>
			<p v-if="logError" class="panel__body notice notice--error" role="alert">{{ logError }}</p>
			<p v-else-if="!log" class="panel__body loading">{{ t('tools_view.reading_log', 'Reading the log…') }}</p>
			<p v-else-if="log.driver !== 'file'" class="panel__body muted">{{ log.driver === 'stderr' ? t('tools_view.log_stderr', 'The site logs to the server\'s error output, so there\'s no file to read here.') : t('tools_view.log_off', 'Logging is off, so there\'s nothing to read.') }}</p>
			<p v-else-if="!log.entries.length" class="panel__body muted">{{ t('tools_view.log_empty', 'Nothing has been logged.') }}</p>
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
.tools-list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.tool {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	align-items: start;
	gap: 0 16px;
	padding: var(--padding-row) var(--padding-inline);
}

.tool + .tool {
	border-top: 1px solid var(--border-color);
}

.tool__text {
	display: grid;
	gap: 2px;
	margin: 0;
}

.tool__name {
	font-weight: 500;
}

.tool__asks {
	color: var(--color-text-muted);
	font-size: var(--font-size-small);
	font-weight: 400;
}

.tool__description {
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.tool__result {
	grid-column: 1 / -1;
}

.tool__result:not(:empty) {
	margin-top: 10px;
}

.tool .spin,
.rows__side .spin {
	margin-right: 6px;
}

.tool__progress {
	display: grid;
	gap: 6px;
}

/* Newer and older pages of the activity. */
.tools__pages {
	display: flex;
	gap: var(--s-3);
	align-items: center;
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
	padding: var(--s-4) var(--padding-inline);
	overflow: auto;
	list-style: none;
	color: var(--color-text-secondary);
	font-family: var(--font-mono);
	font-size: var(--font-size-small);
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
	color: var(--color-text);
}

.tools__message {
	overflow: hidden;
	text-overflow: ellipsis;
}

/* An entry with a trace says so after its message, and opens to show it. */
summary.tools__line .tools__message::after {
	content: " …";
	color: var(--color-text-muted);
}

.tools__entry[open] .tools__message {
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}

.tools__details {
	margin: 2px 0 var(--s-2) 2ch;
	padding-left: var(--s-3);
	overflow-x: auto;
	border-left: 1px solid var(--border-color);
	color: var(--color-text-muted);
	font: inherit;
}

.tools__time,
.is-info {
	color: var(--color-text-muted);
}

.is-error {
	color: var(--color-danger);
}

.is-warn {
	color: var(--color-warning);
}
</style>
