<script setup lang="ts">
/**
 * One problem a Site Health check found (D-612): which file (its entry's
 * title, then its path in mono), what was found and what the site does
 * because of it, and its fix, named for what it writes. Opening the
 * entry and copying the path are in its menu, or the row's own button
 * when there's no fix. A row with files to choose between (which keeps
 * a shared id) shows them under its chevron, the first chosen at first.
 *
 * A fixed row stays where it was, marked Fixed, saying what changed,
 * until the check runs again. A warning or notice can be ignored from
 * the menu, for everyone (D-613); an error can't, since the site is
 * already leaving something out. An ignored row says who ignored it and
 * when, and offers Stop Ignoring. Grouped by file, a row leads with its
 * severity and its group's name in place of the file, and leaves Open
 * in Editor to the file's heading.
 */

import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import MenuButton from './MenuButton.vue';
import { entryRoute } from '../api';
import { formatWhen } from '../format';
import type { HealthRow } from '../health';
import { copyText } from '../toast';

const props = defineProps<{
	row: HealthRow;
	// What the fix did, once the row is fixed.
	fixed?: string;
	// The group's name, when rows are grouped by file.
	group?: string;
	// Whether a fix is running, so none other starts.
	busy: boolean;
	// Whether this row's fix is the one running.
	fixing: boolean;
	// Who ignored it and when, once it's ignored.
	ignored?: { name: string; at: string };
}>();

const emit = defineEmits<{
	fix: [choice?: string];
	ignore: [];
	unignore: [];
}>();

// A path that names several files (a shared id's) isn't one to copy.
const copyable  = computed(() => !props.row.path.includes(', '));
const ignorable = computed(() => props.row.severity !== 'error');
const hasMenu   = computed(() => Boolean(props.row.entry && props.row.fix) || Boolean(props.row.media) || copyable.value || ignorable.value);

// Files to choose between show at once, the first chosen.
const open   = ref(props.row.choices !== undefined);
const choice = ref(props.row.choices?.[0]?.path ?? '');

const PILLS: Record<HealthRow['severity'], { label: string; kind: string }> = {
	error: { label: 'Error', kind: 'pill--danger' },
	warning: { label: 'Warning', kind: 'pill--warn' },
	notice: { label: 'Notice', kind: '' }
};

const mediaRoute = (key: string): object => ({ name: 'media-file', params: { path: key.split('/') } });
</script>

<template>
	<li class="problem" :class="{ 'problem--fixed': fixed !== undefined }">
		<div class="problem__lead">
			<template v-if="group">
				<span class="pill" :class="PILLS[row.severity].kind">{{ PILLS[row.severity].label }}</span>
				<span class="problem__title">{{ group }}</span>
			</template>
			<template v-else>
				<RouterLink v-if="row.entry && row.title !== null" class="problem__title" :to="entryRoute(row.entry)">{{ row.title }}</RouterLink>
				<RouterLink v-else-if="row.media" class="problem__title" :class="{ mono: row.mono }" :to="mediaRoute(row.media)">{{ row.title }}</RouterLink>
				<span v-else-if="row.title !== null" class="problem__title" :class="{ mono: row.mono }">{{ row.title }}</span>
				<span v-if="row.meta" class="problem__meta">{{ row.meta }}</span>
				<span v-if="!row.mono" class="problem__path">{{ row.path }}</span>
			</template>
		</div>

		<div class="problem__what">
			<template v-if="fixed !== undefined">
				<p class="problem__found">{{ fixed }}</p>
			</template>
			<template v-else>
				<p class="problem__found"><template v-if="row.field"><code>{{ row.field }}</code>{{ ' ' }}</template>{{ row.found }}</p>
				<p v-if="row.says" class="problem__says">{{ row.says }}</p>
				<p v-if="ignored" class="problem__meta">Ignored by {{ ignored.name }}, {{ formatWhen(ignored.at).toLowerCase() }}</p>
			</template>
		</div>

		<div class="problem__actions">
			<span v-if="fixed !== undefined" class="pill pill--good">Fixed</span>
			<button v-else-if="ignored" type="button" class="button button--small" :disabled="busy" @click="emit('unignore')"><AdminIcon name="eye" />Stop Ignoring</button>
			<template v-else>
				<button v-if="row.fix && !row.choices" type="button" class="button button--small" :disabled="busy" @click="emit('fix')">
					<span v-if="fixing" class="spin" aria-hidden="true" />{{ row.fix.label }}
				</button>
				<RouterLink v-else-if="row.entry && !row.fix && !group" class="button button--small" :to="entryRoute(row.entry)"><AdminIcon name="file-pen-line" />Open in Editor</RouterLink>
				<MenuButton v-if="hasMenu" button-class="button button--ghost button--small button--icon" :label="`More for ${row.title ?? row.path}`" floating>
					<template #button><AdminIcon name="ellipsis" /></template>
					<template #default="{ close }">
						<RouterLink v-if="row.entry && row.fix" class="menu-item" :to="entryRoute(row.entry)"><AdminIcon name="file-pen-line" />Open in Editor</RouterLink>
						<RouterLink v-if="row.media" class="menu-item" :to="mediaRoute(row.media)"><AdminIcon name="image" />Open Media File</RouterLink>
						<button v-if="copyable" type="button" class="menu-item" @click="close(); copyText(row.path, 'the file path')"><AdminIcon name="copy" />Copy File Path</button>
						<template v-if="ignorable">
							<hr v-if="row.entry && row.fix || row.media || copyable" class="menu-rule">
							<button type="button" class="menu-item" :disabled="busy" @click="close(); emit('ignore')"><AdminIcon name="eye-off" />Ignore This</button>
						</template>
					</template>
				</MenuButton>
				<button v-if="row.choices" type="button" class="button button--ghost button--small button--icon" :aria-expanded="open" :aria-label="open ? 'Hide the files' : 'Choose which file keeps it'" @click="open = !open">
					<AdminIcon :name="open ? 'chevron-up' : 'chevron-down'" />
				</button>
			</template>
		</div>

		<div v-if="row.choices && open && fixed === undefined && !ignored" class="problem__more">
			<p class="problem__label">Which Keeps It</p>
			<div class="pick-list">
				<button v-for="item in row.choices" :key="item.path" type="button" class="pick" :aria-pressed="choice === item.path" @click="choice = item.path">
					<span class="pick__text">
						<span class="pick__name">{{ item.title ?? item.path }}</span>
						<span v-if="item.title !== null" class="pick__meta">{{ item.path }}</span>
					</span>
					<span class="pick__aside">{{ choice === item.path ? 'Keeps the id' : 'Gets a new id' }}</span>
				</button>
			</div>
			<div>
				<button type="button" class="button button--small button--primary" :disabled="busy || !choice" @click="emit('fix', choice)">
					<span v-if="fixing" class="spin" aria-hidden="true" />{{ row.fix?.label }}
				</button>
			</div>
		</div>
	</li>
</template>
