<script setup lang="ts">
/**
 * Tools (D-540, from the Home sketch; the first screen of the Tools
 * section, D-900): a button for each tool that needs no input and is a
 * button (D-761), for anyone without shell access; they're described by
 * the server (`Blush\Tool\Tool` classes), so a plugin's show up here with
 * no JavaScript, grouped by where they come from. A tool the account may
 * not use isn't listed. A result stays in its row, since on a host with
 * no shell it's all there is, and is announced in a polite live region. A
 * tool that runs in the background (D-621) is followed to its end here,
 * with its progress.
 */

import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import { confirmAction } from '../confirm';
import { titleCase } from '../format';
import { t } from '../messages';
import { follow, jobResult } from '../jobs';
import { can } from '../session';
import { errorMessage, request, type ToolAnswer, type ToolButton, type ToolGroup, type ToolResult, type Job } from '../api';

const groups  = ref<ToolGroup[] | null>(null);
const error   = ref('');
const running = ref<string | null>(null);
const results = ref<Record<string, ToolResult>>({});
// The job each running tool queued, as it goes.
const following = ref<Record<string, Job>>({});

const SOURCES: Record<ToolGroup['source']['kind'], string> = {
	core: t('tools_view.sources.core', 'Registered by Blush'),
	site: t('tools_view.sources.site', 'Registered by this site'),
	plugin: t('tools_view.sources.plugin', 'Registered by a plugin'),
	other: t('tools_view.sources.other', 'Registered elsewhere')
};

async function load(): Promise<void> {
	try {
		groups.value = (await request<{ groups: ToolGroup[] }>('GET', '/tools')).groups;
	} catch (caught) {
		error.value = errorMessage(caught, t('tools_view.errors.tools', 'The tools couldn\'t be loaded.'));
	}
}

async function run(tool: ToolButton): Promise<void> {
	if (tool.confirm !== null && !await confirmAction({ title: t('tools_view.run_title', '{tool}?', { tool: tool.title }), body: tool.confirm, confirm: tool.title })) {
		return;
	}

	running.value = tool.key;

	try {
		const answer = await request<ToolAnswer>('POST', `/tools/${tool.key.split('/').map(encodeURIComponent).join('/')}`);

		results.value[tool.key] = 'job' in answer
			? jobResult(await follow(answer.job, (job) => {
				following.value[tool.key] = job;
			}))
			: answer;
	} catch (caught) {
		results.value[tool.key] = {
			successful: false,
			message: errorMessage(caught, t('tools_view.errors.tool', '{tool} failed.', { tool: tool.title })),
			details: []
		};
	} finally {
		running.value = null;
		delete following.value[tool.key];
	}
}

onMounted(() => {
	void load();
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ t('tools_view.title', 'Tools') }}</h1>
			<p class="page-header__hint">{{ t('tools_view.hint', 'Tasks you run by hand') }}</p>
		</div>
	</header>


	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else-if="!groups" class="loading">{{ t('tools_view.loading_tools', 'Loading the tools…') }}</p>
	<div v-else-if="!groups.length" class="panel">
		<EmptyState icon="wrench" :heading="t('tools_view.no_tools', 'No Tools for You')" :text="t('tools_view.no_tools_text', 'Your role can\'t use any of the tools on this site.')" />
	</div>
	<section v-for="(group, index) in groups ?? []" :key="`${group.source.kind}:${group.source.label}`" class="panel" :aria-labelledby="`tools-${index}`">
		<header class="panel__header">
			<h2 :id="`tools-${index}`">{{ group.source.kind === 'core' ? t('tools_view.core', 'Core') : group.source.label }}</h2>
			<p class="panel__hint">{{ SOURCES[group.source.kind] }}</p>
		</header>
		<ul class="tools-list">
			<li v-for="tool in group.tools" :key="tool.key" class="tool">
				<p class="tool__text">
					<span class="tool__name">{{ titleCase(tool.title) }}<span v-if="tool.confirm" class="tool__asks"> {{ t('tools_view.asks_first', '· asks first') }}</span></span>
					<span :id="`tool-${tool.key}`" class="tool__description">{{ tool.description }}</span>
				</p>
				<button type="button" class="button" :disabled="running !== null" :aria-describedby="`tool-${tool.key}`" @click="run(tool)">
					<span v-if="running === tool.key" class="spin" aria-hidden="true" />{{ running === tool.key ? t('tools_view.working', 'Working…') : titleCase(tool.title) }}
				</button>
				<div class="tool__result" aria-live="polite">
					<div v-if="following[tool.key]" class="tool__progress">
						<progress class="progress" max="100" :value="following[tool.key]?.progress ?? undefined" :aria-label="t('tools_view.progress', '{name}: how far along', { name: tool.title })" />
						<span class="tool__description">{{ following[tool.key]?.message || (following[tool.key]?.status === 'queued' ? t('tools_view.waiting', 'Waiting its turn…') : t('tools_view.working', 'Working…')) }}</span>
					</div>
					<div v-else-if="results[tool.key]" class="notice notice--small" :class="results[tool.key]?.successful ? 'notice--success' : 'notice--error'">
						<p>{{ results[tool.key]?.message }}</p>
						<ul v-if="results[tool.key]?.details.length">
							<li v-for="detail in results[tool.key]?.details" :key="detail">{{ detail }}</li>
						</ul>
					</div>
				</div>
			</li>
		</ul>
		<p v-if="index === (groups?.length ?? 0) - 1 && can('site.health')" class="panel__foot">
			<RouterLink class="lnk" :to="{ name: 'health' }">{{ t('tools_view.open_health', 'Open Site Health') }}<AdminIcon name="arrow-right" /></RouterLink>
		</p>
	</section>
</template>

<style scoped>
.tools-list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.tool {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	align-items: start;
	gap: 0 16px;
	padding: var(--padding-row) var(--padding-inline);
}

.tool + .tool {
	border-top: 1px solid var(--border-color);
}

.tool__text {
	display: grid;
	gap: 2px;
	margin: 0;
}

.tool__name {
	font-weight: 500;
}

.tool__asks {
	color: var(--color-text-muted);
	font-size: var(--font-size-small);
	font-weight: 400;
}

.tool__description {
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.tool__result {
	grid-column: 1 / -1;
}

.tool__result:not(:empty) {
	margin-top: 10px;
}

.tool .spin {
	margin-right: 6px;
}

.tool__progress {
	display: grid;
	gap: 6px;
}
</style>
