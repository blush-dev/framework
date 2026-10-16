<script setup lang="ts">
/**
 * Logs (D-541; a Tools screen, D-900), with `site.logs`: the end of the
 * site's log, read only, for a host with no shell.
 */

import { onMounted, ref } from 'vue';
import AdminIcon from '../components/AdminIcon.vue';
import MessageText from '../components/MessageText.vue';
import { config } from '../config';
import { formatSize } from '../format';
import { t } from '../messages';
import { errorMessage, request, type LogEntry, type LogTail } from '../api';

const log     = ref<LogTail | null>(null);
const error   = ref('');
const loading = ref(false);

async function load(): Promise<void> {
	loading.value = true;
	error.value   = '';

	try {
		log.value = await request<LogTail>('GET', '/logs');
	} catch (caught) {
		error.value = errorMessage(caught, t('logs_view.errors.log', 'The log couldn\'t be read.'));
	} finally {
		loading.value = false;
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

onMounted(() => {
	void load();
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ t('logs_view.title', 'Logs') }}</h1>
			<p class="page-header__hint">{{ t('logs_view.hint', 'What the site wrote to its log') }}</p>
		</div>
	</header>


	<section class="panel" aria-labelledby="log-heading">
		<header class="panel__header">
			<h2 id="log-heading">{{ t('logs_view.log', 'Log') }}</h2>
			<p v-if="log?.file" class="panel__hint"><MessageText :text="t('logs_view.log_hint', 'The last {count, plural, one {# entry} other {# entries} } in {file}, newest first ({size})', { count: log.entries.length, size: formatSize(log.size) })"><template #file><span class="mono">{{ log.file }}</span></template></MessageText></p>
			<div class="panel__actions">
				<button type="button" class="button button--small" :disabled="loading" @click="load"><AdminIcon name="refresh-cw" />{{ t('logs_view.refresh', 'Refresh') }}</button>
				<a v-if="log?.file && log.size" class="button button--small" :href="`${config.api}/logs/download`" download><AdminIcon name="download" />{{ t('logs_view.download', 'Download') }}</a>
			</div>
		</header>
		<p v-if="error" class="panel__body notice notice--error" role="alert">{{ error }}</p>
		<p v-else-if="!log" class="panel__body loading">{{ t('logs_view.reading_log', 'Reading the log…') }}</p>
		<p v-else-if="log.driver !== 'file'" class="panel__body muted">{{ log.driver === 'stderr' ? t('logs_view.log_stderr', 'The site logs to the server\'s error output, so there\'s no file to read here.') : t('logs_view.log_off', 'Logging is off, so there\'s nothing to read.') }}</p>
		<p v-else-if="!log.entries.length" class="panel__body muted">{{ t('logs_view.log_empty', 'Nothing has been logged.') }}</p>
		<ol v-else class="logs">
			<li v-for="(entry, index) in log.entries" :key="index">
				<details v-if="entry.details" class="logs__entry">
					<summary class="logs__line"><span class="logs__time">{{ stamp(entry) }}</span> <span :class="tone(entry)">{{ levelName(entry) }}</span> <span class="logs__message">{{ entry.message }}</span></summary>
					<pre class="logs__details">{{ entry.details }}</pre>
				</details>
				<p v-else class="logs__line"><span class="logs__time">{{ stamp(entry) }}</span> <span :class="tone(entry)">{{ levelName(entry) }}</span> <span class="logs__message">{{ entry.message }}</span></p>
			</li>
		</ol>
	</section>
</template>

<style scoped>
.logs {
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

.logs__line {
	display: flex;
	gap: 1ch;
	margin: 0;
	white-space: pre;
}

summary.logs__line {
	cursor: pointer;
	list-style: none;
}

summary.logs__line::-webkit-details-marker {
	display: none;
}

summary.logs__line:hover .logs__message {
	color: var(--color-text);
}

.logs__message {
	overflow: hidden;
	text-overflow: ellipsis;
}

/* An entry with a trace says so after its message, and opens to show it. */
summary.logs__line .logs__message::after {
	content: " …";
	color: var(--color-text-muted);
}

.logs__entry[open] .logs__message {
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}

.logs__details {
	margin: 2px 0 var(--s-2) 2ch;
	padding-left: var(--s-3);
	overflow-x: auto;
	border-left: 1px solid var(--border-color);
	color: var(--color-text-muted);
	font: inherit;
}

.logs__time,
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
