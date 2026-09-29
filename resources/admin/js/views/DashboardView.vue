<script setup lang="ts">
/**
 * The dashboard: the site at a glance and the actions the account may
 * run. Actions are described by the server (`AdminAction` classes,
 * D-222), so ones an extension adds in PHP show up here with no
 * JavaScript. Results are announced in a polite live region.
 */

import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
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

onMounted(load);
</script>

<template>
	<h1 tabindex="-1">Dashboard</h1>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else-if="!dashboard" aria-live="polite">Loading…</p>

	<template v-else>
		<section class="panel" aria-labelledby="site-heading">
			<h2 id="site-heading">Site</h2>
			<dl class="stats">
				<div><dt>Entries</dt><dd>{{ dashboard.content.total }}</dd></div>
				<div><dt>Published</dt><dd>{{ dashboard.content.published }}</dd></div>
				<div>
					<dt>Drafts</dt>
					<dd>
						<RouterLink v-if="can('content.edit')" :to="{ name: 'drafts' }">{{ dashboard.content.draft }}</RouterLink>
						<template v-else>{{ dashboard.content.draft }}</template>
					</dd>
				</div>
				<div>
					<dt>Scheduled</dt>
					<dd>
						<RouterLink v-if="can('content.edit')" :to="{ name: 'drafts', hash: '#scheduled' }">{{ dashboard.content.scheduled }}</RouterLink>
						<template v-else>{{ dashboard.content.scheduled }}</template>
					</dd>
				</div>
				<div><dt>Environment</dt><dd>{{ dashboard.site.environment }}</dd></div>
			</dl>
		</section>

		<section v-if="dashboard.actions.length" class="panel" aria-labelledby="actions-heading">
			<h2 id="actions-heading">Actions</h2>
			<ul class="actions">
				<li v-for="action in dashboard.actions" :key="action.name" class="action">
					<div>
						<button type="button" class="button" :disabled="running !== null" :aria-describedby="`action-${action.name}`" @click="run(action)">
							{{ running === action.name ? `${action.label}…` : action.label }}
						</button>
						<p :id="`action-${action.name}`" class="action__description">{{ action.description }}</p>
					</div>
					<div aria-live="polite">
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
