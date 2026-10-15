<script setup lang="ts">
/**
 * The dashboard (D-538, from the Home sketch): a greeting by the
 * account's name (D-322, D-323) and today's date, the entry the account
 * last saved (D-539), and the entries that need it first, then the rest:
 * drafts, then scheduled, then recently published, the account's own or
 * everyone's. When nothing is waiting on the account, it says so and
 * lists what it published last. Actions are on the Tools screen (D-540).
 *
 * Your API tokens that expire within a week are a notice above it all
 * (D-748), with a way to Your Account to make new ones.
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
import MessageText from '../components/MessageText.vue';
import StatusPill from '../components/StatusPill.vue';
import { locale, t } from '../messages';
import { when } from '../people';
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
		error.value = errorMessage(caught, t('dashboard_view.load_failed', 'The dashboard couldn\'t be loaded.'));
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
		error.value = errorMessage(caught, t('dashboard_view.skip_failed', 'The setup path couldn\'t be skipped.'));
	} finally {
		skipping.value = false;
	}
}

// "Good afternoon, Jane Doe", by the browser's clock.
const greeting = computed(() => {
	const hour = new Date().getHours();
	const part = hour < 5 ? 'evening' : hour < 12 ? 'morning' : hour < 18 ? 'afternoon' : 'evening';

	return session.account ? t('dashboard_view.greeting', '{part, select, morning {Good morning, {name}} afternoon {Good afternoon, {name}} other {Good evening, {name}}}', { part, name: session.account.displayName }) : t('dashboard_view.title', 'Dashboard');
});

const today = new Intl.DateTimeFormat(locale(), { weekday: 'long', month: 'long', day: 'numeric' }).format(new Date());

// Pages and collections: what's written, so what's listed and made new.
const writable = computed(() => [...types.value]
	.filter((type) => type.kind === 'tree' || type.kind === 'collection')
	.sort((a, b) => a.labels.menu.localeCompare(b.labels.menu, locale())));
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
			name: t('dashboard_view.step_page', 'Write your first {item}', { item: pageType.labels.item }),
			text: t('dashboard_view.step_page_text', 'A page stands on its own, like About or Contact.'),
			done: false,
			action: { label: pageType.labels.newItem, to: { name: 'entry-new', query: { type: pageType.name } } }
		} : {
			key: 'page',
			name: t('dashboard_view.step_page', 'Write your first {item}', { item: pageType.labels.item }),
			text: t('dashboard_view.step_page_done', 'Done: {title}, saved {when}.', { title: page.title || t('dashboard_view.untitled', 'Untitled'), when: formatWhen(page.updated).toLowerCase() }),
			done: true,
			action: { label: t('dashboard_view.step_page_open', 'Open It'), to: listRoute(page) }
		});
	}

	if (can('site.settings')) {
		const own = dashboard.value?.setup.ownTypes ?? false;

		list.push({
			key: 'types',
			name: t('dashboard_view.step_types', 'Decide what you publish'),
			text: own ? t('dashboard_view.step_types_done', 'Done: the site has a content type of its own.') : t('dashboard_view.step_types_text', 'A content type is the shape of one kind of entry: a post, a release, a recipe. Pages already exist; everything else you define.'),
			done: own,
			action: own ? { label: t('dashboard_view.step_types_see', 'See Types'), to: { name: 'types' } } : { label: t('dashboard_view.step_types_new', 'New Type'), to: { name: 'type-new' } }
		});
	}

	if (canUpload()) {
		const has = dashboard.value?.setup.media ?? false;

		list.push({
			key: 'media',
			name: t('dashboard_view.step_media', 'Add your media'),
			text: has ? t('dashboard_view.step_media_done', 'Done: the library has files in it.') : t('dashboard_view.step_media_text', 'Logos, photographs, anything an entry needs.'),
			done: has,
			action: { label: has ? t('dashboard_view.step_media_open', 'Open the Library') : t('dashboard_view.step_media_upload', 'Upload Files'), to: { name: 'media' } }
		});
	}

	if (can('accounts.create')) {
		const has = dashboard.value?.setup.others ?? false;

		list.push({
			key: 'people',
			name: t('dashboard_view.step_people', 'Invite the people you work with'),
			text: has ? t('dashboard_view.step_people_done', 'Done: someone else has an account.') : t('dashboard_view.step_people_text', 'Each person signs in with their own account, and their profile becomes their byline on the site.'),
			done: has,
			action: has ? { label: t('dashboard_view.step_people_see', 'See Accounts'), to: { name: 'accounts' } } : { label: t('dashboard_view.step_people_invite', 'Invite Someone'), to: { name: 'account-new' } }
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
			<h1 tabindex="-1">{{ setup ? t('dashboard_view.setup_title', 'Set Up Your Site') : greeting }}</h1>
			<p class="page-header__hint">{{ setup ? t('dashboard_view.setup_hint', 'A few steps, and nothing to read first') : today }}<template v-if="dashboard">{{ ' ' }}<span class="dashboard__env">{{ dashboard.site.environment }}</span></template></p>
		</div>
		<div v-if="!setup && (creatable.length || canUpload())" class="page-header__actions">
			<MenuButton button-class="button button--primary" align="end">
				<template #button><AdminIcon name="plus" />{{ t('dashboard_view.new', 'New') }}<AdminIcon name="chevron-down" /></template>
				<RouterLink v-for="type in creatable" :key="type.name" class="menu-item" :to="{ name: 'entry-new', query: { type: type.name } }"><AdminIcon :name="typeIcon(type)" />{{ type.labels.newItem }}</RouterLink>
				<template v-if="canUpload()">
					<hr v-if="creatable.length" class="menu-rule">
					<RouterLink class="menu-item" :to="{ name: 'media' }"><AdminIcon name="upload" />{{ t('dashboard_view.upload', 'Upload to the Library') }}</RouterLink>
				</template>
			</MenuButton>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="!dashboard">
		<p v-if="!error" class="visually-hidden" role="status">{{ t('dashboard_view.loading', 'Loading the dashboard…') }}</p>
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
			<h2 id="setup-heading">{{ t('dashboard_view.setup_heading', 'Getting Started') }}</h2>
			<p class="panel__hint">{{ t('dashboard_view.setup_done', '{done} of {total} done', { done, total: steps.length }) }}</p>
		</header>
		<ol class="setup">
			<li v-for="(step, index) in steps" :key="step.key" class="setup__step" :class="{ 'is-done': step.done }">
				<span class="setup__number" aria-hidden="true">
					<AdminIcon v-if="step.done" name="check" />
					<template v-else>{{ index + 1 }}</template>
				</span>
				<span class="setup__text">
					<span class="setup__title">{{ step.name }}<span v-if="step.done" class="visually-hidden">{{ t('dashboard_view.step_done', ' (done)') }}</span></span>
					<span class="setup__hint">{{ step.text }}</span>
				</span>
				<RouterLink class="button button--small" :class="{ 'button--primary': step.key === next }" :to="step.action.to">{{ step.action.label }}</RouterLink>
			</li>
		</ol>
		<p class="panel__note setup__note">
			{{ t('dashboard_view.setup_note', 'The dashboard takes this screen\'s place once the site publishes something.') }}
			<button type="button" class="link-button" :disabled="skipping" @click="skip">{{ t('dashboard_view.skip', 'Skip to the Dashboard') }}</button>
		</p>
	</section>

	<template v-else>
		<div v-if="dashboard.tokens.length && session.account" class="notice notice--warn">
			<AdminIcon name="key-round" />
			<span class="notice__text"><MessageText :text="t('dashboard_view.tokens', '{expiring} Whatever uses {count, plural, one {it} other {them} } stops working then; make a new one and swap it in.', { count: dashboard.tokens.length })"><template #expiring><strong>{{ dashboard.tokens.length === 1 ? t('dashboard_view.token_expires', 'Your API token {name} expires {when}.', { name: dashboard.tokens[0]?.name, when: when(dashboard.tokens[0]?.expires ?? null) }) : t('dashboard_view.tokens_expire', '{count} of your API tokens expire within a week.', { count: dashboard.tokens.length }) }}</strong></template></MessageText></span>
			<span class="notice__buttons"><RouterLink class="button button--small" :to="{ name: 'account', params: { username: session.account.username } }">{{ t('dashboard_view.your_account', 'Your Account') }}</RouterLink></span>
		</div>

		<section v-if="dashboard.resume" class="resume" aria-labelledby="resume-heading">
			<span class="resume__mark" aria-hidden="true"><AdminIcon name="square-pen" /></span>
			<div class="resume__body">
				<p id="resume-heading" class="resume__kicker">{{ t('dashboard_view.resume', 'You Were Editing') }}</p>
				<RouterLink class="resume__title" :class="{ untitled: !dashboard.resume.title }" :to="listRoute(dashboard.resume)">{{ dashboard.resume.title || t('dashboard_view.untitled', 'Untitled') }}</RouterLink>
				<p class="resume__meta">
					<span>{{ labelsOf(dashboard.resume.type).singular }}</span>
					<template v-if="dashboard.resume.url"><span class="resume__dot" aria-hidden="true">·</span><span class="mono">{{ dashboard.resume.url }}</span></template>
					<span class="resume__dot" aria-hidden="true">·</span><StatusPill :status="dashboard.resume.status" />
					<span class="resume__dot" aria-hidden="true">·</span><span>{{ t('dashboard_view.resume_saved', 'Saved {when}', { when: formatWhen(dashboard.resume.updated).toLowerCase() }) }}</span>
				</p>
			</div>
			<RouterLink class="button button--primary" :to="listRoute(dashboard.resume)">{{ t('dashboard_view.continue', 'Continue Editing') }}</RouterLink>
		</section>

		<template v-if="clear">
			<div class="panel">
				<EmptyState icon="circle-check" :heading="t('dashboard_view.clear_heading', 'Nothing Is Waiting on You')" tag="h2">
					{{ t('dashboard_view.clear_text', 'No drafts and nothing scheduled.') }}<template v-if="lastPublished">{{ ' ' }}<MessageText :text="t('dashboard_view.last_published', 'The last thing you published was {title}, {when}.', { when: formatWhen(lastPublished.date ?? lastPublished.updated) })"><template #title><strong>{{ lastPublished.title || t('dashboard_view.untitled', 'Untitled') }}</strong></template></MessageText></template>
					<template #actions>
						<div v-if="firstNew || browsable.length" class="dashboard__clear-actions">
							<RouterLink v-if="firstNew" class="button button--primary" :to="{ name: 'entry-new', query: { type: firstNew.name } }"><AdminIcon name="plus" />{{ firstNew.labels.newItem }}</RouterLink>
							<MenuButton v-if="browsable.length" button-class="button" align="start" floating>
								<template #button>{{ t('dashboard_view.browse', 'Browse by Type') }}<AdminIcon name="chevron-down" /></template>
								<RouterLink v-for="type in browsable" :key="type.name" class="menu-item" :to="{ name: 'type', params: { type: type.name } }"><AdminIcon :name="typeIcon(type)" />{{ type.labels.menu }}</RouterLink>
							</MenuButton>
						</div>
					</template>
				</EmptyState>
			</div>
			<section v-if="own?.published.length" class="panel" aria-labelledby="published-heading">
				<header class="panel__header">
					<h2 id="published-heading">{{ t('dashboard_view.published_heading', 'Recently Published') }}</h2>
					<p class="panel__hint">{{ dashboard.yours ? t('dashboard_view.published_mine', 'Mine, newest first') : t('dashboard_view.published_all', 'Newest first') }}</p>
				</header>
				<EntryRows :entries="own.published" />
			</section>
		</template>

		<section v-else class="panel" aria-labelledby="entries-heading">
			<header class="panel__header">
				<h2 id="entries-heading">{{ t('dashboard_view.entries_heading', 'Entries') }}</h2>
				<p class="panel__hint">{{ t('dashboard_view.entries_hint', 'What needs you first, then the rest') }}</p>
				<div v-if="dashboard.yours" class="panel__actions">
					<div class="segmented" role="group" :aria-label="t('dashboard_view.whose', 'Whose entries')">
						<button type="button" :aria-pressed="whose === 'yours'" @click="whose = 'yours'">{{ t('dashboard_view.mine', 'Mine') }}</button>
						<button type="button" :aria-pressed="whose === 'everyone'" @click="whose = 'everyone'">{{ t('dashboard_view.everyone', 'Everyone') }}</button>
					</div>
				</div>
			</header>
			<EntryRows v-if="listed.length" :entries="listed" :credits="whose === 'everyone'" />
			<p v-else class="panel__body muted">{{ whose === 'yours' ? t('dashboard_view.none_yours', 'You have no entries yet.') : t('dashboard_view.none', 'There are no entries yet.') }}</p>
			<footer v-if="browsable.length" class="panel__foot">
				<MenuButton button-class="lnk" align="start" floating>
					<template #button>{{ t('dashboard_view.browse', 'Browse by Type') }}<AdminIcon name="chevron-down" /></template>
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
	padding: var(--s-4) var(--padding-inline);
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-large);
	background: var(--color-surface);
	box-shadow: var(--shadow-1);
}

.resume__mark {
	display: grid;
	flex: none;
	place-items: center;
	width: 38px;
	height: 38px;
	border-radius: var(--border-radius-medium);
	background: var(--color-accent-subtle);
	color: var(--color-accent);
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
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

.resume__title {
	display: block;
	margin-top: 3px;
	overflow: hidden;
	color: var(--color-text);
	font-family: var(--font-family-title);
	font-size: var(--font-size-entry-title-lead);
	font-weight: var(--font-weight-entry-title);
	letter-spacing: var(--letter-spacing-entry-title);
	text-decoration: none;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.resume__title:hover {
	color: var(--color-accent);
}

.resume__meta {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
	margin: 4px 0 0;
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}

.resume__dot {
	color: var(--border-color-strong);
}

.dashboard__env {
	display: inline-flex;
	align-items: center;
	height: 20px;
	margin-left: var(--s-2);
	padding: 0 7px;
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-small);
	background: var(--color-surface-subtle);
	color: var(--color-text-secondary);
	font-family: var(--font-mono);
	font-size: var(--font-size-xsmall);
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
	padding: var(--padding-row) var(--padding-inline);
}

.setup__step + .setup__step {
	border-top: 1px solid var(--border-color);
}

.setup__number {
	display: grid;
	place-items: center;
	width: 24px;
	height: 24px;
	border-radius: 50%;
	background: var(--color-accent-subtle);
	color: var(--color-accent);
	font-family: var(--font-mono);
	font-size: var(--font-size-small);
	font-variant-numeric: tabular-nums;
}

.setup__number svg {
	width: 14px;
	height: 14px;
}

.is-done .setup__number {
	background: var(--color-success-subtle);
	color: var(--color-success);
}

.setup__text {
	display: grid;
	gap: 2px;
}

.setup__title {
	font-weight: 500;
}

.is-done .setup__title {
	color: var(--color-text-secondary);
}

.setup__hint {
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
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
