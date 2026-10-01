<script setup lang="ts">
/**
 * Content health: the problems `content:lint` finds in every file. It
 * checks when the screen opens and when asked again. Notices (undeclared
 * keys, 1.x names, virtual terms) are optional. Severity is written out,
 * never shown by color alone.
 */

import { onMounted, ref } from 'vue';
import { ApiError, request, type Health, type Violation } from '../api';
import AdminIcon from '../components/AdminIcon.vue';
import { plural } from '../format';

const severities: Record<Violation['severity'], string> = {
	error: 'pill--danger',
	warning: 'pill--warn',
	notice: ''
};

const health  = ref<Health | null>(null);
const strict  = ref(false);
const loading = ref(false);
const error   = ref('');

async function check(): Promise<void> {
	loading.value = true;
	error.value   = '';

	try {
		health.value = await request<Health>('GET', strict.value ? '/health?strict=1' : '/health');
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'Content health couldn\'t be checked.';
	} finally {
		loading.value = false;
	}
}

function summary(result: Health): string {
	const counts = [plural(result.counts.error, 'error'), plural(result.counts.warning, 'warning')];

	if (result.counts.notice !== null) {
		counts.push(plural(result.counts.notice, 'notice'));
	}

	const metadata = result.metadata > 0 ? ` and ${plural(result.metadata, 'media metadata file')}` : '';

	return `Checked ${plural(result.checked, 'file')}${metadata}: ${counts.join(', ')}.`;
}

onMounted(check);
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Content Health</h1>
			<p class="page-header__hint">Problems in content files, as <code>content:lint</code> finds them</p>
		</div>
		<div class="page-header__actions">
			<button type="button" class="button" :disabled="loading" @click="check">
				<AdminIcon name="refresh-cw" />
				{{ loading ? 'Checking…' : 'Check again' }}
			</button>
		</div>
	</header>

	<div class="toolbar">
		<label class="checkbox">
			<input v-model="strict" type="checkbox" :disabled="loading" @change="check">
			Include notices
		</label>
	</div>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div aria-live="polite">
		<p v-if="loading && !health" class="loading">Checking every file…</p>
		<p v-else-if="health" class="notice" :class="health.counts.error ? 'notice--error' : 'notice--success'">{{ summary(health) }}</p>
	</div>

	<template v-if="health">
		<div v-if="!health.files.length" class="panel">
			<div class="empty">
				<AdminIcon name="circle-check" />
				<p class="empty__heading">No Problems Found</p>
				<p class="empty__text">Every file passed{{ health.strict ? ', notices included' : '' }}.</p>
			</div>
		</div>

		<section v-for="file in health.files" :key="file.path" class="panel">
			<header class="panel__header">
				<h2 class="mono">{{ file.path }}</h2>
				<p class="panel__hint">{{ plural(file.violations.length, 'problem') }}</p>
			</header>
			<ul class="violations">
				<li v-for="(violation, index) in file.violations" :key="index" class="violation">
					<span class="pill" :class="severities[violation.severity]">{{ violation.severity }}</span>
					<p><code>{{ violation.field }}</code>: {{ violation.message }}</p>
				</li>
			</ul>
		</section>
	</template>
</template>

<style scoped>
h2.mono {
	font-family: var(--font-mono);
	font-size: var(--text-sm);
	font-weight: 500;
	overflow-wrap: anywhere;
}

.violations {
	margin: 0;
	padding: 0;
	list-style: none;
}

.violation {
	display: grid;
	grid-template-columns: 5.5rem minmax(0, 1fr);
	align-items: baseline;
	gap: 12px;
	padding: var(--pad-row) var(--pad-x);
}

.violation + .violation {
	border-top: 1px solid var(--border);
}

.violation .pill {
	justify-self: start;
	text-transform: capitalize;
}

.violation code {
	overflow-wrap: anywhere;
}

@media (width <= 640px) {
	.violation {
		grid-template-columns: minmax(0, 1fr);
		gap: 6px;
	}
}
</style>
