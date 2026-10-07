<script setup lang="ts">
/**
 * One of Site Health's issues, and its fix (D-546; once Content Health,
 * D-543): content files' problems as `content:lint` finds them, entry
 * ids, collection folders, or file names; or media files' details, media
 * ids, or image sizes. It shows Site Health's last check (`GET health`),
 * says when it ran, and checks again when asked or after a fix (`POST
 * health`), which updates Site Health too.
 *
 * Files' problems: notices (undeclared keys, 1.x names) are optional,
 * and severity is written out, never shown by color alone.
 * Ids (D-477, D-478, D-487): missing ids are added in one go, and for a
 * shared id, you choose the file that keeps it; the others get new ones.
 * Image sizes are recorded in their details (D-488), entries named by
 * another pattern than their type's are renamed to it, a type at a time
 * (D-512), collections' entries kept in folders are moved into their
 * collections' folders (D-514), terms and profiles entries name with
 * no file are written (D-584), links between entries are filed with
 * their ids (D-596), and data types still written as taxonomies
 * are migrated to collections and relations (D-591, D-593).
 */

import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { errorMessage, request, type AssignedIds, type Health, type HealthIds, type Violation } from '../api';
import { useAction } from '../action';
import { loadCounts } from '../counts';
import AdminIcon from '../components/AdminIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import { formatWhen, plural } from '../format';
import { screenTitle } from '../screen';
import { toast } from '../toast';
import { refreshTypes } from '../types';

const props = defineProps<{
	area: 'content' | 'media';
	check: 'files' | 'ids' | 'terms' | 'refs' | 'taxonomies' | 'folders' | 'names' | 'sizes';
}>();

const severities: Record<Violation['severity'], string> = {
	error: 'pill--danger',
	warning: 'pill--warn',
	notice: ''
};

// Each issue's title and what it's about.
// Each issue's title, what it's about, and what all clear means.
const SCREENS: Record<string, { title: string; hint: string; clear: string }> = {
	'content:files': { title: 'Content Files', hint: 'Problems in entries\' files, as content:lint finds them', clear: '' },
	'content:ids': { title: 'Entry IDs', hint: 'Every content file needs an id of its own', clear: 'Every content file has an id of its own.' },
	'content:terms': { title: 'Terms and Profiles', hint: 'A term or profile entries name is left out of the site until it has a file', clear: 'Every term and profile entries name has a file.' },
	'content:refs': { title: 'Links Between Entries', hint: 'A link filed with its id follows what it links to through a rename or move', clear: 'Every link between entries is filed with its id.' },
	'content:taxonomies': { title: 'Taxonomies', hint: 'Read as collections and their relationships until they\'re migrated', clear: 'Every content type is written as a collection or a tree.' },
	'content:folders': { title: 'Collection Folders', hint: 'A collection\'s entries are files in its folder', clear: 'Every collection\'s entries are files in its folder.' },
	'content:names': { title: 'File Names', hint: 'Older names keep working; renaming them changes no address', clear: 'Every entry is named by its type\'s pattern.' },
	'media:files': { title: 'Media Details', hint: 'Problems in media files\' details, as content:lint finds them', clear: '' },
	'media:ids': { title: 'Media IDs', hint: 'Every media file needs an id of its own; an image\'s sizes share its', clear: 'Every media file has an id of its own.' },
	'media:sizes': { title: 'Image Sizes', hint: 'An image\'s other sizes are listed in its details', clear: 'Every image lists its sizes.' }
};

const screen = computed(() => SCREENS[`${props.area}:${props.check}`] ?? { title: 'Site Health', hint: '', clear: '' });

watch(screen, (value) => {
	screenTitle.value = value.title;
}, { immediate: true });

const health  = ref<Health | null>(null);
const notices = ref(false);
const fixing  = ref<string | null>(null);

const { busy: loading, error, run } = useAction();

// The area's files with problems, notices only when asked.
const files = computed(() => (health.value?.files ?? [])
	.filter((file) => file.area === props.area)
	.map((file) => ({ ...file, violations: file.violations.filter((violation) => notices.value || violation.severity !== 'notice') }))
	.filter((file) => file.violations.length > 0));

// The area's ids: entries', or media files' (D-487).
const ids = computed<{ ids: HealthIds; missing: string; keep: string } | null>(() => {
	if (health.value === null) {
		return null;
	}

	return props.area === 'content'
		? { ids: health.value.ids, missing: '/health/ids', keep: '/health/ids/keep' }
		: { ids: health.value.mediaIds, missing: '/health/media-ids', keep: '/health/media-ids/keep' };
});

// Whether the issue has anything to fix.
const found = computed(() => {
	const value = health.value;

	if (value === null) {
		return false;
	}

	switch (props.check) {
		case 'files':
			return files.value.length > 0;
		case 'ids':
			return (ids.value?.ids.missing.length ?? 0) + (ids.value?.ids.duplicates.length ?? 0) > 0;
		case 'terms':
			return value.terms.count > 0;
		case 'refs':
			return value.refs.count > 0;
		case 'taxonomies':
			return value.taxonomies.length > 0;
		case 'folders':
			return value.flat.count > 0;
		case 'names':
			return value.fileNames.length > 0;
		default:
			return value.mediaSizes.images + value.mediaSizes.stale > 0;
	}
});

/**
 * Loads the last check, or checks again (`again`), which Site Health and
 * its count follow.
 */
async function load(again = false): Promise<void> {
	await run('The files couldn\'t be checked.', async () => {
		health.value = await request<Health>(again ? 'POST' : 'GET', '/health');

		if (again) {
			void loadCounts();
		}
	});
}

// How many of the area's problems are of a severity.
function counted(severity: Violation['severity']): number {
	return files.value.reduce((total, file) => total + file.violations.filter((violation) => violation.severity === severity).length, 0);
}

function summary(result: Health): string {
	const counts = [plural(counted('error'), 'error'), plural(counted('warning'), 'warning')];

	if (notices.value) {
		counts.push(plural(counted('notice'), 'notice'));
	}

	return props.area === 'content'
		? `${plural(result.checked, 'file')} checked: ${counts.join(', ')}.`
		: `${plural(result.metadata, 'details file')} checked: ${counts.join(', ')}.`;
}

interface FixAnswer {
	failed: Record<string, string>;
}

/**
 * Runs a fix (`key` marks its button busy), says what it did, or which
 * files (`noun`, singular and plural) it couldn't change and why, and checks again. `done`
 * says what changed, or what to say when nothing did.
 */
async function runFix<T extends FixAnswer>(key: string, path: string, body: Record<string, string>, noun: [string, string], failure: string, done: (answer: T) => { changed: number; text: string }): Promise<void> {
	fixing.value = key;

	try {
		const answer = await request<T>('POST', path, body);
		const failed = Object.entries(answer.failed);

		if (failed.length) {
			toast(`${plural(failed.length, ...noun)} couldn't be changed: ${failed.map(([file, why]) => `${file} (${why})`).join('; ')}`, { kind: 'warn' });
		} else {
			const { changed, text } = done(answer);

			toast(text, { kind: changed ? 'good' : 'info' });
		}

		await load(true);
	} catch (caught) {
		toast(errorMessage(caught, failure), { kind: 'danger' });
	} finally {
		fixing.value = null;
	}
}

/**
 * Runs an id fix.
 */
function fix(key: string, path: string, body: Record<string, string> = {}): Promise<void> {
	return runFix<AssignedIds>(key, path, body, ['file', 'files'], 'The ids couldn\'t be fixed.', (answer) => {
		const added = Object.keys(answer.assigned).length;

		return { changed: added, text: added ? `Gave ${plural(added, 'file')} a new id` : 'No files you may edit needed an id' };
	});
}

/**
 * Records images' sizes in their details.
 */
function recordSizes(): Promise<void> {
	return runFix<FixAnswer & { recorded: Record<string, string[]> }>('sizes', '/health/media-sizes', {}, ['image', 'images'], 'The sizes couldn\'t be recorded.', (answer) => {
		const images = Object.keys(answer.recorded).length;

		return { changed: images, text: images ? `Recorded the sizes of ${plural(images, 'image')}` : 'No images you may edit needed their sizes recorded' };
	});
}

/**
 * Moves collections' entries kept in folders into their collection's
 * folder (D-514).
 */
function flatten(): Promise<void> {
	return runFix<FixAnswer & { renamed: Record<string, string> }>('flat', '/health/flatten', {}, ['entry', 'entries'], 'The entries couldn\'t be moved.', (answer) => {
		const moved = Object.keys(answer.renamed).length;

		return { changed: moved, text: moved ? `Moved ${plural(moved, 'entry', 'entries')} into their collections' folders` : 'No entries you may edit needed moving' };
	});
}

/**
 * Writes the terms and profiles entries name with no file (D-584).
 */
function writeTerms(): Promise<void> {
	return runFix<FixAnswer & { created: Record<string, string> }>('terms', '/health/terms', {}, ['term', 'terms'], 'The terms couldn\'t be written.', (answer) => {
		const written = Object.keys(answer.created).length;

		return { changed: written, text: written ? `Wrote ${plural(written, 'file')}` : 'No terms or profiles you may create were missing' };
	});
}

/**
 * Files links between entries with their ids (D-596).
 */
function fileRefs(): Promise<void> {
	return runFix<FixAnswer & { filed: string[] }>('refs', '/health/refs', {}, ['file', 'files'], 'The links couldn\'t be filed.', (answer) => {
		const filed = answer.filed.length;

		return { changed: filed, text: filed ? `Filed links in ${plural(filed, 'file')}` : 'No files you may edit needed their links filed' };
	});
}

/**
 * Migrates the data types still written as taxonomies (D-591), then
 * loads the types again.
 */
function migrateTaxonomies(): Promise<void> {
	return runFix<FixAnswer & { migrated: Record<string, string[]> }>('taxonomies', '/health/taxonomies', {}, ['type', 'types'], 'The types couldn\'t be migrated.', (answer) => {
		const migrated = Object.keys(answer.migrated).length;

		refreshTypes();

		return { changed: migrated, text: migrated ? `Migrated ${plural(migrated, 'type')}` : 'No types needed migrating' };
	});
}

/**
 * Renames a type's entries to its file name pattern (D-512).
 */
function renameFiles(type: string): Promise<void> {
	return runFix<FixAnswer & { renamed: Record<string, string> }>(`names:${type}`, '/health/filenames', { type }, ['entry', 'entries'], 'The files couldn\'t be renamed.', (answer) => {
		const renamed = Object.keys(answer.renamed).length;

		return { changed: renamed, text: renamed ? `Renamed the files of ${plural(renamed, 'entry', 'entries')}` : 'No entries you may edit needed renaming' };
	});
}

onMounted(() => {
	void load();
});
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'health' }"><AdminIcon name="chevron-left" />Site Health</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ screen.title }}</h1>
			<p class="page-header__hint">{{ health ? `Last checked ${formatWhen(health.at).toLowerCase()}` : screen.hint }}</p>
		</div>
		<div class="page-header__actions">
			<button type="button" class="button" :disabled="loading || fixing !== null" @click="load(true)">
				<span v-if="loading" class="spin" aria-hidden="true" /><AdminIcon v-else name="refresh-cw" />{{ loading ? 'Checking…' : 'Check Again' }}
			</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else-if="!health" class="loading">Loading the last check…</p>

	<template v-else-if="check === 'files'">
		<div class="toolbar">
			<label class="checkbox">
				<input v-model="notices" type="checkbox">
				Include notices
			</label>
		</div>

		<p class="notice" :class="counted('error') ? 'notice--error' : 'notice--success'" aria-live="polite">{{ summary(health) }}</p>

		<div v-if="!files.length" class="panel">
			<EmptyState icon="circle-check" heading="No Problems Found">
				Every file passed{{ notices ? ', notices included' : '' }}.
			</EmptyState>
		</div>

		<section v-for="file in files" :key="file.path" class="panel">
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

	<div v-else-if="!found" class="panel">
		<EmptyState icon="circle-check" heading="Nothing to Fix" :text="screen.clear" />
	</div>

	<section v-else-if="check === 'ids' && ids" class="panel" aria-labelledby="ids-heading">
		<header class="panel__header">
			<h2 id="ids-heading">{{ area === 'content' ? 'Files Without Their Own ID' : 'Media Without Their Own ID' }}</h2>
			<p class="panel__hint">{{ screen.hint }}</p>
		</header>
		<div v-if="ids.ids.missing.length" class="ids__row">
			<p>{{ plural(ids.ids.missing.length, 'file') }} {{ ids.ids.missing.length === 1 ? 'has' : 'have' }} no id, or one that isn't valid.</p>
			<button type="button" class="button button--primary button--small" :disabled="fixing !== null" @click="fix('missing', ids.missing)">
				{{ fixing === 'missing' ? 'Adding…' : 'Add Missing IDs' }}
			</button>
		</div>
		<div v-for="shared in ids.ids.duplicates" :key="shared.id" class="ids__row ids__row--shared">
			<p>These files share the id <code>{{ shared.id }}</code>. Keep it on one; the others get new ids.</p>
			<ul class="ids__files">
				<li v-for="file in shared.paths" :key="file">
					<code>{{ file }}</code>
					<button type="button" class="button button--small" :disabled="fixing !== null" @click="fix(`keep:${file}`, ids.keep, { path: file })">
						{{ fixing === `keep:${file}` ? 'Keeping…' : 'Keep Here' }}<span class="visually-hidden"> ({{ file }})</span>
					</button>
				</li>
			</ul>
		</div>
	</section>

	<section v-else-if="check === 'sizes'" class="panel" aria-labelledby="sizes-heading">
		<header class="panel__header">
			<h2 id="sizes-heading">Sizes Not Recorded</h2>
			<p class="panel__hint">{{ screen.hint }}</p>
		</header>
		<div class="ids__row">
			<p>
				<template v-if="health.mediaSizes.sizes">{{ plural(health.mediaSizes.sizes, 'size') }} of {{ plural(health.mediaSizes.images, 'image') }} {{ health.mediaSizes.sizes === 1 ? 'isn\'t' : 'aren\'t' }} recorded yet.</template>
				<template v-if="health.mediaSizes.stale"> {{ plural(health.mediaSizes.stale, 'image') }} {{ health.mediaSizes.stale === 1 ? 'lists' : 'list' }} files that aren't {{ health.mediaSizes.stale === 1 ? 'its' : 'their' }} sizes.</template>
			</p>
			<button type="button" class="button button--primary button--small" :disabled="fixing !== null" @click="recordSizes">
				{{ fixing === 'sizes' ? 'Recording…' : 'Record Sizes' }}
			</button>
		</div>
	</section>

	<section v-else-if="check === 'terms'" class="panel" aria-labelledby="terms-heading">
		<header class="panel__header">
			<h2 id="terms-heading">Named Without a File</h2>
			<p class="panel__hint">{{ screen.hint }}</p>
		</header>
		<div class="ids__row">
			<p>{{ plural(health.terms.count, 'term or profile has', 'terms and profiles have') }} no file, such as <code>{{ health.terms.examples[0]?.type }}/{{ health.terms.examples[0]?.slug }}</code>. Each is written published, titled as entries name it.</p>
			<button type="button" class="button button--primary button--small" :disabled="fixing !== null" @click="writeTerms">
				{{ fixing === 'terms' ? 'Writing…' : 'Write Files' }}
			</button>
		</div>
	</section>

	<section v-else-if="check === 'refs'" class="panel" aria-labelledby="refs-heading">
		<header class="panel__header">
			<h2 id="refs-heading">Links Without IDs</h2>
			<p class="panel__hint">{{ screen.hint }}</p>
		</header>
		<div class="ids__row">
			<p>{{ plural(health.refs.count, 'file has', 'files have') }} links not filed with their ids, such as <code>{{ health.refs.examples[0]?.path }}</code> ({{ health.refs.examples[0]?.relations.join(', ') }}). Each link's id is filed under <code>refs</code>, and a value naming an id or an old slug is written as the slug it has now.</p>
			<button type="button" class="button button--primary button--small" :disabled="fixing !== null" @click="fileRefs">
				{{ fixing === 'refs' ? 'Filing…' : 'File Links' }}
			</button>
		</div>
	</section>

	<section v-else-if="check === 'taxonomies'" class="panel" aria-labelledby="taxonomies-heading">
		<header class="panel__header">
			<h2 id="taxonomies-heading">Written as Taxonomies</h2>
			<p class="panel__hint">{{ screen.hint }}</p>
		</header>
		<div class="ids__row">
			<p>{{ plural(health.taxonomies.length, 'type is', 'types are') }} still written as {{ health.taxonomies.length === 1 ? 'a taxonomy' : 'taxonomies' }}: <code>{{ health.taxonomies.join(', ') }}</code>. Each file in <code>user/data/types</code> becomes a collection, keeping its other settings, and what it files moves to a relationship in <code>user/data/relations</code>.</p>
			<button type="button" class="button button--primary button--small" :disabled="fixing !== null" @click="migrateTaxonomies">
				{{ fixing === 'taxonomies' ? 'Migrating…' : 'Migrate Types' }}
			</button>
		</div>
	</section>

	<section v-else-if="check === 'folders'" class="panel" aria-labelledby="flat-heading">
		<header class="panel__header">
			<h2 id="flat-heading">Entries in Folders</h2>
			<p class="panel__hint">{{ screen.hint }}</p>
		</header>
		<div class="ids__row">
			<p>{{ plural(health.flat.count, 'entry is', 'entries are') }} kept in a folder, such as <code>{{ health.flat.examples[0]?.path }}</code> → <code>{{ health.flat.examples[0]?.to }}</code>.</p>
			<button type="button" class="button button--primary button--small" :disabled="fixing !== null" @click="flatten">
				{{ fixing === 'flat' ? 'Moving…' : 'Move Out of Folders' }}
			</button>
		</div>
	</section>

	<section v-else-if="check === 'names'" class="panel" aria-labelledby="names-heading">
		<header class="panel__header">
			<h2 id="names-heading">Older File Names</h2>
			<p class="panel__hint">{{ screen.hint }}</p>
		</header>
		<div v-for="names in health.fileNames" :key="names.type" class="ids__row">
			<p>
				{{ plural(names.count, 'entry', 'entries') }} in {{ names.label }} {{ names.count === 1 ? 'isn\'t' : 'aren\'t' }} named <code>{{ names.pattern }}</code>, such as <code>{{ names.examples[0]?.path }}</code> → <code>{{ names.examples[0]?.to }}</code>.
				<template v-if="names.skipped"> {{ plural(names.skipped, 'entry', 'entries') }} kept as {{ names.skipped === 1 ? 'a folder keeps its' : 'folders keep their' }} name.</template>
			</p>
			<button type="button" class="button button--primary button--small" :disabled="fixing !== null" @click="renameFiles(names.type)">
				{{ fixing === `names:${names.type}` ? 'Renaming…' : 'Rename Files' }}<span class="visually-hidden"> ({{ names.label }})</span>
			</button>
		</div>
	</section>
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
