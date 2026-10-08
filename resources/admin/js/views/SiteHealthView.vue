<script setup lang="ts">
/**
 * Site Health (D-543, from the Home sketch): one answer to "is anything
 * wrong?", in place of Content Health. **Checks** has three figures, what
 * needs a look down the wide column (failing first, each leading to where
 * it's fixed when there's somewhere), and the areas checked down the
 * side. Content and media lead to their details (`HealthView`), which
 * hold the fixes. The rest are `doctor`'s checks, as the web server's
 * PHP sees them. **Requirements** compares what Blush needs with what
 * this host has, and **Site & Server** lists facts for a bug report;
 * Copy Report, on both, copies them all. It shows the last report (D-545), checking first only when
 * there's none, and checks again when asked.
 */

import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import { useAction } from '../action';
import { loadCounts, navCounts } from '../counts';
import { formatWhen, plural } from '../format';
import { finish } from '../jobs';
import { can, session } from '../session';
import { copyText, toast } from '../toast';
import { request, type HealthArea, type HealthCheck, type HealthFact, type HealthRequirement, type SiteHealth } from '../api';
import type { IconName } from '../icons';

type Tab = 'checks' | 'requirements' | 'info';

const route = useRoute();
const TABS: { key: Tab; label: string }[] = [
	{ key: 'checks', label: 'Checks' },
	{ key: 'requirements', label: 'Requirements' },
	{ key: 'info', label: 'Site & Server' }
];
const tab = computed<Tab>(() => TABS.find((item) => item.key === route.query.tab)?.key ?? 'checks');

const health = ref<SiteHealth | null>(null);

const { busy: loading, error, run } = useAction();

// How far along checking the files is, when it runs as a job (D-625).
const checking = ref<number | null>(null);

// The last report, or checking again (D-545); the panel's count follows.
// Checking again answers at once but for the content and media files,
// which a job reads a chunk at a time (D-625); once it's done, the
// report is loaded again.
async function check(again = false): Promise<void> {
	checking.value = null;

	await run('Site health couldn\'t be checked.', async () => {
		const answer = await request<SiteHealth & { job?: string }>(again ? 'POST' : 'GET', '/health/site');

		health.value = answer;

		if (answer.job !== undefined) {
			await finish(answer.job, (job) => {
				checking.value = job.progress;
			});

			health.value   = await request<SiteHealth>('GET', '/health/site');
			checking.value = null;
		}

		if (again) {
			toast(`Ran ${plural(health.value.checks.length, 'check')}`);
		}

		void loadCounts();
	});
}

const ICONS: Record<HealthArea, IconName> = { content: 'files', media: 'image', extensions: 'plug', system: 'settings', accounts: 'users' };
const ORDER: Record<HealthCheck['status'], number> = { failure: 0, warning: 1, pass: 2 };

const checks   = computed(() => health.value?.checks ?? []);
const failing  = computed(() => checks.value.filter((item) => item.status === 'failure').length);
const warnings = computed(() => checks.value.filter((item) => item.status === 'warning').length);
const passing  = computed(() => checks.value.filter((item) => item.status === 'pass').length);
const needy    = computed(() => failing.value + warnings.value);
const issues   = computed(() => checks.value.filter((item) => item.status !== 'pass').sort((a, b) => ORDER[a.status] - ORDER[b.status]));

const areaLabel = (key: HealthArea): string => health.value?.areas.find((area) => area.key === key)?.label ?? key;

// What an area's checks found: how many need a look, and whether any fail.
function areaState(key: HealthArea): { issues: number; failing: boolean } {
	const inArea = checks.value.filter((item) => item.area === key && item.status !== 'pass');

	return { issues: inArea.length, failing: inArea.some((item) => item.status === 'failure') };
}

// Where a check leads, or nowhere: a chevron is a promise.
function linkOf(item: HealthCheck): object | null {
	switch (item.link) {
		case 'content':
		case 'media':
			// Each issue has its own screen (D-546).
			return { name: item.link === 'media' ? 'health-media-check' : 'health-check', params: { area: item.link, check: item.key } };
		case 'themes':
		case 'plugins':
		case 'icon-packs':
			return can(`extensions.${item.link}.view`) ? { name: item.link } : null;
		case 'account':
			return { name: 'account', params: { username: session.account?.username ?? '' } };
		default:
			return null;
	}
}

const PILLS: Record<HealthCheck['status'] | HealthRequirement['status'], { label: string; kind: string }> = {
	failure: { label: 'Fail', kind: 'pill--danger' },
	warning: { label: 'Check', kind: 'pill--warn' },
	pass: { label: 'Pass', kind: 'pill--good' },
	optional: { label: 'Not loaded', kind: '' }
};

// The requirements in their groups.
const groups = computed(() => {
	const list: { name: string; rows: HealthRequirement[] }[] = [];

	for (const row of health.value?.requirements ?? []) {
		const last = list[list.length - 1];

		if (last?.name === row.group) {
			last.rows.push(row);
		} else {
			list.push({ name: row.group, rows: [row] });
		}
	}

	return list;
});

const unmet = computed(() => (health.value?.requirements ?? []).filter((row) => row.status === 'warning' || row.status === 'failure').length);

/**
 * Copies the requirements and facts as text, for a bug report.
 */
function copyReport(): void {
	if (!health.value) {
		return;
	}

	// As PHP and the config have them, not as they're shown (D-615).
	const facts = (rows: HealthFact[]): string[] => rows.map((row) => `${row.key}: ${row.raw}`);
	const lines = [
		'Site report',
		'',
		...facts(health.value.site),
		'',
		...facts(health.value.server),
		'',
		'Requirements',
		...health.value.requirements.map((row) => `  [${row.status === 'pass' ? 'ok' : (row.status === 'optional' ? '--' : '!!')}] ${row.name}: needs ${row.needs.toLowerCase()}, has ${row.installed.toLowerCase()}`)
	];

	void copyText(lines.join('\n'), 'the report');
}

const toolsShown = computed(() => (navCounts.value?.actions ?? 0) > 0 || can('site.logs'));

onMounted(() => {
	void check();
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Site Health</h1>
			<p class="page-header__hint">{{ health ? `Last checked ${formatWhen(health.checked).toLowerCase()}` : 'Checking…' }}</p>
		</div>
		<div class="page-header__actions">
			<button v-if="tab === 'checks'" type="button" class="button button--primary" :disabled="loading" @click="check(true)">
				<span v-if="loading" class="spin" aria-hidden="true" /><AdminIcon v-else name="refresh-cw" />{{ loading ? (checking !== null ? `Checking… ${checking}%` : 'Checking…') : 'Run a Check' }}
			</button>
			<button v-else type="button" class="button" :disabled="!health" @click="copyReport"><AdminIcon name="copy" />Copy Report</button>
		</div>
	</header>

	<nav class="status-tabs" aria-label="Site Health">
		<RouterLink v-for="item in TABS" :key="item.key" class="status-tabs__tab" :to="{ query: item.key === 'checks' ? {} : { tab: item.key } }" :aria-current="tab === item.key ? 'page' : undefined">{{ item.label }}</RouterLink>
	</nav>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else-if="!health" class="loading">Checking the site…</p>

	<template v-else-if="tab === 'checks'">
		<dl class="stats stats--three">
			<div class="stat" :class="{ 'stat--danger': failing, 'stat--warn': !failing && needy }">
				<dt class="stat__label"><AdminIcon name="triangle-alert" />Needs Attention</dt>
				<dd class="stat__value">{{ needy }}</dd>
				<dd class="stat__context">{{ needy ? [failing ? `${failing} failing` : '', warnings ? `${warnings} worth a look` : ''].filter(Boolean).join(', ') : 'Nothing is wrong right now' }}</dd>
			</div>
			<div class="stat">
				<dt class="stat__label"><AdminIcon name="circle-check" />Passing</dt>
				<dd class="stat__value">{{ passing }}</dd>
				<dd class="stat__context">Nothing to do in those</dd>
			</div>
			<div class="stat">
				<dt class="stat__label"><AdminIcon name="list-checks" />Checks Run</dt>
				<dd class="stat__value">{{ checks.length }}</dd>
				<dd class="stat__context">Across {{ plural(health.areas.length, 'area') }}</dd>
			</div>
		</dl>

		<div class="columns">
			<div class="columns__col">
				<section class="panel" aria-labelledby="issues-heading">
					<header class="panel__header">
						<h2 id="issues-heading">Issues</h2>
						<p v-if="issues.length" class="panel__hint">{{ failing ? 'Failing first' : 'Worst first' }}</p>
					</header>
					<ul v-if="issues.length" class="rows">
						<li v-for="item in issues" :key="`${item.area}:${item.key}`">
							<component :is="linkOf(item) ? RouterLink : 'div'" :class="linkOf(item) ? 'rows__link' : 'rows__item'" v-bind="linkOf(item) ? { to: linkOf(item) } : {}">
								<span class="rows__main">
									<span class="rows__title">{{ item.label }}</span>
									<span class="rows__text">{{ item.message }}</span>
									<span class="rows__sub">{{ areaLabel(item.area) }}{{ item.hint ? ` · ${item.hint}` : '' }}</span>
								</span>
								<span class="rows__side">
									<span class="pill" :class="PILLS[item.status].kind">{{ PILLS[item.status].label }}</span>
									<AdminIcon name="chevron-right" class="rows__chevron" :class="{ 'is-blank': !linkOf(item) }" />
								</span>
							</component>
						</li>
					</ul>
					<p v-else class="panel__body muted">Every check passed. Nothing needs you here.</p>
				</section>
			</div>
			<div class="columns__col">
				<section class="panel" aria-labelledby="areas-heading">
					<header class="panel__header"><h2 id="areas-heading">Areas</h2></header>
					<ul class="rows">
						<li v-for="area in health.areas" :key="area.key">
							<div class="rows__item">
								<AdminIcon :name="ICONS[area.key]" class="rows__icon" />
								<span class="rows__main">
									<span class="rows__title">{{ area.label }}</span>
									<span class="rows__sub">{{ area.description }}</span>
								</span>
								<span v-if="areaState(area.key).issues" class="pill" :class="areaState(area.key).failing ? 'pill--danger' : 'pill--warn'">{{ plural(areaState(area.key).issues, 'issue') }}</span>
								<span v-else class="pill pill--good">Clear</span>
							</div>
						</li>
					</ul>
					<p class="panel__foot">
						<span>The same checks <code>doctor</code> runs.</span>
						<RouterLink v-if="toolsShown" class="lnk" :to="{ name: 'tools' }">Open Tools<AdminIcon name="arrow-right" /></RouterLink>
					</p>
				</section>
			</div>
		</div>
	</template>

	<section v-else-if="tab === 'requirements'" class="panel" aria-labelledby="requirements-heading">
		<header class="panel__header">
			<h2 id="requirements-heading">Requirements</h2>
			<p class="panel__hint">{{ unmet ? `${plural(health.requirements.length, 'requirement')} · ${unmet} worth a look` : `All ${health.requirements.length} met` }}</p>
		</header>
		<div class="table-wrap">
			<table class="table" aria-labelledby="requirements-heading">
				<colgroup><col><col class="site-health__needs"><col class="site-health__installed"><col class="site-health__status"></colgroup>
				<thead>
					<tr><th scope="col">Requirement</th><th scope="col">Needs</th><th scope="col">Installed</th><th scope="col">Status</th></tr>
				</thead>
				<tbody v-for="group in groups" :key="group.name">
					<tr class="table__group"><th scope="rowgroup" colspan="4">{{ group.name }}</th></tr>
					<tr v-for="row in group.rows" :key="row.name">
						<th scope="row">
							{{ row.name }}
							<span v-if="row.why" class="site-health__why">{{ row.why }}</span>
						</th>
						<td class="site-health__value">{{ row.needs }}</td>
						<td class="site-health__value">{{ row.installed }}</td>
						<td><span class="pill" :class="PILLS[row.status].kind">{{ PILLS[row.status].label }}</span></td>
					</tr>
				</tbody>
			</table>
		</div>
	</section>

	<div v-else class="columns columns--even">
		<section class="panel" aria-labelledby="site-heading">
			<header class="panel__header">
				<h2 id="site-heading">This Site</h2>
				<p class="panel__hint">As Blush reports it</p>
			</header>
			<dl class="fact-rows fact-rows--panel">
				<div v-for="fact in health.site" :key="fact.key">
					<dt>{{ fact.label }}<span v-if="fact.note" class="site-health__why mono">{{ fact.note }}</span></dt>
					<dd :class="{ mono: fact.mono }">{{ fact.value }}</dd>
				</div>
			</dl>
		</section>
		<section class="panel" aria-labelledby="server-heading">
			<header class="panel__header">
				<h2 id="server-heading">The Server</h2>
				<p class="panel__hint">What PHP sees from here</p>
			</header>
			<dl class="fact-rows fact-rows--panel">
				<div v-for="fact in health.server" :key="fact.key">
					<dt>{{ fact.label }}<span v-if="fact.note" class="site-health__why mono">{{ fact.note }}</span></dt>
					<dd :class="{ mono: fact.mono }">{{ fact.value }}</dd>
				</div>
			</dl>
		</section>
	</div>
</template>

<style scoped>

.site-health__needs {
	width: 200px;
}

.site-health__installed {
	width: 200px;
}

.site-health__status {
	width: 104px;
}

.site-health__value {
	color: var(--fg-2);
	font-family: var(--font-mono);
	font-size: var(--text-xs);
}

.site-health__why {
	display: block;
	color: var(--fg-3);
	font-size: var(--text-sm);
	overflow-wrap: anywhere;
}

tbody th[scope="row"] {
	text-align: left;
}

</style>
