<script setup lang="ts">
/**
 * Content health: the problems `content:lint` finds in every file. It
 * checks when the screen opens and when asked again. Notices (undeclared
 * keys, 1.x names, virtual terms) are optional. Severity is written out,
 * never shown by color alone.
 */

import { onMounted, ref } from 'vue';
import { ApiError, request, type Health } from '../api';
import { plural } from '../format';

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

	return `Checked ${plural(result.checked, 'file')}: ${counts.join(', ')}.`;
}

onMounted(check);
</script>

<template>
	<h1 tabindex="-1">Content health</h1>

	<div class="toolbar">
		<label class="checkbox">
			<input v-model="strict" type="checkbox" :disabled="loading" @change="check">
			Include notices
		</label>
		<button type="button" class="button" :disabled="loading" @click="check">{{ loading ? 'Checking…' : 'Check again' }}</button>
	</div>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div aria-live="polite">
		<p v-if="loading && !health">Checking every file…</p>
		<p v-else-if="health" class="notice" :class="health.counts.error ? 'notice--error' : 'notice--success'">{{ summary(health) }}</p>
	</div>

	<template v-if="health">
		<p v-if="!health.files.length">No problems found.</p>

		<section v-for="file in health.files" :key="file.path" class="panel">
			<h2><code>{{ file.path }}</code></h2>
			<ul class="violations">
				<li v-for="(violation, index) in file.violations" :key="index" class="violation" :class="`violation--${violation.severity}`">
					<span class="violation__severity">{{ violation.severity }}</span>
					<code>{{ violation.field }}</code>: {{ violation.message }}
				</li>
			</ul>
		</section>
	</template>
</template>
