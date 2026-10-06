<script setup lang="ts">
/**
 * The dashboard (D-538, from the Home sketch): a greeting by the
 * account's name (D-322, D-323) and today's date, the entry the account
 * last saved (D-539), and the entries that need it first, then the rest:
 * drafts, then scheduled, then recently published, the account's own or
 * everyone's. When nothing is waiting on the account, it says so and
 * lists what it published last. Actions are on the Tools screen (D-540).
 *
 * Until the site publishes something, a numbered setup path takes the
 * dashboard's place (40-screens §8): each step does the thing, and shows
 * when it's done. Skipping it is the account's preference.
 */

import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import EntryRows from '../components/EntryRows.vue';
import MenuButton from '../components/MenuButton.vue';
import StatusPill from '../components/StatusPill.vue';
import { navCounts } from '../counts';
import { formatWhen } from '../format';
import { can, canAnyType, canType, canUpload, savePreferences, session } from '../session';
import { errorMessage, request, type ContentTypeSummary, type Dashboard, type DashboardEntry } from '../api';
import { labelsOf, listRoute, loadTypes, typeIcon, types } from '../types';
import type { IconName } from '../icons';

const dashboard = ref<Dashboard | null>(null);
const error     = ref('');
const skipped   = ref(session.account?.preferences.setupSkipped ?? false);
const skipping  = ref(false);
const whose     = ref<'yours' | 'everyone'>('yours');

async function load(): Promise<void> {
	try {
		dashboard.value = await request<Dashboard>('GET', '/dashboard');
	} catch (caught) {
		error.value = errorMessage(caught, 'The dashboard couldn\'t be loaded.');
	}
}

/**
 * Skips the setup path for good, for this account.
 */
async function skip(): Promise<void> {
	skipping.value = true;

	try {
		await savePreferences({ setupSkipped: true });
		skipped.value = true;
	} catch (caught) {
		error.value = errorMessage(caught, 'The setup path couldn\'t be skipped.');
	} finally {
		skipping.value = false;
	}
}

// "Good afternoon, Jane Doe", by the browser's clock.
const greeting = computed(() => {
	const hour = new Date().getHours();
	const part = hour < 5 ? 'evening' : hour < 12 ? 'morning' : hour < 18 ? 'afternoon' : 'evening';

	return session.account ? `Good ${part}, ${session.account.displayName}` : 'Dashboard';
});

const today = new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric' }).format(new Date());

// Pages and collections: what's written, so what's listed and made new.
const writable = computed(() => [...types.value]
	.filter((type) => type.kind === 'tree' || type.kind === 'collection')
	.sort((a, b) => a.labels.menu.localeCompare(b.labels.menu)));
const creatable = computed(() => writable.value.filter((type) => canType(type.name, 'create')));
const browsable = computed(() => writable.value.filter((type) => canType(type.name, 'edit')));

// Collections first: what a site writes most.
const firstNew = computed(() => creatable.value.find((type) => type.kind === 'collection') ?? creatable.value[0]);

// The account's own entries, or everyone's when it has no profile.
const own    = computed(() => dashboard.value?.yours ?? dashboard.value?.everyone ?? null);
const shown  = computed(() => whose.value === 'yours' ? own.value : (dashboard.value?.everyone ?? null));
const listed = computed<DashboardEntry[]>(() => shown.value ? [...shown.value.draft, ...shown.value.scheduled, ...shown.value.published] : []);

// Nothing waiting: none of the account's own entries drafted or
// scheduled, the one it was editing included.
const clear = computed(() => dashboard.value !== null && own.value !== null
	&& (dashboard.value.resume === null || dashboard.value.resume.status === 'published')
	&& own.value.draft.length === 0 && own.value.scheduled.length === 0);
const lastPublished = computed(() => own.value?.published[0] ?? null);

interface Step {
	key: string;
	name: string;
	text: string;
	done: boolean;
	action: { label: string; to: object };
	icon?: IconName;
}

const pages = computed(() => types.value.find((type) => type.name === dashboard.value?.setup.type));

// The setup path's steps the account can take (D-240): its first page, a
// type of the site's own, media, and the people it works with.
const steps = computed<Step[]>(() => {
	const list: Step[]   = [];
	const page           = dashboard.value?.setup.page ?? null;
	const pageType: ContentTypeSummary | undefined = pages.value;

	if (pageType && canType(pageType.name, 'create')) {
		list.push(page === null ? {
			key: 'page',
			name: `Write your first ${pageType.labels.item}`,
			text: 'A page stands on its own, like About or Contact.',
			done: false,
			action: { label: pageType.labels.newItem, to: { name: 'entry-new', query: { type: pageType.name } } }
		} : {
			key: 'page',
			name: `Write your first ${pageType.labels.item}`,
			text: `Done: ${page.title || 'Untitled'}, saved ${formatWhen(page.updated).toLowerCase()}.`,
			done: true,
			action: { label: 'Open It', to: listRoute(page) }
		});
	}

	if (can('site.settings')) {
		const own = dashboard.value?.setup.ownTypes ?? false;

		list.push({
			key: 'types',
			name: 'Decide what you publish',
			text: own ? 'Done: the site has a content type of its own.' : 'A content type is the shape of one kind of entry: a post, a release, a recipe. Pages already exist; everything else you define.',
			done: own,
			action: own ? { label: 'See Types', to: { name: 'types' } } : { label: 'New Type', to: { name: 'type-new' } }
		});
	}

	if (canUpload()) {
		const has = (navCounts.value?.media ?? 0) > 0;

		list.push({
			key: 'media',
			name: 'Add your media',
			text: has ? 'Done: the library has files in it.' : 'Logos, photographs, anything an entry needs.',
			done: has,
			action: { label: has ? 'Open the Library' : 'Upload Files', to: { name: 'media' } }
		});
	}

	if (can('accounts.create')) {
		const has = (navCounts.value?.accounts ?? 1) > 1;

		list.push({
			key: 'people',
			name: 'Invite the people you work with',
			text: has ? 'Done: someone else has an account.' : 'Each person signs in with their own account, and their profile becomes their byline on the site.',
			done: has,
			action: has ? { label: 'See Accounts', to: { name: 'accounts' } } : { label: 'Invite Someone', to: { name: 'account-new' } }
		});
	}

	return list;
});

const setup = computed(() => dashboard.value !== null && dashboard.value.published === 0 && !skipped.value && steps.value.length > 0);
const done  = computed(() => steps.value.filter((step) => step.done).length);
const next  = computed(() => steps.value.find((step) => !step.done)?.key);

onMounted(() => {
	void load().then(() => {
		whose.value = dashboard.value?.yours ? 'yours' : 'everyone';
	});

	if (canAnyType('edit') || canAnyType('create')) {
		loadTypes().catch(() => undefined);
	}
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ setup ? 'Set Up Your Site' : greeting }}</h1>
			<p class="page-header__hint">{{ setup ? 'A few steps, and nothing to read first' : today }}<template v-if="dashboard">{{ ' ' }}<span class="dashboard__env">{{ dashboard.site.environment }}</span></template></p>
		</div>
		<div v-if="!setup && (creatable.length || canUpload())" class="page-header__actions">
			<MenuButton button-class="button button--primary" align="end">
				<template #button><AdminIcon name="plus" />New<AdminIcon name="chevron-down" /></template>
				<RouterLink v-for="type in creatable" :key="type.name" class="menu-item" :to="{ name: 'entry-new', query: { type: type.name } }"><AdminIcon :name="typeIcon(type)" />{{ type.labels.newItem }}</RouterLink>
				<template v-if="canUpload()">
					<hr v-if="creatable.length" class="menu-rule">
					<RouterLink class="menu-item" :to="{ name: 'media' }"><AdminIcon name="upload" />Upload to the Library</RouterLink>
				</template>
			</MenuButton>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="!dashboard">
		<p v-if="!error" class="visually-hidden" role="status">Loading the dashboard…</p>
		<section v-if="!error" class="panel" aria-hidden="true">
			<header class="panel__header">
				<span class="skeleton skeleton--heading" style="width: 7rem" />
			</header>
			<ul class="rows">
				<li v-for="row in 4" :key="row" class="rows__link">
					<span class="rows__main">
						<span class="skeleton" :style="{ width: `${30 + row * 9}%` }" />
						<span class="skeleton skeleton--small" :style="{ width: `${22 - row * 2}%` }" />
					</span>
				</li>
			</ul>
		</section>
	</template>

	<section v-else-if="setup" class="panel" aria-labelledby="setup-heading">
		<header class="panel__header">
			<h2 id="setup-heading">Getting Started</h2>
			<p class="panel__hint">{{ done }} of {{ steps.length }} done</p>
		</header>
		<ol class="setup">
			<li v-for="(step, index) in steps" :key="step.key" class="setup__step" :class="{ 'is-done': step.done }">
				<span class="setup__number" aria-hidden="true">
					<AdminIcon v-if="step.done" name="check" />
					<template v-else>{{ index + 1 }}</template>
				</span>
				<span class="setup__text">
					<span class="setup__title">{{ step.name }}<span v-if="step.done" class="visually-hidden"> (done)</span></span>
					<span class="setup__hint">{{ step.text }}</span>
				</span>
				<RouterLink class="button button--small" :class="{ 'button--primary': step.key === next }" :to="step.action.to">{{ step.action.label }}</RouterLink>
			</li>
		</ol>
		<p class="panel__note setup__note">
			The dashboard takes this screen's place once the site publishes something.
			<button type="button" class="link-button" :disabled="skipping" @click="skip">Skip to the Dashboard</button>
		</p>
	</section>

	<template v-else>
		<section v-if="dashboard.resume" class="resume" aria-labelledby="resume-heading">
			<span class="resume__mark" aria-hidden="true"><AdminIcon name="square-pen" /></span>
			<div class="resume__body">
				<p id="resume-heading" class="resume__kicker">You Were Editing</p>
				<RouterLink class="resume__title" :class="{ untitled: !dashboard.resume.title }" :to="listRoute(dashboard.resume)">{{ dashboard.resume.title || 'Untitled' }}</RouterLink>
				<p class="resume__meta">
					<span>{{ labelsOf(dashboard.resume.type).singular }}</span>
					<template v-if="dashboard.resume.url"><span class="resume__dot" aria-hidden="true">·</span><span class="mono">{{ dashboard.resume.url }}</span></template>
					<span class="resume__dot" aria-hidden="true">·</span><StatusPill :status="dashboard.resume.status" />
					<span class="resume__dot" aria-hidden="true">·</span><span>Saved {{ formatWhen(dashboard.resume.updated).toLowerCase() }}</span>
				</p>
			</div>
			<RouterLink class="button button--primary" :to="listRoute(dashboard.resume)">Continue Editing</RouterLink>
		</section>

		<template v-if="clear">
			<div class="panel">
				<EmptyState icon="circle-check" heading="Nothing Is Waiting on You" tag="h2">
					No drafts and nothing scheduled.<template v-if="lastPublished"> The last thing you published was <strong>{{ lastPublished.title || 'Untitled' }}</strong>, {{ formatWhen(lastPublished.published ?? lastPublished.updated) }}.</template>
					<template #actions>
						<div v-if="firstNew || browsable.length" class="dashboard__clear-actions">
							<RouterLink v-if="firstNew" class="button button--primary" :to="{ name: 'entry-new', query: { type: firstNew.name } }"><AdminIcon name="plus" />{{ firstNew.labels.newItem }}</RouterLink>
							<MenuButton v-if="browsable.length" button-class="button" align="start">
								<template #button>Browse by Type<AdminIcon name="chevron-down" /></template>
								<RouterLink v-for="type in browsable" :key="type.name" class="menu-item" :to="{ name: 'type', params: { type: type.name } }"><AdminIcon :name="typeIcon(type)" />{{ type.labels.menu }}</RouterLink>
							</MenuButton>
						</div>
					</template>
				</EmptyState>
			</div>
			<section v-if="own?.published.length" class="panel" aria-labelledby="published-heading">
				<header class="panel__header">
					<h2 id="published-heading">Recently Published</h2>
					<p class="panel__hint">{{ dashboard.yours ? 'Mine, newest first' : 'Newest first' }}</p>
				</header>
				<EntryRows :entries="own.published" />
			</section>
		</template>

		<section v-else class="panel" aria-labelledby="entries-heading">
			<header class="panel__header">
				<h2 id="entries-heading">Entries</h2>
				<p class="panel__hint">What needs you first, then the rest</p>
				<div v-if="dashboard.yours" class="panel__actions">
					<div class="segmented" role="group" aria-label="Whose entries">
						<button type="button" :aria-pressed="whose === 'yours'" @click="whose = 'yours'">Mine</button>
						<button type="button" :aria-pressed="whose === 'everyone'" @click="whose = 'everyone'">Everyone</button>
					</div>
				</div>
			</header>
			<EntryRows v-if="listed.length" :entries="listed" :credits="whose === 'everyone'" />
			<p v-else class="panel__body muted">{{ whose === 'yours' ? 'You have no entries yet.' : 'There are no entries yet.' }}</p>
			<footer v-if="browsable.length" class="panel__foot">
				<MenuButton button-class="lnk" align="start" floating>
					<template #button>Browse by Type<AdminIcon name="chevron-down" /></template>
					<RouterLink v-for="type in browsable" :key="type.name" class="menu-item" :to="{ name: 'type', params: { type: type.name } }"><AdminIcon :name="typeIcon(type)" />{{ type.labels.menu }}</RouterLink>
				</MenuButton>
			</footer>
		</section>
	</template>
</template>

<style scoped>
.resume {
	display: flex;
	align-items: center;
	gap: var(--s-4);
	padding: var(--s-4) var(--pad-x);
	border: 1px solid var(--border);
	border-radius: var(--r-3);
	background: var(--surface);
	box-shadow: var(--shadow-1);
}

.resume__mark {
	display: grid;
	flex: none;
	place-items: center;
	width: 38px;
	height: 38px;
	border-radius: var(--r-2);
	background: var(--accent-soft);
	color: var(--accent);
}

.resume__mark .icon {
	width: 20px;
	height: 20px;
}

.resume__body {
	flex: 1 1 auto;
	min-width: 0;
}

.resume__kicker {
	margin: 0;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

.resume__title {
	display: block;
	margin-top: 3px;
	overflow: hidden;
	color: var(--fg);
	font-family: var(--font-title);
	font-size: var(--title-lead);
	font-weight: var(--title-weight);
	letter-spacing: var(--title-track);
	text-decoration: none;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.resume__title:hover {
	color: var(--accent);
}

.resume__meta {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
	margin: 4px 0 0;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.resume__dot {
	color: var(--border-strong);
}

.dashboard__env {
	display: inline-flex;
	align-items: center;
	height: 20px;
	margin-left: var(--s-2);
	padding: 0 7px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--surface-2);
	color: var(--fg-2);
	font-family: var(--font-mono);
	font-size: var(--text-xs);
	vertical-align: middle;
}

.dashboard__clear-actions {
	display: flex;
	flex-wrap: wrap;
	justify-content: center;
	gap: var(--s-2);
	margin-top: calc(var(--s-5) - 7px);
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

.setup__number svg {
	width: 14px;
	height: 14px;
}

.is-done .setup__number {
	background: var(--good-soft);
	color: var(--good);
}

.setup__text {
	display: grid;
	gap: 2px;
}

.setup__title {
	font-weight: 500;
}

.is-done .setup__title {
	color: var(--fg-2);
}

.setup__hint {
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.setup__note {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	gap: var(--s-2) var(--s-4);
}

@media (width <= 640px) {
	.resume {
		flex-wrap: wrap;
	}

	.resume > .button {
		width: 100%;
	}

	.setup__step {
		grid-template-columns: auto minmax(0, 1fr);
	}

	.setup__step .button {
		grid-column: 2;
		justify-self: start;
	}
}
</style>
