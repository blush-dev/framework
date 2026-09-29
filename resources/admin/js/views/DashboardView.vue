<script setup lang="ts">
/**
 * The dashboard: the site at a glance and the actions the account may
 * run. Actions are described by the server (`AdminAction` classes,
 * D-222), so ones an extension adds in PHP show up here with no
 * JavaScript. Results are announced in a polite live region.
 */

import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import { can } from '../session';
import { ApiError, request, type ActionDescription, type ActionResult, type Dashboard } from '../api';

const dashboard = ref<Dashboard | null>(null);
const error     = ref('');
const running   = ref<string | null>(null);
const results   = ref<Record<string, ActionResult>>({});

async function load(): Promise<void> {
	try {
		dashboard.value = await request<Dashboard>('GET', '/dashboard');
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'The dashboard couldn\'t be loaded.';
	}
}

async function run(action: ActionDescription): Promise<void> {
	if (action.confirm !== null && !window.confirm(action.confirm)) {
		return;
	}

	running.value = action.name;

	try {
		results.value[action.name] = await request<ActionResult>('POST', `/actions/${encodeURIComponent(action.name)}`);
		await load();
	} catch (caught) {
		results.value[action.name] = {
			successful: false,
			message: caught instanceof ApiError ? caught.message : `${action.label} failed.`,
			details: []
		};
	} finally {
		running.value = null;
	}
}

/**
 * A count with thousands separators, in the browser's language.
 */
function count(value: number): string {
	return value.toLocaleString();
}

/**
 * What share of the entries a count is.
 */
function share(value: number, total: number): string {
	return total === 0 ? 'No entries yet' : `${Math.round(value / total * 100)}% of entries`;
}

onMounted(load);
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Dashboard</h1>
			<p v-if="dashboard" class="page-header__hint">{{ dashboard.site.name }} · <span class="mono">{{ dashboard.site.environment }}</span></p>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else-if="!dashboard" class="loading" aria-live="polite">Loading…</p>

	<template v-else>
		<section aria-labelledby="content-heading">
			<h2 id="content-heading" class="visually-hidden">Content</h2>
			<dl class="stats">
				<div class="stat">
					<dt class="stat__label"><AdminIcon name="files" />Entries</dt>
					<dd class="stat__value">{{ count(dashboard.content.total) }}</dd>
					<dd class="stat__context">Every type and status</dd>
				</div>
				<div class="stat">
					<dt class="stat__label"><AdminIcon name="circle-check" />Published</dt>
					<dd class="stat__value">{{ count(dashboard.content.published) }}</dd>
					<dd class="stat__context">{{ share(dashboard.content.published, dashboard.content.total) }}</dd>
				</div>
				<div class="stat">
					<dt class="stat__label"><AdminIcon name="file-pen-line" />Drafts</dt>
					<dd class="stat__value">
						<RouterLink v-if="can('content.edit')" :to="{ name: 'entries', query: { status: 'draft' } }">{{ count(dashboard.content.draft) }}</RouterLink>
						<template v-else>{{ count(dashboard.content.draft) }}</template>
					</dd>
					<dd class="stat__context">{{ share(dashboard.content.draft, dashboard.content.total) }}</dd>
				</div>
				<div class="stat">
					<dt class="stat__label"><AdminIcon name="calendar-clock" />Scheduled</dt>
					<dd class="stat__value">
						<RouterLink v-if="can('content.edit')" :to="{ name: 'entries', query: { status: 'scheduled' } }">{{ count(dashboard.content.scheduled) }}</RouterLink>
						<template v-else>{{ count(dashboard.content.scheduled) }}</template>
					</dd>
					<dd class="stat__context">Go live on their own</dd>
				</div>
			</dl>
		</section>

		<section v-if="dashboard.actions.length" class="panel" aria-labelledby="actions-heading">
			<header class="panel__header">
				<h2 id="actions-heading">Actions</h2>
				<p class="panel__hint">Run site tasks now</p>
			</header>
			<ul class="actions">
				<li v-for="action in dashboard.actions" :key="action.name" class="action">
					<p :id="`action-${action.name}`" class="action__description">{{ action.description }}</p>
					<button type="button" class="button" :disabled="running !== null" :aria-describedby="`action-${action.name}`" @click="run(action)">
						{{ running === action.name ? `${action.label}…` : action.label }}
					</button>
					<div class="action__result" aria-live="polite">
						<div v-if="results[action.name]" class="notice" :class="results[action.name]?.successful ? 'notice--success' : 'notice--error'">
							<p>{{ results[action.name]?.message }}</p>
							<ul v-if="results[action.name]?.details.length">
								<li v-for="detail in results[action.name]?.details" :key="detail">{{ detail }}</li>
							</ul>
						</div>
					</div>
				</li>
			</ul>
		</section>
	</template>
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
	align-items: center;
	gap: 0 16px;
	padding: var(--pad-row) var(--pad-x);
}

.action + .action {
	border-top: 1px solid var(--border);
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

.stats dd {
	margin: 0;
}
</style>
