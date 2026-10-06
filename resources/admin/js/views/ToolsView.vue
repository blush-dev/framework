<script setup lang="ts">
/**
 * Tools (D-540, from the Home sketch): tasks run by hand, and the log
 * they write to. **Actions** turn command-line tasks into buttons for
 * anyone without shell access; they're described by the server
 * (`AdminAction` classes, D-222), so a plugin's show up here with no
 * JavaScript, grouped by where they come from. An action the account
 * may not run isn't listed. A result stays in its row, since on a host
 * with no shell it's all there is, and is announced in a polite live
 * region. **Logs** shows the end of the site's log, read only, with
 * `site.logs` (D-541).
 */

import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import { config } from '../config';
import { confirmAction } from '../confirm';
import { formatSize, plural, titleCase } from '../format';
import { can } from '../session';
import { errorMessage, request, type ActionDescription, type ActionGroup, type ActionResult, type LogEntry, type LogTail } from '../api';

type Tab = 'actions' | 'logs';

const groups  = ref<ActionGroup[] | null>(null);
const error   = ref('');
const running = ref<string | null>(null);
const results = ref<Record<string, ActionResult>>({});

const log        = ref<LogTail | null>(null);
const logError   = ref('');
const logLoading = ref(false);

const tabs = computed(() => [
	{ key: 'actions' as const, label: 'Actions' },
	...(can('site.logs') ? [{ key: 'logs' as const, label: 'Logs' }] : [])
]);
// The tab is in the address (`?tab=logs`), as the entries list's are.
const route = useRoute();
const tab   = computed<Tab>(() => route.query.tab === 'logs' && can('site.logs') ? 'logs' : 'actions');

const NOTES: Record<Tab, string> = {
	actions: 'An action turns a command-line task into a button, for anyone without shell access. Blush registers some, and a plugin can register its own. An action you don\'t have the capability for isn\'t listed.',
	logs: 'Read only. This is for the moment something says it failed on a host with no way in over SSH.'
};

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
		results.value[action.name] = await request<ActionResult>('POST', `/actions/${encodeURIComponent(action.name)}`);
	} catch (caught) {
		results.value[action.name] = {
			successful: false,
			message: errorMessage(caught, `${action.label} failed.`),
			details: []
		};
	} finally {
		running.value = null;
	}
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

// Logs load when first shown.
watch(tab, (value) => {
	if (value === 'logs' && log.value === null && !logLoading.value) {
		void loadLog();
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
			<p class="page-header__hint">Tasks you run by hand{{ can('site.logs') ? ', and the log they write to' : '' }}</p>
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
						<div v-if="results[action.name]" class="notice notice--small" :class="results[action.name]?.successful ? 'notice--success' : 'notice--error'">
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

.action .spin {
	margin-right: 6px;
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
