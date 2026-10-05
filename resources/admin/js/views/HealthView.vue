<script setup lang="ts">
/**
 * Content health: the problems `content:lint` finds in every file. It
 * checks when the screen opens and when asked again. Notices (undeclared
 * keys, 1.x names, virtual terms) are optional. Severity is written out,
 * never shown by color alone.
 *
 * Above them, the files missing an id and the ids files share (D-477),
 * fixed here (D-478): missing ids are added in one go, and for a shared
 * id, you choose the file that keeps it; the others get new ones. Media
 * files have ids too (D-487), fixed the same way in a panel of their own,
 * and images' sizes are recorded in their details from one more (D-488).
 */

import { computed, onMounted, ref } from 'vue';
import { ApiError, request, type AssignedIds, type Health, type HealthIds, type Violation } from '../api';
import AdminIcon from '../components/AdminIcon.vue';
import { plural } from '../format';
import { toast } from '../toast';

const severities: Record<Violation['severity'], string> = {
	error: 'pill--danger',
	warning: 'pill--warn',
	notice: ''
};

const health  = ref<Health | null>(null);
const strict  = ref(false);
const loading = ref(false);
const error   = ref('');
const fixing  = ref<string | null>(null);

interface IdGroup {
	key: string;
	heading: string;
	hint: string;
	ids: HealthIds;
	// Where missing ids are added, and where a shared one is kept.
	missing: string;
	keep: string;
}

// Entries' ids, then media files' (D-487), each only when something's wrong.
const idGroups = computed<IdGroup[]>(() => {
	if (health.value === null) {
		return [];
	}

	return [
		{ key: 'entry', heading: 'Entry IDs', hint: 'Every content file needs an id of its own', ids: health.value.ids, missing: '/health/ids', keep: '/health/ids/keep' },
		{ key: 'media', heading: 'Media IDs', hint: 'Every media file needs an id of its own; an image\'s sizes share its', ids: health.value.mediaIds, missing: '/health/media-ids', keep: '/health/media-ids/keep' }
	].filter((group) => group.ids.missing.length > 0 || group.ids.duplicates.length > 0);
});

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

/**
 * Runs an id fix, says what it did, and checks again.
 */
async function fix(key: string, path: string, body: Record<string, string> = {}): Promise<void> {
	fixing.value = key;

	try {
		const answer = await request<AssignedIds>('POST', path, body);
		const added  = Object.keys(answer.assigned).length;
		const failed = Object.entries(answer.failed);

		if (failed.length) {
			toast(`${plural(failed.length, 'file')} couldn't be changed: ${failed.map(([file, why]) => `${file} (${why})`).join('; ')}`, { kind: 'warn' });
		} else {
			toast(added ? `Gave ${plural(added, 'file')} a new id` : 'No files you may edit needed an id', { kind: added ? 'good' : 'info' });
		}

		await check();
	} catch (caught) {
		toast(caught instanceof ApiError ? caught.message : 'The ids couldn\'t be fixed.', { kind: 'danger' });
	} finally {
		fixing.value = null;
	}
}

const recording = ref(false);

/**
 * Records images' sizes in their details, says what it did, and checks
 * again.
 */
async function recordSizes(): Promise<void> {
	recording.value = true;

	try {
		const answer = await request<{ recorded: Record<string, string[]>; failed: Record<string, string> }>('POST', '/health/media-sizes');
		const images = Object.keys(answer.recorded).length;
		const failed = Object.entries(answer.failed);

		if (failed.length) {
			toast(`${plural(failed.length, 'image')} couldn't be changed: ${failed.map(([file, why]) => `${file} (${why})`).join('; ')}`, { kind: 'warn' });
		} else {
			toast(images ? `Recorded the sizes of ${plural(images, 'image')}` : 'No images you may edit needed their sizes recorded', { kind: images ? 'good' : 'info' });
		}

		await check();
	} catch (caught) {
		toast(caught instanceof ApiError ? caught.message : 'The sizes couldn\'t be recorded.', { kind: 'danger' });
	} finally {
		recording.value = false;
	}
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
		<section v-for="group in idGroups" :key="group.key" class="panel" :aria-labelledby="`${group.key}-ids-heading`">
			<header class="panel__header">
				<h2 :id="`${group.key}-ids-heading`">{{ group.heading }}</h2>
				<p class="panel__hint">{{ group.hint }}</p>
			</header>
			<div v-if="group.ids.missing.length" class="ids__row">
				<p>{{ plural(group.ids.missing.length, 'file') }} {{ group.ids.missing.length === 1 ? 'has' : 'have' }} no id, or one that isn't valid.</p>
				<button type="button" class="button button--primary button--small" :disabled="fixing !== null" @click="fix(`${group.key}:missing`, group.missing)">
					{{ fixing === `${group.key}:missing` ? 'Adding…' : 'Add Missing IDs' }}
				</button>
			</div>
			<div v-for="shared in group.ids.duplicates" :key="shared.id" class="ids__row ids__row--shared">
				<p>These files share the id <code>{{ shared.id }}</code>. Keep it on one; the others get new ids.</p>
				<ul class="ids__files">
					<li v-for="file in shared.paths" :key="file">
						<code>{{ file }}</code>
						<button type="button" class="button button--small" :disabled="fixing !== null" @click="fix(`${group.key}:keep:${file}`, group.keep, { path: file })">
							{{ fixing === `${group.key}:keep:${file}` ? 'Keeping…' : 'Keep Here' }}<span class="visually-hidden"> ({{ file }})</span>
						</button>
					</li>
				</ul>
			</div>
		</section>

		<section v-if="health.mediaSizes.images || health.mediaSizes.stale" class="panel" aria-labelledby="sizes-heading">
			<header class="panel__header">
				<h2 id="sizes-heading">Image Sizes</h2>
				<p class="panel__hint">An image's other sizes are listed in its details</p>
			</header>
			<div class="ids__row">
				<p>
					<template v-if="health.mediaSizes.sizes">{{ plural(health.mediaSizes.sizes, 'size') }} of {{ plural(health.mediaSizes.images, 'image') }} {{ health.mediaSizes.sizes === 1 ? 'isn\'t' : 'aren\'t' }} recorded yet.</template>
					<template v-if="health.mediaSizes.stale"> {{ plural(health.mediaSizes.stale, 'image') }} {{ health.mediaSizes.stale === 1 ? 'lists' : 'list' }} files that aren't {{ health.mediaSizes.stale === 1 ? 'its' : 'their' }} sizes.</template>
				</p>
				<button type="button" class="button button--primary button--small" :disabled="recording || fixing !== null" @click="recordSizes">
					{{ recording ? 'Recording…' : 'Record Sizes' }}
				</button>
			</div>
		</section>

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

.ids__row {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: var(--s-3);
	padding: var(--pad-row) var(--pad-x);
}

.ids__row p {
	margin: 0;
}

.ids__row + .ids__row {
	border-top: 1px solid var(--border);
}

.ids__row--shared {
	display: block;
}

.ids__files {
	margin: var(--s-2) 0 0;
	padding: 0;
	list-style: none;
}

.ids__files li {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: var(--s-3);
	padding: var(--s-1) 0;
}

.ids__files code,
.ids__row code {
	overflow-wrap: anywhere;
}

@media (width <= 640px) {
	.violation {
		grid-template-columns: minmax(0, 1fr);
		gap: 6px;
	}
}
</style>
