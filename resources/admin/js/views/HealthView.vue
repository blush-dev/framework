<script setup lang="ts">
/**
 * One of Site Health's checks (D-546; once Content Health, D-543), drawn
 * from the Site Health sketch (D-612): everything the check found, each
 * fixable where it's listed. It shows Site Health's last check (`GET
 * health`), says when it ran, and checks again when asked (`POST
 * health`), which updates Site Health too.
 *
 * - **Groups and rows** (`health.ts`): problems of one kind under a
 *   heading that says what the site does about them; a row each, with
 *   its own fix where Blush has one.
 * - **Fixing:** one row's fix runs at once. More than one row, and any
 *   group fix, asks first, listing every change (8, then "and N more").
 *   A fixed row stays where it was, marked Fixed, until Check Again
 *   clears it; the check runs again after each fix, so the figures,
 *   Site Health, and its count follow at once.
 * - **Ignoring** (D-613): a warning or notice can be ignored from its
 *   row's menu, for the site, and moves to the Ignored tab, where it
 *   says who ignored it and Stop Ignoring brings it back. Ignored
 *   problems stop counting here and in Site Health.
 * - **The list's own parts:** the tabs with their counts (Needs
 *   Attention, Notices where a check has them, and Ignored), which say
 *   what the figures would, the search (`/`), a severity filter, Group
 *   By where one file can have several problems, and the rows' density.
 * - **Scale:** a group shows 5 rows and grows 50 at a time.
 */

import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { entryRoute, errorMessage, request, type Health } from '../api';
import { useAction } from '../action';
import { confirmAction } from '../confirm';
import { finish } from '../jobs';
import { loadCounts } from '../counts';
import { compact } from '../density';
import AdminIcon from '../components/AdminIcon.vue';
import DensityToggle from '../components/DensityToggle.vue';
import AdminSelect from '../components/AdminSelect.vue';
import EmptyState from '../components/EmptyState.vue';
import HealthRow from '../components/HealthRow.vue';
import { formatNumber, formatWhen } from '../format';
import { healthGroups, SCREENS, type HealthCheckKey, type HealthGroup, type HealthRow as Row, type Severity } from '../health';
import { screenTitle } from '../screen';
import { useSearchKey } from '../search-key';
import { toast } from '../toast';
import { refreshTypes } from '../types';
import { t } from '../messages';

const props = defineProps<{
	area: 'content' | 'media';
	check: HealthCheckKey;
}>();

const route = useRoute();

const screen = computed(() => SCREENS[`${props.area}:${props.check}`] ?? { title: t('health_view.site_health', 'Site Health'), about: '', clear: '', byFile: false });

watch(screen, (value) => {
	screenTitle.value = value.title;
}, { immediate: true });

const health = ref<Health | null>(null);

const { busy: loading, error, run } = useAction();

// The row or group whose fix is running, and how far along a fix that
// runs as a job is (D-624).
const fixing   = ref<string | null>(null);
const progress = ref<number | null>(null);

// How far along checking again is, when it runs as a job (D-625).
const checking = ref<number | null>(null);

// Rows fixed since the check last ran, by key: kept where they were,
// with what the fix did, until Check Again.
interface Fixed {
	group: Omit<HealthGroup, 'rows'>;
	row: Row;
	at: number;
	done: string;
}

const fixed = ref<Record<string, Fixed>>({});

const PER_GROUP = 5;
const MORE      = 50;

const query = ref('');
const sev   = ref<'any' | Severity>('any');
const mode  = ref<'problem' | 'file'>('problem');
const shown = ref<Record<string, number>>({});

// Grouped by file, how many files show; more come 50 at a time.
const FILES      = 20;
const filesShown = ref(FILES);

const searchField = useSearchKey();

/**
 * Loads the last check, or checks again (`again`), which Site Health and
 * its count follow. Checking again when asked (`clear`) clears the rows
 * marked Fixed.
 */
async function load(again = false, clear = again): Promise<void> {
	checking.value = null;

	await run(t('health_view.check_failed', 'The files couldn\'t be checked.'), async () => {
		const answer = await request<Health | { job: string }>(again ? 'POST' : 'GET', '/health');

		// Checking again reads every file, a chunk at a time in a job
		// (D-625), then the new check is loaded.
		if ('job' in answer) {
			await finish(answer.job, (job) => {
				checking.value = job.progress;
			});
		}

		health.value   = 'job' in answer ? await request<Health>('GET', '/health') : answer;
		checking.value = null;

		if (clear) {
			fixed.value = {};
		}

		if (again) {
			void loadCounts();
		}
	});
}

// Another check starts afresh.
watch(() => [props.area, props.check], () => {
	fixed.value = {};
	query.value = '';
	sev.value   = 'any';
	mode.value  = 'problem';
	shown.value = {};
	filesShown.value = FILES;
});

// The check's groups, with the rows fixed since it last ran put back
// where they were.
const groups = computed<HealthGroup[]>(() => {
	const built = health.value === null ? [] : healthGroups(health.value, props.area, props.check);

	for (const item of Object.values(fixed.value)) {
		let group = built.find((each) => each.key === item.group.key);

		if (group === undefined) {
			group = { ...item.group, rows: [] };
			built.push(group);
		}

		if (!group.rows.some((row) => row.key === item.row.key)) {
			group.rows.splice(Math.min(item.at, group.rows.length), 0, item.row);
		}
	}

	return built;
});

const isFixed   = (row: Row): boolean => fixed.value[row.key] !== undefined;
const ignoredOf = (row: Row): { name: string; at: string } | undefined => health.value?.ignored[row.key];

const tally = computed(() => {
	const counts = { error: 0, warning: 0, notice: 0, fixed: 0, ignored: 0 };

	for (const group of groups.value) {
		for (const row of group.rows) {
			counts[isFixed(row) ? 'fixed' : (ignoredOf(row) ? 'ignored' : row.severity)]++;
		}
	}

	return counts;
});

type Tab = 'need' | 'notices' | 'ignored';

const needs     = computed(() => tally.value.error + tally.value.warning);
const hasNotice = computed(() => groups.value.some((group) => group.severity === 'notice'));
const tab       = computed<Tab>(() => {
	switch (route.query.tab) {
		case 'notices':
			return hasNotice.value ? 'notices' : 'need';
		case 'ignored':
			return 'ignored';
		default:
			return 'need';
	}
});
const twoKinds  = computed(() => groups.value.some((group) => group.severity === 'error') && groups.value.some((group) => group.severity === 'warning'));

const sevOptions  = [{ value: 'any', label: t('health_view.any_severity', 'Any severity') }, { value: 'error', label: t('health_view.errors', 'Errors') }, { value: 'warning', label: t('health_view.warnings', 'Warnings') }];
const modeOptions = [{ value: 'problem', label: t('health_view.group_by_problem', 'Group by problem') }, { value: 'file', label: t('health_view.group_by_file', 'Group by file') }];

const sevValue = computed({
	get: () => sev.value,
	set: (value: string) => {
		sev.value = value === 'error' || value === 'warning' ? value : 'any';
	}
});

const modeValue = computed({
	get: () => mode.value,
	set: (value: string) => {
		mode.value = value === 'file' ? 'file' : 'problem';
	}
});

// Whether a row is in a tab: an ignored one only in Ignored, the rest by
// severity (a fixed row stays in its own).
function inTab(row: Row, group: HealthGroup): boolean {
	if (tab.value === 'ignored') {
		return !isFixed(row) && ignoredOf(row) !== undefined;
	}

	return (isFixed(row) || ignoredOf(row) === undefined) && (tab.value === 'notices') === (group.severity === 'notice');
}

// The groups' rows in this tab that match the search and severity.
const visible = computed(() => {
	const words = query.value.trim().toLowerCase();
	const match = (row: Row): boolean => words === '' || [row.title ?? '', row.path, row.field ?? '', row.found, row.meta ?? ''].join(' ').toLowerCase().includes(words);

	return groups.value
		.filter((group) => tab.value !== 'need' || sev.value === 'any' || group.severity === sev.value)
		.map((group) => ({ group, rows: group.rows.filter((row) => inTab(row, group) && match(row)) }))
		.filter((item) => item.rows.length > 0);
});

const visibleCount = computed(() => visible.value.reduce((total, item) => total + item.rows.length, 0));

// Grouped by file: each file once, with its problems, worst first.
const files = computed(() => {
	const byPath = new Map<string, { path: string; title: string | null; entry?: Row['entry']; rows: { row: Row; group: HealthGroup }[] }>();

	for (const { group, rows } of visible.value) {
		for (const row of rows) {
			const file = byPath.get(row.path) ?? { path: row.path, title: row.title, entry: row.entry, rows: [] };

			file.rows.push({ row, group });
			byPath.set(row.path, file);
		}
	}

	const rank = (rows: { row: Row }[]): number => Math.min(...rows.map(({ row }) => ['error', 'warning', 'notice'].indexOf(row.severity)));

	return [...byPath.values()].sort((a, b) => rank(a.rows) - rank(b.rows));
});

const PILLS: Record<Severity, { label: string; kind: string }> = {
	error: { label: t('health_view.pill_error', 'Error'), kind: 'pill--danger' },
	warning: { label: t('health_view.pill_warning', 'Warning'), kind: 'pill--warn' },
	notice: { label: t('health_view.pill_notice', 'Notice'), kind: '' }
};

// Ignored shows even at 0, as a list's Draft tab does.
const tabs = computed(() => [
	{ key: 'need', label: t('health_view.tab_need', 'Needs Attention'), count: needs.value },
	...(hasNotice.value ? [{ key: 'notices', label: t('health_view.tab_notices', 'Notices'), count: tally.value.notice }] : []),
	{ key: 'ignored', label: t('health_view.tab_ignored', 'Ignored'), count: tally.value.ignored }
]);

// The rows a group's fix would change: those not fixed or ignored, and
// not waiting on a choice of their own.
const bulkRows = (rows: Row[]): Row[] => rows.filter((row) => !isFixed(row) && ignoredOf(row) === undefined && row.choices === undefined);

// What a group's heading counts: its rows still open.
const openCount = (rows: Row[]): number => rows.filter((row) => !isFixed(row)).length;

/**
 * Ignores a row's problem for the site, or stops (D-613), with Undo for
 * ignoring; Site Health's count follows.
 */
async function setIgnored(row: Row, ignore: boolean): Promise<void> {
	if (health.value === null || fixing.value !== null) {
		return;
	}

	fixing.value = row.key;

	try {
		const answer = await request<{ ignored: Health['ignored'] }>('POST', ignore ? '/health/ignore' : '/health/unignore', { key: row.key });

		health.value = { ...health.value, ignored: answer.ignored };
		void loadCounts();

		if (ignore) {
			toast(t('health_view.ignored', 'Ignored. It\'s in the Ignored tab.'), { undo: () => void setIgnored(row, false) });
		} else {
			toast(t('health_view.unignored', 'No longer ignored'));
		}
	} catch (caught) {
		toast(errorMessage(caught, t('health_view.change_failed', 'It couldn\'t be changed.')), { kind: 'danger' });
	} finally {
		fixing.value = null;
	}
}

/**
 * Runs a group's fix for some of its rows (`choice`, the file a row
 * chose to keep), asking first for more than one row, a group fix, or a
 * fix that always asks,
 * with every change listed. Rows it fixed are marked Fixed; a file it
 * couldn't change is named, with why. Then the check runs again.
 */
async function fix(group: HealthGroup, rows: Row[], options: { bulk?: boolean; choice?: string } = {}): Promise<void> {
	const fixer = group.fix;

	if (fixer === undefined || rows.length === 0 || fixing.value !== null) {
		return;
	}

	if (options.bulk || rows.length > 1 || fixer.ask) {
		const listed = rows.slice(0, 8);
		const ok     = await confirmAction({
			title: group.bulk?.title(rows.length) ?? t('health_view.change_files_title', 'Change {count, plural, one {# File} other {# Files}}?', { count: rows.length }),
			body: group.bulk?.say ?? t('health_view.change_files_body', 'Each file gets the change below and nothing else.'),
			items: listed.map((row) => ({ title: row.path, meta: row.fix?.change ?? '' })),
			more: rows.length > listed.length ? t('health_view.and_more', 'and {count} more, the same change in each', { count: rows.length - listed.length }) : undefined,
			after: group.bulk?.after ?? t('health_view.change_files_after', 'Published entries change on the site as soon as this is done.'),
			confirm: group.bulk?.label(rows.length) ?? t('health_view.change_files', 'Change {count, plural, one {# File} other {# Files}}', { count: rows.length })
		});

		if (!ok) {
			return;
		}
	}

	fixing.value = options.bulk ? group.key : (rows[0]?.key ?? null);

	try {
		const answer = await sendFix(fixer.path, fixer.body(rows, options.choice));
		const failed = Object.entries(answer.failed ?? {});
		const done   = rows.filter((row) => answer.failed?.[row.path] === undefined && (row.choices === undefined || failed.length === 0));

		const next = { ...fixed.value };

		for (const row of done) {
			const { rows: _rows, ...meta } = group;

			next[row.key] = { group: meta, row, at: group.rows.indexOf(row), done: row.fix?.done ?? t('health_view.fixed', 'Fixed.') };
		}

		fixed.value = next;

		if (failed.length) {
			toast(t('health_view.fix_failed_files', '{files} couldn\'t be changed: {reasons}', { files: fixer.noun(failed.length), reasons: failed.map(([file, why]) => t('health_view.file_reason', '{file} ({why})', { file, why })).join('; ') }), { kind: 'warn' });
		} else if (done.length) {
			toast(done.length === 1 ? t('health_view.fixed_one', 'Fixed {name}', { name: done[0]?.title ?? done[0]?.path }) : t('health_view.fixed_many', 'Fixed {things}', { things: group.unit(done.length) }), { kind: 'good' });
		}

		if (props.check === 'taxonomies' || props.check === 'types') {
			refreshTypes();
		}

		await load(true, false);
	} catch (caught) {
		toast(errorMessage(caught, t('health_view.fix_failed', 'It couldn\'t be fixed.')), { kind: 'danger' });
	} finally {
		fixing.value   = null;
		progress.value = null;
	}
}

/**
 * Sends a fix. One that changes many files answers a job (D-624), run
 * and followed here to its end, whose result is the fix's answer.
 */
async function sendFix(path: string, body: Record<string, unknown>): Promise<{ failed?: Record<string, string> }> {
	const answer = await request<{ failed?: Record<string, string> } | { job: string }>('POST', path, body);

	if (!('job' in answer)) {
		return answer;
	}

	const job = await finish(answer.job, (update) => {
		progress.value = update.progress;
	});

	return job.result as { failed?: Record<string, string> };
}

onMounted(() => {
	void load();
});
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'health' }"><AdminIcon name="chevron-left" />{{ t('health_view.site_health', 'Site Health') }}</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ screen.title }}</h1>
			<p class="page-header__hint">{{ loading && health ? t('health_view.checking', 'Checking…') : (health ? t('health_view.last_checked', 'Last checked {when}', { when: formatWhen(health.at).toLowerCase() }) : screen.about) }}</p>
		</div>
		<div class="page-header__actions">
			<button type="button" class="button" :disabled="loading || fixing !== null" @click="load(true)">
				<span v-if="loading" class="spin" aria-hidden="true" /><AdminIcon v-else name="refresh-cw" />{{ loading ? (checking !== null ? t('health_view.checking_percent', 'Checking… {percent}%', { percent: checking }) : t('health_view.checking', 'Checking…')) : t('health_view.check_again', 'Check Again') }}
			</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else-if="!health" class="loading">{{ t('health_view.loading', 'Loading the last check…') }}</p>

	<template v-else>
		<nav class="status-tabs" :aria-label="t('health_view.problems', 'Problems')">
			<RouterLink v-for="item in tabs" :key="item.key" class="status-tabs__tab" :to="{ query: item.key === 'need' ? {} : { tab: item.key } }" :aria-current="tab === item.key ? 'page' : undefined">
				{{ item.label }}
				<span class="status-tabs__count">{{ formatNumber(item.count) }}</span>
			</RouterLink>
		</nav>

		<div v-if="groups.length" class="toolbar" role="search">
			<label class="search-field toolbar__search">
				<AdminIcon name="search" />
				<span class="visually-hidden">{{ t('health_view.search_label', 'Search titles and files') }}</span>
				<input ref="searchField" v-model="query" type="search" :placeholder="t('health_view.search_label', 'Search titles and files')" autocomplete="off" aria-keyshortcuts="/">
				<kbd class="search-field__key" aria-hidden="true">/</kbd>
			</label>
			<div v-if="tab === 'need' && twoKinds" class="toolbar__filter">
				<label class="visually-hidden" for="health-severity">{{ t('health_view.severity', 'Severity') }}</label>
				<AdminSelect id="health-severity" v-model="sevValue" :options="sevOptions" />
			</div>
			<div v-if="screen.byFile" class="toolbar__filter">
				<label class="visually-hidden" for="health-group">{{ t('health_view.group_by', 'Group by') }}</label>
				<AdminSelect id="health-group" v-model="modeValue" :options="modeOptions" />
			</div>
			<DensityToggle />
		</div>

		<div v-if="!visibleCount" class="panel">
			<EmptyState v-if="query.trim()" icon="search" :heading="t('health_view.no_match', 'Nothing Matches “{query}”', { query: query.trim() })" :text="t('health_view.no_match_text', 'The search looks at titles, files, and what was found.')">
				<template #actions>
					<button type="button" class="button" @click="query = ''">{{ t('health_view.clear_search', 'Clear the Search') }}</button>
				</template>
			</EmptyState>
			<EmptyState v-else-if="tab === 'need'" icon="circle-check" :heading="t('health_view.nothing_needs_fixing', 'Nothing Needs Fixing')" :text="tally.notice ? t('health_view.clear_with_notices', '{clear} {count, plural, one {# notice is} other {# notices are} } in their own tab; none of them change what the site shows.', { clear: screen.clear, count: tally.notice }) : screen.clear" />
			<EmptyState v-else-if="tab === 'ignored'" icon="eye-off" :heading="t('health_view.nothing_ignored', 'Nothing Ignored')" :text="t('health_view.nothing_ignored_text', 'Ignore a warning or notice from its row\'s menu. Ignored problems stop counting, for everyone.')" />
			<EmptyState v-else icon="info" :heading="t('health_view.no_notices', 'No Notices')" />
		</div>

		<template v-else-if="mode === 'file' && screen.byFile">
<section v-for="file in files.slice(0, filesShown)" :key="file.path" class="panel" :class="{ 'panel--compact': compact }" :aria-label="file.title ?? file.path">
				<header class="panel__header">
					<div class="panel__header-text">
						<h2>{{ file.title ?? t('health_view.unreadable_title', 'The Title Can\'t Be Read') }}</h2>
						<p class="panel__hint mono">{{ file.path }}</p>
					</div>
					<div class="panel__actions">
						<span class="panel__hint">{{ t('health_view.problem_count', '{count, plural, one {# problem} other {# problems} }', { count: file.rows.length }) }}</span>
						<RouterLink v-if="file.entry" class="button button--small" :to="entryRoute(file.entry)"><AdminIcon name="file-pen-line" />{{ t('health_view.open_in_editor', 'Open in Editor') }}</RouterLink>
					</div>
				</header>
				<ul class="problems">
					<HealthRow
						v-for="{ row, group } in file.rows"
						:key="row.key"
						:row="row"
						:group="group.name"
						:fixed="fixed[row.key]?.done"
						:busy="fixing !== null"
						:fixing="fixing === row.key"
						:ignored="ignoredOf(row)"
						@fix="(choice) => fix(group, [row], { choice })"
						@ignore="setIgnored(row, true)"
						@unignore="setIgnored(row, false)"
					/>
				</ul>
			</section>
			<p v-if="files.length > filesShown" class="panel panel__foot">
				<span>{{ t('health_view.showing_files', 'Showing {shown} of {total} files', { shown: filesShown, total: files.length }) }}</span>
				<button type="button" class="lnk" @click="filesShown += MORE">{{ t('health_view.show_more', 'Show {count} More', { count: Math.min(MORE, files.length - filesShown) }) }}</button>
			</p>
		</template>

		<template v-else>
			<section v-for="{ group, rows } in visible" :key="group.key" class="panel" :class="{ 'panel--compact': compact }" :aria-labelledby="`group-${group.key}`">
				<header class="panel__header">
					<div class="panel__header-text">
						<h2 :id="`group-${group.key}`">{{ group.name }}</h2>
						<p class="panel__hint">{{ group.says }}</p>
					</div>
					<div class="panel__actions">
						<span class="panel__hint">{{ openCount(rows) ? group.unit(openCount(rows)) : t('health_view.all_fixed', 'All fixed') }}</span>
						<span class="pill" :class="PILLS[group.severity].kind">{{ PILLS[group.severity].label }}</span>
						<button v-if="tab !== 'ignored' && group.bulk && bulkRows(rows).length > 1" type="button" class="button button--small button--primary" :disabled="fixing !== null" @click="fix(group, bulkRows(rows), { bulk: true })">
							<span v-if="fixing === group.key" class="spin" aria-hidden="true" />{{ fixing === group.key && progress !== null && progress < 100 ? t('health_view.working_percent', 'Working… {percent}%', { percent: progress }) : group.bulk.label(bulkRows(rows).length) }}
						</button>
					</div>
				</header>
				<ul class="problems">
					<HealthRow
						v-for="row in rows.slice(0, shown[group.key] ?? PER_GROUP)"
						:key="row.key"
						:row="row"
						:fixed="fixed[row.key]?.done"
						:busy="fixing !== null"
						:fixing="fixing === row.key"
						:ignored="ignoredOf(row)"
						@fix="(choice) => fix(group, [row], { choice })"
						@ignore="setIgnored(row, true)"
						@unignore="setIgnored(row, false)"
					/>
				</ul>
				<p v-if="rows.length > (shown[group.key] ?? PER_GROUP)" class="panel__foot">
					<span>{{ t('health_view.showing', 'Showing {shown} of {total}', { shown: shown[group.key] ?? PER_GROUP, total: rows.length }) }}</span>
					<button type="button" class="lnk" @click="shown = { ...shown, [group.key]: (shown[group.key] ?? PER_GROUP) + MORE }">{{ t('health_view.show_more', 'Show {count} More', { count: Math.min(MORE, rows.length - (shown[group.key] ?? PER_GROUP)) }) }}</button>
				</p>
			</section>
		</template>
	</template>
</template>
