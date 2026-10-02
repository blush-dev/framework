<script setup lang="ts">
/**
 * The dashboard: a greeting by the account's name (D-322, D-323), the
 * site at a glance, and the actions the account may run. Actions are described by the server (`AdminAction` classes,
 * D-222), so ones an extension adds in PHP show up here with no
 * JavaScript. Results are announced in a polite live region.
 *
 * Tiles and actions are skeletons until the server answers. A site with
 * no content at all gets a short setup path in place of the figures:
 * each step creates the first entry of a type.
 */

import { computed, onMounted, ref } from 'vue';
import { confirmAction } from '../confirm';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import { canAnyType, canType, session } from '../session';
import { ApiError, request, type ActionDescription, type ActionResult, type Dashboard } from '../api';
import { loadTypes, types } from '../types';

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
	if (action.confirm !== null && !await confirmAction({ title: `${action.label}?`, body: action.confirm, confirm: action.label })) {
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

// The setup path: pages first, then the other types entries are written
// in. Taxonomies' terms come from using them, so they aren't steps.
const steps = computed(() => [
	...types.value.filter((type) => type.kind === 'tree'),
	...types.value.filter((type) => type.kind === 'collection')
].filter((type) => canType(type.name, 'create')));

const empty = computed(() => dashboard.value?.content.total === 0);

// "Good afternoon, Jane Doe", by the browser's clock.
const greeting = computed(() => {
	const hour = new Date().getHours();
	const part = hour < 5 ? 'evening' : hour < 12 ? 'morning' : hour < 18 ? 'afternoon' : 'evening';

	return session.account ? `Good ${part}, ${session.account.displayName}` : 'Dashboard';
});

onMounted(() => {
	void load();

	if (canAnyType('create')) {
		loadTypes().catch(() => undefined);
	}
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ greeting }}</h1>
			<p v-if="dashboard" class="page-header__hint">{{ dashboard.site.name }} · <span class="mono">{{ dashboard.site.environment }}</span></p>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-else-if="!dashboard">
		<p class="visually-hidden" role="status">Loading the dashboard…</p>
		<div class="stats" aria-hidden="true">
			<div v-for="tile in 4" :key="tile" class="stat">
				<span class="skeleton" :style="{ width: `${40 + tile * 9}%` }" />
				<span class="skeleton skeleton--figure" />
				<span class="skeleton skeleton--small" :style="{ width: `${70 - tile * 6}%` }" />
			</div>
		</div>
		<section class="panel" aria-hidden="true">
			<header class="panel__header">
				<h2>Actions</h2>
			</header>
			<ul class="actions">
				<li v-for="row in 3" :key="row" class="action">
					<span class="skeleton" :style="{ width: `${50 + row * 11}%` }" />
					<span class="skeleton skeleton--button" />
				</li>
			</ul>
		</section>
	</template>

	<template v-else>
		<section v-if="empty" class="panel" aria-labelledby="setup-heading">
			<header class="panel__header">
				<h2 id="setup-heading">Get Started</h2>
				<p class="panel__hint">The site has no content yet</p>
			</header>
			<ol v-if="steps.length" class="setup">
				<li v-for="(type, index) in steps" :key="type.name" class="setup__step">
					<span class="setup__number" aria-hidden="true">{{ index + 1 }}</span>
					<span class="setup__text">
						<span class="setup__title">Write your first {{ type.labels.item }}</span>
						<span class="setup__hint">{{ type.kind !== 'tree' ? `${type.labels.plural} are listed together on the site.` : type.folder === '' ? 'A page that stands on its own, like About.' : `${type.labels.plural} nest by folder, each at its own address.` }}</span>
					</span>
					<RouterLink class="button" :class="{ 'button--primary': index === 0 }" :to="{ name: 'entry-new', query: { type: type.name } }">{{ type.labels.newItem }}</RouterLink>
				</li>
			</ol>
			<p v-else class="panel__body setup__none">Once someone writes the first entry, the site's content shows here.</p>
		</section>

		<section v-else aria-labelledby="content-heading">
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
					<dd class="stat__value">{{ count(dashboard.content.draft) }}</dd>
					<dd class="stat__context">{{ share(dashboard.content.draft, dashboard.content.total) }}</dd>
				</div>
				<div class="stat">
					<dt class="stat__label"><AdminIcon name="calendar-clock" />Scheduled</dt>
					<dd class="stat__value">{{ count(dashboard.content.scheduled) }}</dd>
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

.skeleton--button {
	width: 6rem;
	height: 30px;
}

.setup {
	margin: 0;
	padding: 0;
	list-style: none;
}

.setup__step {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr) auto;
	align-items: center;
	gap: 14px;
	padding: var(--pad-row) var(--pad-x);
}

.setup__step + .setup__step {
	border-top: 1px solid var(--border);
}

.setup__number {
	display: grid;
	place-items: center;
	width: 24px;
	height: 24px;
	border-radius: 50%;
	background: var(--accent-soft);
	color: var(--accent);
	font-family: var(--font-mono);
	font-size: var(--text-sm);
	font-variant-numeric: tabular-nums;
}

.setup__text {
	display: grid;
	gap: 2px;
}

.setup__title {
	font-weight: 500;
}

.setup__hint,
.setup__none {
	color: var(--fg-2);
	font-size: var(--text-sm);
}

@media (width <= 640px) {
	.setup__step {
		grid-template-columns: auto minmax(0, 1fr);
	}

	.setup__step .button {
		grid-column: 2;
		justify-self: start;
	}
}
</style>
