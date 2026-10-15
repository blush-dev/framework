<script setup lang="ts">
/**
 * A date and time as a value that opens a month (admin.md §8, The
 * document panel): a Monday-first grid with today ringed and the chosen
 * day tinted, soft rather than solid, then the time as two short fields
 * and an AM/PM switch on a 12-hour clock, never a native time input.
 * Hours read 1 to 12, so noon and midnight are 12. The model stays the
 * form's `YYYY-MM-DDTHH:MM` (empty for none); the 12-hour clock is only
 * how it's shown and typed.
 *
 * A `dayOnly` picker (a date field without a time, D-766) has no time:
 * its model is `YYYY-MM-DD`. A `clearable` one has **Clear** among its
 * quick choices, which empties it (an entry's Expires, D-852).
 */

import { computed, nextTick, ref } from 'vue';
import { hour12, monthDays, MONTHS, SHORT_MONTHS, WEEKDAYS } from '../month';
import { usePopover } from '../popover';
import AdminIcon from './AdminIcon.vue';
import { t } from '../messages';

const props = defineProps<{
	id: string;
	describedBy?: string;
	invalid?: boolean;
	dayOnly?: boolean;
	clearable?: boolean;
}>();

const model = defineModel<string>({ required: true });

function pad(value: number): string {
	return String(value).padStart(2, '0');
}

// The model as a date, or `null`.
const value = computed(() => {
	const match = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/.exec(model.value);

	return match === null ? null : new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), Number(match[4] ?? 0), Number(match[5] ?? 0));
});

function write(date: Date): void {
	const day = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

	model.value = props.dayOnly ? day : `${day}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const shown = computed(() => {
	const date = value.value;

	return date === null ? null : {
		day: t('date_picker.shown_day', '{weekday} {day} {month} {year}', { weekday: WEEKDAYS[(date.getDay() + 6) % 7], day: String(date.getDate()), month: SHORT_MONTHS[date.getMonth()], year: String(date.getFullYear()) }),
		time: date.getHours() < 12
			? t('date_picker.shown_time_am', '{hour}:{minute} am', { hour: pad(hour12(date.getHours())), minute: pad(date.getMinutes()) })
			: t('date_picker.shown_time_pm', '{hour}:{minute} pm', { hour: pad(hour12(date.getHours())), minute: pad(date.getMinutes()) })
	};
});

const button = ref<HTMLButtonElement | null>(null);
const panel  = ref<HTMLElement | null>(null);
const month  = ref(new Date());

const popover                = usePopover(button, panel, { gap: 6 });
const { open, place, layer, close } = popover;

function sameDay(a: Date, b: Date): boolean {
	return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

// Six weeks from the Monday on or before the month's first day.
const days = computed(() => {
	const today = new Date();

	return monthDays(month.value.getFullYear(), month.value.getMonth()).map((day) => ({
		...day,
		today: sameDay(day.date, today),
		chosen: value.value !== null && sameDay(day.date, value.value),
		label: t('date_picker.day_label', '{weekday} {day} {month}', { weekday: WEEKDAYS[(day.date.getDay() + 6) % 7], day: String(day.date.getDate()), month: MONTHS[day.date.getMonth()] })
	}));
});

// The time fields, as typed until they're committed.
const hourText   = ref('');
const minuteText = ref('');

function syncTime(): void {
	const date = value.value ?? new Date();

	hourText.value   = pad(hour12(date.getHours()));
	minuteText.value = pad(date.getMinutes());
}

const afternoon = computed(() => (value.value?.getHours() ?? 9) >= 12);

function base(): Date {
	if (value.value !== null) {
		return new Date(value.value);
	}

	const date = new Date();

	date.setHours(9, 0, 0, 0);

	return date;
}

function pickDay(date: Date): void {
	const next = base();

	next.setFullYear(date.getFullYear(), date.getMonth(), date.getDate());
	write(next);
}

function commitTime(): void {
	const next   = base();
	const hour   = Number.parseInt(hourText.value, 10);
	const minute = Number.parseInt(minuteText.value, 10);
	const pm     = next.getHours() >= 12;

	next.setHours(
		Number.isNaN(hour) || hour < 1 || hour > 12 ? next.getHours() : (hour % 12) + (pm ? 12 : 0),
		Number.isNaN(minute) || minute < 0 || minute > 59 ? next.getMinutes() : minute
	);
	write(next);
	syncTime();
}

function setMeridiem(pm: boolean): void {
	const next = base();

	next.setHours((next.getHours() % 12) + (pm ? 12 : 0));
	write(next);
}

function quick(which: 'now' | 'tomorrow' | 'monday'): void {
	const next = new Date();

	if (which === 'tomorrow') {
		next.setDate(next.getDate() + 1);
		next.setHours(9, 0, 0, 0);
	} else if (which === 'monday') {
		next.setDate(next.getDate() + (((8 - next.getDay()) % 7) || 7));
		next.setHours(9, 0, 0, 0);
	}

	month.value = new Date(next.getFullYear(), next.getMonth(), 1);
	write(next);
	syncTime();
}

function clear(): void {
	model.value = '';
	close();
}

function move(by: number): void {
	month.value = new Date(month.value.getFullYear(), month.value.getMonth() + by, 1);
}

async function show(): Promise<void> {
	const date = value.value ?? new Date();

	month.value = new Date(date.getFullYear(), date.getMonth(), 1);
	syncTime();
	await popover.show();
	await nextTick();
	panel.value?.querySelector<HTMLElement>('.date__day.is-chosen, .date__day.is-today')?.focus();
}

function keydown(event: KeyboardEvent): void {
	if (event.key === 'Escape') {
		event.preventDefault();
		event.stopPropagation();
		close();
	}
}

function timeKey(event: KeyboardEvent): void {
	if (event.key === 'Enter') {
		event.preventDefault();
		commitTime();
	}
}

defineExpose({ show });
</script>

<template>
	<button
		:id="props.id"
		ref="button"
		type="button"
		class="date"
		aria-haspopup="dialog"
		:aria-expanded="open"
		:aria-describedby="describedBy"
		:aria-invalid="invalid ? 'true' : undefined"
		@click="open ? close() : show()"
	>
		<template v-if="shown">
			<span class="date__day-text">{{ shown.day }}</span>
			<span v-if="!dayOnly" class="date__time-text">{{ shown.time }}</span>
		</template>
		<span v-else class="date__day-text">{{ t('date_picker.not_set', 'Not set') }}</span>
		<AdminIcon name="chevron-down" class="date__caret" />
	</button>

	<Teleport :to="layer">
		<div v-if="open" ref="panel" class="date-panel" role="dialog" :aria-label="dayOnly ? t('date_picker.choose_date', 'Choose a date') : t('date_picker.choose_date_time', 'Choose a date and time')" :style="place ?? { visibility: 'hidden' }" @keydown="keydown">
			<div class="date-panel__head">
				<button type="button" class="button button--ghost button--icon" @click="move(-1)">
					<AdminIcon name="chevron-left" /><span class="visually-hidden">{{ t('date_picker.previous_month', 'Previous month') }}</span>
				</button>
				<p class="date-panel__month" aria-live="polite">{{ t('date_picker.month_year', '{month} {year}', { month: MONTHS[month.getMonth()], year: String(month.getFullYear()) }) }}</p>
				<button type="button" class="button button--ghost button--icon" @click="move(1)">
					<AdminIcon name="chevron-right" /><span class="visually-hidden">{{ t('date_picker.next_month', 'Next month') }}</span>
				</button>
			</div>
			<div class="date-panel__week" aria-hidden="true">
				<span v-for="day in WEEKDAYS" :key="day">{{ day.charAt(0) }}</span>
			</div>
			<div class="date-panel__grid">
				<button
					v-for="day in days"
					:key="day.iso"
					type="button"
					class="date__day"
					:class="{ 'is-out': day.out, 'is-today': day.today, 'is-chosen': day.chosen }"
					:aria-pressed="day.chosen"
					:aria-label="day.label"
					@click="pickDay(day.date)"
				>{{ day.date.getDate() }}</button>
			</div>
			<div v-if="!dayOnly" class="date-panel__time">
				<span class="date-panel__label">{{ t('date_picker.time', 'Time') }}</span>
				<input v-model="hourText" class="date-panel__field mono" type="text" inputmode="numeric" maxlength="2" :aria-label="t('date_picker.hour', 'Hour')" @change="commitTime" @keydown="timeKey" @focus="($event.target as HTMLInputElement).select()">
				<span aria-hidden="true">:</span>
				<input v-model="minuteText" class="date-panel__field mono" type="text" inputmode="numeric" maxlength="2" :aria-label="t('date_picker.minute', 'Minute')" @change="commitTime" @keydown="timeKey" @focus="($event.target as HTMLInputElement).select()">
				<span class="date-panel__meridiem" role="group" :aria-label="t('date_picker.meridiem', 'Morning or afternoon')">
					<button type="button" :aria-pressed="!afternoon" @click="setMeridiem(false)">{{ t('date_picker.am', 'AM') }}</button>
					<button type="button" :aria-pressed="afternoon" @click="setMeridiem(true)">{{ t('date_picker.pm', 'PM') }}</button>
				</span>
			</div>
			<div class="date-panel__quick">
				<button type="button" class="button button--small" @click="quick('now')">{{ dayOnly ? t('date_picker.today', 'Today') : t('date_picker.now', 'Now') }}</button>
				<button type="button" class="button button--small" @click="quick('tomorrow')">{{ dayOnly ? t('date_picker.tomorrow', 'Tomorrow') : t('date_picker.tomorrow_nine', 'Tomorrow, 9 am') }}</button>
				<button type="button" class="button button--small" @click="quick('monday')">{{ t('date_picker.next_monday', 'Next Monday') }}</button>
				<button v-if="clearable && model !== ''" type="button" class="button button--small" @click="clear">{{ t('date_picker.clear', 'Clear') }}</button>
			</div>
		</div>
	</Teleport>
</template>

<style scoped>
/* The value: accent ink, no box, since it's chosen rather than typed. */
.date {
	display: inline-flex;
	align-items: center;
	gap: var(--s-2);
	min-width: 0;
	padding: 4px 6px;
	margin-left: -6px;
	border: 0;
	border-radius: var(--border-radius-small);
	background: none;
	color: var(--color-accent);
	font: inherit;
	text-align: left;
	cursor: pointer;
}

.date:hover,
.date[aria-expanded="true"] {
	background: var(--color-accent-subtle);
}

.date[aria-invalid="true"] {
	color: var(--color-danger);
}

.date__time-text {
	color: var(--color-text-muted);
	font-family: var(--font-mono);
	font-size: var(--font-size-small);
}

.date__caret {
	flex: none;
	width: 13px;
	height: 13px;
	color: var(--color-text-muted);
}
</style>

<style>
.date-panel {
	position: fixed;
	z-index: 70;
	display: grid;
	gap: var(--s-3);
	width: 284px;
	padding: var(--s-3);
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-medium);
	background: var(--color-surface);
	box-shadow: var(--shadow-2);
}

.date-panel__head {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.date-panel__month {
	font-weight: 600;
}

.date-panel__week,
.date-panel__grid {
	display: grid;
	grid-template-columns: repeat(7, 1fr);
	gap: 2px;
	text-align: center;
}

.date-panel__week {
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}

.date__day {
	aspect-ratio: 1;
	border: 0;
	border-radius: var(--border-radius-small);
	background: none;
	color: var(--color-text);
	font: inherit;
	font-variant-numeric: tabular-nums;
	cursor: pointer;
}

.date__day:hover {
	background: var(--color-surface-subtle);
}

.date__day.is-out {
	color: var(--color-text-muted);
}

.date__day.is-today {
	box-shadow: inset 0 0 0 1px var(--border-color-strong);
}

/* Soft, not solid: it's only saying "this one". */
.date__day.is-chosen {
	background: var(--color-accent-subtle);
	color: var(--color-accent);
	font-weight: 600;
}

.date-panel__time {
	display: flex;
	align-items: center;
	gap: 6px;
	padding-top: var(--s-3);
	border-top: 1px solid var(--border-color);
}

.date-panel__label {
	margin-right: auto;
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.date-panel__field {
	width: 3.2em;
	min-height: var(--ctl-sm);
	padding: 0;
	border: 1px solid var(--border-color-strong);
	border-radius: var(--border-radius-small);
	background: var(--color-surface);
	color: var(--color-text);
	text-align: center;
}

.date-panel__field:focus-visible {
	border-color: var(--color-accent);
	outline-offset: 0;
}

.date-panel__meridiem {
	display: inline-flex;
	margin-left: 6px;
	border: 1px solid var(--border-color-strong);
	border-radius: var(--border-radius-small);
	overflow: hidden;
}

.date-panel__meridiem button {
	min-height: calc(var(--ctl-sm) - 2px);
	padding: 0 9px;
	border: 0;
	background: var(--color-surface);
	color: var(--color-text-secondary);
	font: inherit;
	font-size: var(--font-size-small);
	cursor: pointer;
}

.date-panel__meridiem button[aria-pressed="true"] {
	background: var(--color-accent-subtle);
	color: var(--color-accent);
	font-weight: 600;
}

.date-panel__quick {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}
</style>
