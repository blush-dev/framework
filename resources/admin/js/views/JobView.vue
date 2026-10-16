<script setup lang="ts">
/**
 * One background job (D-904), under Tools → Jobs: its status, and while
 * it waits or runs, its progress, asked again every few seconds; why it
 * failed, whole, where the list has a line; what it said (its message
 * and the lines under it) and handed back (`result`); and when it was
 * queued, started, and finished, its attempts, and who queued it. Retry
 * (a failed job) and Delete (one that isn't running) are in the header.
 */

import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import { confirmAction } from '../confirm';
import { siteDateTime } from '../dates';
import { formatWhenInline } from '../format';
import { JOB_PILLS } from '../jobs';
import { t } from '../messages';
import { screenTitle } from '../screen';
import { can, session } from '../session';
import { toast } from '../toast';
import { errorMessage, request, type Job } from '../api';

// How often, in milliseconds, a job that waits or runs is asked again.
const POLL = 3000;

const route  = useRoute();
const router = useRouter();
const id     = computed(() => String(route.params.id ?? ''));

const job   = ref<Job | null>(null);
const error = ref('');
const busy  = ref(false);

let timer: number | undefined;

// What it handed back, when it handed back anything.
const result = computed(() => job.value !== null && Object.keys(job.value.result).length ? JSON.stringify(job.value.result, null, 2) : '');

async function load(): Promise<void> {
	window.clearTimeout(timer);

	try {
		job.value   = (await request<{ job: Job }>('GET', `/jobs/${encodeURIComponent(id.value)}`)).job;
		error.value = '';
	} catch (caught) {
		error.value = errorMessage(caught, t('job_view.errors.load', 'The job couldn\'t be loaded.'));

		return;
	}

	if (job.value.status === 'queued' || job.value.status === 'running') {
		timer = window.setTimeout(() => void load(), POLL);
	}
}

async function retry(): Promise<void> {
	busy.value = true;

	try {
		await request('POST', `/jobs/${encodeURIComponent(id.value)}/retry`);
		toast(t('job_view.queued_again', 'Queued it again'));
	} catch (caught) {
		toast(errorMessage(caught, t('job_view.errors.retry', 'The job couldn\'t be queued again.')), { kind: 'warn' });
	} finally {
		busy.value = false;
		await load();
	}
}

async function remove(): Promise<void> {
	const current = job.value;

	if (current === null || !await confirmAction({ title: t('job_view.delete_title', 'Delete This Job?'), body: current.status === 'queued' ? t('job_view.delete_body_queued', '"{job}" is removed from the list and won\'t run.', { job: current.label }) : t('job_view.delete_body', '"{job}" is removed from the list.', { job: current.label }), confirm: t('common.delete', 'Delete'), danger: true })) {
		return;
	}

	busy.value = true;

	try {
		await request('DELETE', `/jobs/${encodeURIComponent(current.id)}`);
		toast(t('job_view.deleted', 'Deleted “{job}”', { job: current.label }));
		await router.push({ name: 'jobs' });
	} catch (caught) {
		toast(errorMessage(caught, t('job_view.errors.delete', 'The job couldn\'t be deleted.')), { kind: 'warn' });
		await load();
	} finally {
		busy.value = false;
	}
}

watch(id, () => {
	job.value = null;
	void load();
}, { immediate: true });

watch(job, (value) => {
	screenTitle.value = value?.label ?? null;
});

onBeforeUnmount(() => {
	window.clearTimeout(timer);
});
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'jobs' }"><AdminIcon name="chevron-left" />{{ t('job_view.jobs', 'Jobs') }}</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ job?.label ?? t('job_view.title', 'Job') }}</h1>
			<p v-if="job" class="page-header__hint"><span class="pill" :class="JOB_PILLS[job.status].kind">{{ JOB_PILLS[job.status].label }}</span> <span class="mono">{{ job.job }}</span></p>
		</div>
		<div v-if="job" class="page-header__actions">
			<button v-if="job.status !== 'running'" type="button" class="button button--danger" :disabled="busy" @click="remove"><AdminIcon name="trash-2" />{{ t('common.delete', 'Delete') }}</button>
			<button v-if="job.status === 'failed'" type="button" class="button button--primary" :disabled="busy" @click="retry"><AdminIcon name="refresh-cw" />{{ t('job_view.retry', 'Retry') }}</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else-if="!job" class="loading">{{ t('job_view.loading', 'Loading the job…') }}</p>

	<div v-else class="columns">
		<div class="columns__col">
			<section v-if="job.status === 'queued' || job.status === 'running'" class="panel" aria-labelledby="job-progress-heading" aria-live="polite">
				<header class="panel__header">
					<h2 id="job-progress-heading">{{ t('job_view.progress', 'Progress') }}</h2>
				</header>
				<div class="panel__body job__progress">
					<progress class="progress" max="100" :value="job.progress ?? undefined" :aria-label="t('job_view.how_far', '{name}: how far along', { name: job.label })" />
					<p class="muted">{{ job.message || (job.status === 'queued' ? (job.due ? t('job_view.waiting', 'Waiting its turn…') : t('job_view.tries_again', 'Tries again {when}.', { when: formatWhenInline(job.available) })) : t('job_view.working', 'Working…')) }}</p>
				</div>
			</section>

			<section v-if="job.error" class="panel" aria-labelledby="job-error-heading">
				<header class="panel__header">
					<h2 id="job-error-heading">{{ job.status === 'failed' ? t('job_view.why_failed', 'Why It Failed') : t('job_view.last_error', 'Its Last Error') }}</h2>
					<p v-if="job.status !== 'failed'" class="panel__hint">{{ t('job_view.last_error_hint', 'From an attempt before this one') }}</p>
				</header>
				<pre class="panel__body job__text">{{ job.error }}</pre>
			</section>

			<section class="panel" aria-labelledby="job-said-heading">
				<header class="panel__header">
					<h2 id="job-said-heading">{{ t('job_view.what_it_said', 'What It Said') }}</h2>
				</header>
				<div v-if="job.message || job.details.length" class="panel__body job__said">
					<p v-if="job.message">{{ job.message }}</p>
					<ul v-if="job.details.length" class="job__details">
						<li v-for="(detail, index) in job.details" :key="index">{{ detail }}</li>
					</ul>
				</div>
				<p v-else class="panel__body muted">{{ t('job_view.said_nothing', 'Nothing yet.') }}</p>
			</section>

			<section v-if="result" class="panel" aria-labelledby="job-result-heading">
				<header class="panel__header">
					<h2 id="job-result-heading">{{ t('job_view.result', 'What It Handed Back') }}</h2>
					<p class="panel__hint">{{ t('job_view.result_hint', 'As the job returned it') }}</p>
				</header>
				<pre class="panel__body job__text">{{ result }}</pre>
			</section>
		</div>

		<section class="panel" aria-labelledby="job-facts-heading">
			<header class="panel__header">
				<h2 id="job-facts-heading">{{ t('job_view.details', 'Details') }}</h2>
			</header>
			<dl class="fact-rows fact-rows--panel">
				<div>
					<dt>{{ t('job_view.status', 'Status') }}</dt>
					<dd>{{ JOB_PILLS[job.status].label }}</dd>
				</div>
				<div>
					<dt>{{ t('job_view.queued_by', 'Queued by') }}</dt>
					<dd>
						<RouterLink v-if="job.by?.username && (can('accounts.view') || job.by.username === session.account?.username)" class="lnk" :to="{ name: 'account', params: { username: job.by.username } }">{{ job.by.name }}</RouterLink>
						<template v-else>{{ job.by?.name ?? t('job_view.the_schedule', 'The schedule') }}</template>
					</dd>
				</div>
				<div>
					<dt>{{ t('job_view.queued', 'Queued') }}</dt>
					<dd><time :datetime="job.queued">{{ siteDateTime(job.queued) }}</time></dd>
				</div>
				<div v-if="job.started">
					<dt>{{ t('job_view.started', 'Started') }}</dt>
					<dd><time :datetime="job.started">{{ siteDateTime(job.started) }}</time></dd>
				</div>
				<div v-if="job.finished">
					<dt>{{ t('job_view.finished', 'Finished') }}</dt>
					<dd><time :datetime="job.finished">{{ siteDateTime(job.finished) }}</time></dd>
				</div>
				<div>
					<dt>{{ t('job_view.attempts', 'Failed attempts') }}</dt>
					<dd>{{ job.attempts }}</dd>
				</div>
				<div v-if="job.status === 'queued' && !job.due">
					<dt>{{ t('job_view.next_try', 'Next try') }}</dt>
					<dd><time :datetime="job.available">{{ siteDateTime(job.available) }}</time></dd>
				</div>
				<div>
					<dt>{{ t('job_view.id', 'Id') }}</dt>
					<dd class="mono">{{ job.id }}</dd>
				</div>
			</dl>
		</section>
	</div>
</template>

<style scoped>
.job__progress {
	display: grid;
	gap: var(--s-2);
}

/* An error or a result, whole, as it was written. */
.job__text {
	max-height: 60vh;
	margin: 0;
	overflow: auto;
	font-family: var(--font-mono);
	font-size: var(--font-size-small);
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}

.job__said {
	display: grid;
	gap: var(--s-2);
}

.job__details {
	margin: 0;
	padding-left: var(--s-4);
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}
</style>
