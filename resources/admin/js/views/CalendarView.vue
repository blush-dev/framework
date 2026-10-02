<script setup lang="ts">
/**
 * The calendar (D-368): a month of the entries the account may edit that
 * have a published date, each on its day in the site's timezone, so what
 * went out and what's queued read together. A draft with a date is on
 * its day too. Pages and collections only: terms and profiles have dates,
 * but they aren't published on a day the way an entry is. Weeks start on Monday, as the date picker's do; today is
 * ringed. An entry opens the editor. Status is an icon and words, never
 * color alone.
 *
 * The month, type, and status are in the address (`?month=2026-10`), so
 * a month can be linked to and Back returns to it. On a narrow screen
 * the grid becomes a list of the days that have entries.
 *
 * Read-only (D-368): an entry's date is changed in the editor.
 */

import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ApiError, entryRoute, request, type CalendarEntry, type CalendarMonth, type EntryStatus } from '../api';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect, { type SelectOption } from '../components/AdminSelect.vue';
import { plural } from '../format';
import type { IconName } from '../icons';
import { canType } from '../session';
import { findType, loadTypes, types } from '../types';

const route  = useRoute();
const router = useRouter();

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const MONTH    = /^(\d{4})-(0[1-9]|1[0-2])$/;

const STATUSES: { key: EntryStatus | 'any'; label: string }[] = [
	{ key: 'any', label: 'All' },
	{ key: 'published', label: 'Published' },
	{ key: 'scheduled', label: 'Scheduled' },
	{ key: 'draft', label: 'Drafts' }
];

const MARKS: Record<EntryStatus, { icon: IconName; label: string }> = {
	published: { icon: 'circle-check', label: 'Published' },
	scheduled: { icon: 'clock', label: 'Scheduled' },
	draft: { icon: 'file-pen-line', label: 'Draft' }
};

const calendar = ref<CalendarMonth | null>(null);
const loading  = ref(false);
const error    = ref('');

void loadTypes().catch(() => undefined);

function queryText(name: string): string {
	const value = route.query[name];

	return typeof value === 'string' ? value : '';
}

// What the address asks for; the month is the site's current one until
// the first answer says which that is.
const asked  = computed(() => MONTH.test(queryText('month')) ? queryText('month') : '');
const month  = computed(() => asked.value || calendar.value?.month || '');
const status = computed<EntryStatus | 'any'>(() => STATUSES.find((item) => item.key === queryText('status'))?.key ?? 'any');
const type   = computed(() => queryText('type'));

const typeOptions = computed<SelectOption[]>(() => [
	{ value: '', label: 'All types' },
	...types.value
		.filter((item) => (item.kind === 'tree' || item.kind === 'collection') && canType(item.name, 'edit'))
		.sort((a, b) => a.labels.menu.localeCompare(b.labels.menu))
		.map((item) => ({ value: item.name, label: item.labels.menu }))
]);

function update(changes: Record<string, string>): void {
	const query = { ...route.query, ...changes };

	for (const [key, value] of Object.entries(query)) {
		if (value === '' || (key === 'status' && value === 'any')) {
			delete query[key];
		}
	}

	void router.replace({ query });
}

const typeValue = computed({
	get: () => type.value,
	set: (value: string) => update({ type: value })
});

// The month `offset` months from the one shown.
function shift(offset: number): string {
	const match = MONTH.exec(month.value);

	if (match === null) {
		return '';
	}

	const date = new Date(Number(match[1]), Number(match[2]) - 1 + offset, 1);

	return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function go(offset: number): void {
	void router.push({ query: { ...route.query, month: shift(offset) } });
}

const thisMonth = computed(() => calendar.value?.today.slice(0, 7) ?? '');

function goToday(): void {
	const query = { ...route.query };

	delete query.month;
	void router.push({ query });
}

const heading = computed(() => {
	const match = MONTH.exec(month.value);

	return match === null ? 'Calendar' : new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(new Date(Number(match[1]), Number(match[2]) - 1, 1));
});

interface Day {
	key: string;
	number: number;
	weekday: string;
	label: string;
	out: boolean;
	today: boolean;
	entries: CalendarEntry[];
}

// Six weeks, or as few as the month needs, from the Monday on or before
// its first day; only the month's own days hold entries.
const days = computed<Day[]>(() => {
	const match = MONTH.exec(month.value);

	if (match === null) {
		return [];
	}

	const year    = Number(match[1]);
	const index   = Number(match[2]) - 1;
	const first   = new Date(year, index, 1);
	const lead    = (first.getDay() + 6) % 7;
	const length  = new Date(year, index + 1, 0).getDate();
	const weeks   = Math.ceil((lead + length) / 7);
	const today   = calendar.value?.today ?? '';
	const byDay   = new Map<number, CalendarEntry[]>();
	const long    = new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric' });

	for (const entry of calendar.value?.month === month.value ? calendar.value.entries : []) {
		byDay.set(entry.day, [...(byDay.get(entry.day) ?? []), entry]);
	}

	return Array.from({ length: weeks * 7 }, (_, offset) => {
		const date = new Date(year, index, 1 - lead + offset);
		const out  = date.getMonth() !== index;
		const iso  = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

		return {
			key: iso,
			number: date.getDate(),
			weekday: WEEKDAYS[offset % 7] ?? '',
			label: long.format(date),
			out,
			today: iso === today,
			entries: out ? [] : (byDay.get(date.getDate()) ?? [])
		};
	});
});

const dated = computed(() => days.value.filter((day) => day.entries.length > 0).length);

// "2:30 pm", from the site's `HH:MM`.
function time(value: string): string {
	const [hours = 0, minutes = 0] = value.split(':').map(Number);

	return `${((hours + 11) % 12) + 1}:${String(minutes).padStart(2, '0')} ${hours < 12 ? 'am' : 'pm'}`;
}

function typeLabel(name: string): string {
	return findType(name)?.labels.singular ?? name;
}

const summary = computed(() => {
	const answer = calendar.value;

	if (answer === null || answer.month !== month.value) {
		return loading.value ? 'Loading…' : '';
	}

	if (answer.total === 0) {
		return `Nothing dated in ${heading.value}`;
	}

	const shown = answer.entries.length < answer.total ? `, showing the first ${answer.entries.length.toLocaleString()}` : '';

	return `${plural(answer.total, 'entry', 'entries')} in ${heading.value}${shown}`;
});

let asking = 0;

async function load(): Promise<void> {
	const ask    = ++asking;
	const params = new URLSearchParams();

	if (asked.value !== '') {
		params.set('month', asked.value);
	}

	if (status.value !== 'any') {
		params.set('status', status.value);
	}

	if (type.value !== '') {
		params.set('type', type.value);
	}

	loading.value = true;
	error.value   = '';

	try {
		const answer = await request<CalendarMonth>('GET', `/calendar${params.size > 0 ? `?${params.toString()}` : ''}`);

		if (ask === asking) {
			calendar.value = answer;
		}
	} catch (caught) {
		if (ask === asking) {
			error.value = caught instanceof ApiError ? caught.message : 'The calendar couldn\'t be loaded.';
		}
	} finally {
		if (ask === asking) {
			loading.value = false;
		}
	}
}

watch(() => [asked.value, status.value, type.value], load, { immediate: true });
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Calendar</h1>
			<p class="page-header__hint">Entries on the day they're published, in the site's time</p>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<section class="panel" aria-labelledby="calendar-month" :aria-busy="loading">
		<header class="panel__header calendar-bar">
			<div class="calendar-nav">
				<button type="button" class="button button--icon button--small" :disabled="!month" aria-label="Previous month" title="Previous month" @click="go(-1)"><AdminIcon name="chevron-left" /></button>
				<button type="button" class="button button--icon button--small" :disabled="!month" aria-label="Next month" title="Next month" @click="go(1)"><AdminIcon name="chevron-right" /></button>
				<h2 id="calendar-month" class="calendar-month">{{ heading }}</h2>
				<button v-if="thisMonth && month !== thisMonth" type="button" class="button button--small" @click="goToday">Today</button>
			</div>
			<div class="calendar-filters">
				<div class="segmented" role="group" aria-label="Status">
					<button v-for="item in STATUSES" :key="item.key" type="button" :aria-pressed="status === item.key" @click="update({ status: item.key })">{{ item.label }}</button>
				</div>
				<div class="calendar-type">
					<label class="visually-hidden" for="calendar-type">Content type</label>
					<AdminSelect id="calendar-type" v-model="typeValue" :options="typeOptions" />
				</div>
			</div>
			<p class="panel__hint calendar-summary" aria-live="polite">{{ summary }}</p>
		</header>

		<div class="calendar">
			<div class="calendar__week" aria-hidden="true">
				<span v-for="weekday in WEEKDAYS" :key="weekday">{{ weekday }}</span>
			</div>
			<ol class="calendar__days">
				<li
					v-for="day in days"
					:key="day.key"
					class="calendar__day"
					:class="{ 'is-out': day.out, 'is-today': day.today, 'is-empty': !day.entries.length }"
					:aria-hidden="day.out ? 'true' : undefined"
				>
					<p class="calendar__date">
						<span class="visually-hidden">{{ day.label }}{{ day.today ? ' (today)' : '' }}</span>
						<span class="calendar__weekday" aria-hidden="true">{{ day.weekday }}</span>
						<span class="calendar__number" aria-hidden="true">{{ day.number }}</span>
					</p>
					<ul v-if="day.entries.length" class="calendar__entries">
						<li v-for="entry in day.entries" :key="entry.id">
							<RouterLink
								class="calendar__entry"
								:class="`is-${entry.status}`"
								:to="entryRoute(entry)"
								:title="`${entry.title || 'Untitled'} · ${typeLabel(entry.type)} · ${MARKS[entry.status].label} · ${time(entry.time)}`"
							>
								<AdminIcon :name="MARKS[entry.status].icon" />
								<span class="visually-hidden">{{ MARKS[entry.status].label }}, {{ typeLabel(entry.type) }}:</span>
								<span class="calendar__title" :class="{ untitled: !entry.title }">{{ entry.title || 'Untitled' }}</span>
								<span class="calendar__time">{{ time(entry.time) }}</span>
							</RouterLink>
						</li>
					</ul>
				</li>
			</ol>
			<div v-if="calendar && dated === 0 && !loading" class="empty calendar__empty">
				<AdminIcon name="calendar-days" />
				<p class="empty__heading">Nothing This Month</p>
				<p class="empty__text">No entries {{ status === 'any' ? '' : `${STATUSES.find((item) => item.key === status)?.label.toLowerCase()} ` }}are dated in {{ heading }}.</p>
			</div>
		</div>
	</section>
</template>

<style scoped>
.calendar-bar {
	flex-wrap: wrap;
	gap: var(--s-2) var(--s-3);
}

.calendar-nav,
.calendar-filters {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
}

.calendar-filters {
	margin-left: auto;
}

.calendar-month {
	margin: 0 var(--s-1);
}

.calendar-summary {
	flex-basis: 100%;
}

/* The panel's last row of days keeps to its rounded corners. */
.calendar {
	position: relative;
	overflow: clip;
	border-radius: 0 0 var(--r-3) var(--r-3);
}

.calendar__week,
.calendar__days {
	display: grid;
	grid-template-columns: repeat(7, minmax(0, 1fr));
}

.calendar__week {
	border-bottom: 1px solid var(--border);
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.calendar__week span {
	padding: var(--s-2) var(--s-3);
}

.calendar__days {
	margin: 0;
	padding: 0;
	list-style: none;
}

.calendar__day {
	display: flex;
	flex-direction: column;
	gap: var(--s-1);
	min-width: 0;
	min-height: 7.5rem;
	padding: var(--s-2);
	border-bottom: 1px solid var(--border);
}

.calendar__day:not(:nth-child(7n)) {
	border-right: 1px solid var(--border);
}

.calendar__day:nth-last-child(-n + 7) {
	border-bottom: 0;
}

.calendar__day.is-out {
	background: var(--surface-2);
}

.calendar__date {
	display: flex;
	margin: 0;
}

.calendar__weekday {
	display: none;
}

.calendar__number {
	display: inline-grid;
	place-items: center;
	min-width: 1.75rem;
	height: 1.75rem;
	padding: 0 4px;
	border-radius: 999px;
	color: var(--fg-2);
	font-size: var(--text-sm);
	font-variant-numeric: tabular-nums;
}

.is-out .calendar__number {
	color: var(--fg-3);
}

.is-today .calendar__number {
	box-shadow: inset 0 0 0 1px var(--border-strong);
	color: var(--fg);
	font-weight: 600;
}

.calendar__entries {
	display: grid;
	gap: 2px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.calendar__entry {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr);
	align-items: center;
	column-gap: 6px;
	padding: 3px 6px;
	border-radius: var(--r-1);
	color: var(--fg);
	font-size: var(--text-sm);
	line-height: 1.3;
	text-decoration: none;
}

.calendar__entry:hover {
	background: var(--surface-2);
}

.calendar__entry :deep(svg) {
	width: 14px;
	height: 14px;
}

.calendar__entry.is-published :deep(svg) {
	color: var(--good);
}

.calendar__entry.is-scheduled {
	background: var(--accent-soft);
}

.calendar__entry.is-scheduled :deep(svg) {
	color: var(--accent);
}

.calendar__entry.is-draft {
	color: var(--fg-2);
}

.calendar__entry.is-draft :deep(svg) {
	color: var(--fg-3);
}

.calendar__title {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.calendar__time {
	grid-column: 2;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-variant-numeric: tabular-nums;
}

.calendar__empty {
	position: absolute;
	inset: 50% auto auto 50%;
	translate: -50% -50%;
	padding: var(--s-4) var(--s-5);
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	box-shadow: var(--shadow-1);
}

/* A list of the days that have entries. */
@media (width <= 760px) {
	.calendar__week,
	.calendar__day.is-out,
	.calendar__day.is-empty {
		display: none;
	}

	.calendar__days {
		grid-template-columns: minmax(0, 1fr);
	}

	.calendar__day {
		display: grid;
		grid-template-columns: 3rem minmax(0, 1fr);
		align-items: start;
		min-height: 0;
		padding: var(--s-3) var(--pad-x);
		border-right: 0;
		border-bottom: 1px solid var(--border);
	}

	.calendar__day:not(:nth-child(7n)) {
		border-right: 0;
	}

	.calendar__day:last-child {
		border-bottom: 0;
	}

	.calendar__date {
		flex-direction: column;
		align-items: center;
	}

	.calendar__weekday {
		display: block;
		color: var(--fg-3);
		font-size: var(--text-xs);
	}

	.calendar__empty {
		position: static;
		translate: none;
		border: 0;
		box-shadow: none;
	}
}
</style>
