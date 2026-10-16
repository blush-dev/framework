<script setup lang="ts">
/**
 * One change from the activity log (D-902), under Tools → Activity:
 * what it changed, its fields and body before and after (`DiffCompare`,
 * as the editor's conflict bar compares), or why that can't be shown;
 * its Details: which entry or file, who made it, how, and when, whether
 * it can be undone (and why not), and the change it undid or that
 * undid it, with no notices over the screen (D-905); and the other
 * changes to the same entry or file, each opening its own screen. Undo
 * is in the header.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import ActivityRows from '../components/ActivityRows.vue';
import AdminIcon from '../components/AdminIcon.vue';
import DiffCompare from '../components/DiffCompare.vue';
import { changedHow, loadActivity, loadChange, shownValue, undoChange, type ActivityChange, type ChangeDetail, type DiffState } from '../activity';
import { errorMessage } from '../api';
import { siteDateTime } from '../dates';
import { diffLines, hunks, type Differences } from '../diff';
import { t } from '../messages';
import { screenTitle } from '../screen';
import { can, session } from '../session';
import { toast } from '../toast';

const route = useRoute();
const id    = computed(() => String(route.params.id ?? ''));

const detail  = ref<ChangeDetail | null>(null);
const others  = ref<ActivityChange[] | null>(null);
const error   = ref('');
const undoing = ref(false);

const change = computed(() => detail.value?.change ?? null);

// The diff as the comparison draws it.
const differences = computed<Differences | null>(() => {
	const diff = detail.value?.diff;

	if (diff === undefined || diff.state !== 'shown') {
		return null;
	}

	return {
		rows: diff.fields.map((field) => ({ label: field.name, theirs: shownValue(field.before), mine: shownValue(field.after) })),
		body: diff.body === null ? null : hunks(diffLines(diff.body.before, diff.body.after))
	};
});

// Why a change's before and after aren't shown.
const UNSHOWN: Record<Exclude<DiffState, 'shown'>, string> = {
	new: t('activity_change_view.unshown.new', 'It made something new, so there\'s nothing it replaced to compare.'),
	expired: t('activity_change_view.unshown.expired', 'What it replaced is kept for 30 days, and that\'s passed.'),
	'not-kept': t('activity_change_view.unshown.not_kept', 'What it replaced wasn\'t kept, as for a move to a new address or language.'),
	unknown: t('activity_change_view.unshown.unknown', 'What it wrote can\'t be told apart from what came after: the file was changed outside the admin since, or it\'s gone.')
};

const unshown = computed(() => {
	const state = detail.value?.diff.state;

	return state === undefined || state === 'shown' ? null : UNSHOWN[state];
});

async function load(): Promise<void> {
	error.value  = '';
	others.value = null;

	try {
		detail.value = await loadChange(id.value);
	} catch (caught) {
		detail.value = null;
		error.value  = errorMessage(caught, t('activity_change_view.errors.load', 'The change couldn\'t be loaded.'));

		return;
	}

	try {
		others.value = (await loadActivity({ subject: detail.value.change.subject })).changes.filter((other) => other.id !== id.value);
	} catch {
		others.value = [];
	}
}

async function undo(): Promise<void> {
	const current = change.value;

	if (current === null) {
		return;
	}

	undoing.value = true;

	try {
		await undoChange(current.id);
		toast(t('activity_change_view.undid', 'Undid: {summary}', { summary: current.summary }));
	} catch (caught) {
		toast(errorMessage(caught, t('activity_change_view.errors.undo', 'The change couldn\'t be undone.')), { kind: 'warn' });
	} finally {
		undoing.value = false;
		await load();
	}
}

watch(id, () => {
	void load();
}, { immediate: true });

watch(change, (value) => {
	screenTitle.value = value?.summary ?? null;
});
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'activity' }"><AdminIcon name="chevron-left" />{{ t('activity_change_view.activity', 'Activity') }}</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ change?.summary ?? t('activity_change_view.title', 'Change') }}</h1>
			<p v-if="change && detail" class="page-header__hint">{{ detail.actionLabel }} · <time :datetime="change.time">{{ siteDateTime(change.time) }}</time></p>
		</div>
		<div v-if="change" class="page-header__actions">
			<RouterLink v-if="change.link" class="button" :to="change.link">{{ change.kind === 'entry' ? t('activity_change_view.open_entry', 'Open Entry') : t('activity_change_view.open_file', 'Open File') }}</RouterLink>
			<button v-if="!change.undone && change.undo.available" type="button" class="button button--primary" :disabled="undoing" @click="undo">
				<span v-if="undoing" class="spin" aria-hidden="true" />{{ undoing ? t('activity_change_view.undoing', 'Undoing…') : t('common.undo', 'Undo') }}
			</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else-if="!detail" class="loading">{{ t('activity_change_view.loading', 'Loading the change…') }}</p>

	<template v-else-if="change">
		<div class="columns">
			<div class="columns__col">
				<section class="panel" aria-labelledby="change-diff-heading">
					<header class="panel__header">
						<h2 id="change-diff-heading">{{ t('activity_change_view.what_changed', 'What Changed') }}</h2>
						<p v-if="detail.diff.moved" class="panel__hint">{{ t('activity_change_view.moved', 'Moved from {from} to {to}', { from: detail.diff.moved.from, to: detail.diff.moved.to }) }}</p>
					</header>
					<DiffCompare v-if="differences" :differences="differences" :labels="{ field: t('activity_change_view.field', 'Field'), before: t('activity_change_view.before', 'Before'), after: t('activity_change_view.after', 'After'), body: t('activity_change_view.body', 'Body'), legend: t('activity_change_view.legend', '{before_mark} before {after_mark} after'), beforeLine: t('activity_change_view.before_line', 'Before: '), afterLine: t('activity_change_view.after_line', 'After: '), same: t('activity_change_view.same', 'Nothing it changed differs from what it replaced.') }" />
					<p v-else class="panel__body muted">{{ unshown }}</p>
				</section>

				<section class="panel" aria-labelledby="change-others-heading">
					<header class="panel__header">
						<h2 id="change-others-heading">{{ change.kind === 'entry' ? t('activity_change_view.others_entry', 'Other Changes to This Entry') : t('activity_change_view.others_file', 'Other Changes to This File') }}</h2>
						<p class="panel__hint">{{ t('activity_change_view.newest_first', 'Newest first') }}</p>
					</header>
					<p v-if="others === null" class="panel__body loading">{{ t('common.loading', 'Loading…') }}</p>
					<p v-else-if="!others.length" class="panel__body muted">{{ t('activity_change_view.no_others', 'No other changes are listed.') }}</p>
					<ActivityRows v-else :changes="others" @changed="load" />
				</section>
			</div>

			<section class="panel" aria-labelledby="change-facts-heading">
				<header class="panel__header">
					<h2 id="change-facts-heading">{{ t('activity_change_view.details', 'Details') }}</h2>
				</header>
				<dl class="fact-rows fact-rows--panel">
					<div>
						<dt>{{ change.kind === 'entry' ? t('activity_change_view.entry', 'Entry') : t('activity_change_view.file', 'File') }}</dt>
						<dd><RouterLink v-if="change.link" class="lnk" :to="change.link">{{ change.title }}</RouterLink><template v-else>{{ change.title }}</template></dd>
					</div>
					<div>
						<dt>{{ t('activity_change_view.change', 'Change') }}</dt>
						<dd>{{ detail.actionLabel }}</dd>
					</div>
					<div>
						<dt>{{ t('activity_change_view.who', 'Who') }}</dt>
						<dd><RouterLink v-if="change.account.username && (can('accounts.view') || change.account.username === session.account?.username)" class="lnk" :to="{ name: 'account', params: { username: change.account.username } }">{{ change.account.name }}</RouterLink><template v-else>{{ change.account.name }}</template></dd>
					</div>
					<div>
						<dt>{{ t('activity_change_view.how', 'How') }}</dt>
						<dd><span class="tag">{{ changedHow(change) }}</span></dd>
					</div>
					<div>
						<dt>{{ t('activity_change_view.when', 'When') }}</dt>
						<dd><time :datetime="change.time">{{ siteDateTime(change.time) }}</time></dd>
					</div>
					<div>
						<dt>{{ t('activity_change_view.undo', 'Undo') }}</dt>
						<dd>
							<RouterLink v-if="detail.undoneBy" class="lnk" :to="{ name: 'activity-change', params: { id: detail.undoneBy } }">{{ t('activity_change_view.undone_by', 'Undone by a later change') }}</RouterLink>
							<template v-else-if="change.undo.available">{{ t('activity_change_view.can_undo', 'Can be undone') }}</template>
							<template v-else>{{ change.undo.reason }}</template>
						</dd>
					</div>
					<div v-if="detail.undoes">
						<dt>{{ t('activity_change_view.undid_label', 'Undid') }}</dt>
						<dd><RouterLink class="lnk" :to="{ name: 'activity-change', params: { id: detail.undoes } }">{{ t('activity_change_view.see_undone', 'An earlier change') }}</RouterLink></dd>
					</div>
				</dl>
			</section>
		</div>
	</template>
</template>
