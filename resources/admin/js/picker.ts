/**
 * What every relation picker shares (D-599, D-607): the field's values,
 * what's known about each, the relation's rules, and the search, for
 * the five controls (tree, tokens, people, select, cards) to draw.
 *
 * The form value stays what the form has always held, the values
 * separated by commas, so saving is unchanged. A value typed in new is
 * kept as it was typed (`Ricotta Salata`), and the save writes it as a
 * new entry titled that way (D-596): nothing is written until Update.
 *
 * **At scale** (D-607): the first answer has every candidate when there
 * are 50 or fewer (`whole`), which the picker then searches itself;
 * past that it holds only what the field has, and asks the server as
 * typing stops (200ms), dropping an answer for a search already typed
 * past. Either way a search shows at most `cap` results, ranked, and
 * says how many matched, and before anything's typed the picker offers
 * a few (`suggest`: the most used, the recently edited, or the recently
 * linked through the relation).
 */

import { computed, ref, watch, type Ref } from 'vue';
import { debounced } from './action';
import { errorMessage, type FieldDescription, type InheritedValues } from './api';
import { moreLine, ranked, referenceValues, slugOf, loadReferences, WHOLE, type ReferenceItem } from './references';
import { moved, useReorder } from './reorder';
import { canType } from './session';
import { labelsOf } from './types';

export interface PickerProps {
	id: string;
	field: FieldDescription;
	// Whether the last value stays (an entry's main byline, or a required
	// people field).
	keepLast?: boolean;
	// The entry's own slug: never offered, and a term isn't its own parent.
	self?: string;
	invalid?: boolean;
	describedBy?: string;
	// A single value drawn as a value in a row, not a box.
	plain?: boolean;
	// What a translation uses from its original.
	inherited?: InheritedValues;
	// Whether publishing was tried with the field short of its minimum.
	attempted?: boolean;
}

export interface PickerOptions {
	// How many results a search shows.
	cap: number;
	// What's offered before anything's typed, and how many.
	suggest: 'uses' | 'edited' | 'recent';
	suggestions: number;
	// Whether a search shows what's chosen too (cards tick them).
	keepChosen?: boolean;
	// Whether the field's whole list is a tree: a term's parent leaves
	// out its own branch.
	branch?: boolean;
}

/**
 * The state and actions a picker draws from.
 */
export function usePicker(props: PickerProps, model: Ref<string>, options: PickerOptions) {
	const type     = computed(() => props.field.to ?? '');
	const names    = computed(() => labelsOf(type.value));
	// The entry's own type's words, from the relation's key (`recipe.cooks`).
	const source   = computed(() => labelsOf(props.field.relation?.key.split('.')[0] ?? 'entry'));
	const multiple = computed(() => props.field.multiple !== false);
	const relation = computed(() => props.field.relation);

	// What's known about each slug, from every answer so far.
	const known = ref(new Map<string, ReferenceItem>());

	function remember(items: ReferenceItem[]): void {
		const next = new Map(known.value);

		for (const item of items) {
			if (!item.missing || !next.has(item.slug)) {
				next.set(item.slug, item);
			}
		}

		known.value = next;
	}

	const values = computed(() => referenceValues(model.value));
	const slugs  = computed(() => values.value.map(slugOf));

	function has(slug: string): boolean {
		return slugs.value.includes(slug);
	}

	function write(next: string[]): void {
		model.value = next.join(', ');
	}

	// Values typed in new here, by slug: written when the entry is saved.
	const fresh = ref(new Set<string>());

	function itemOf(value: string): ReferenceItem {
		return known.value.get(slugOf(value)) ?? { slug: slugOf(value), title: value, status: null, parent: null, uses: null, depth: null, missing: false };
	}

	function isNew(value: string): boolean {
		return fresh.value.has(slugOf(value)) && known.value.get(slugOf(value))?.missing !== false;
	}

	function isMissing(value: string): boolean {
		return !isNew(value) && itemOf(value).missing;
	}

	// Each value that names nothing, as written, for its line.
	const missing = computed(() => values.value.filter(isMissing).map((value) => ({ value, item: itemOf(value) })));

	// The values typed in new, written when the entry is saved.
	const added = computed(() => values.value.filter(isNew));

	// How many it takes, and needs to publish (D-599).
	const max     = computed(() => multiple.value ? relation.value?.max ?? null : null);
	const minimum = computed(() => relation.value?.min ?? 0);
	const full    = computed(() => max.value !== null && values.value.length >= max.value);
	const ordered = computed(() => relation.value?.ordered === true);
	const reorder = useReorder(() => values.value.length, (from, to) => write(moved(values.value, from, to)));

	// Whether the last one stays: the × goes, and a line says why.
	const lastStays = computed(() => props.keepLast === true && values.value.length <= 1);

	function add(value: string): void {
		const slug = slugOf(value);

		if (slug === '' || has(slug) || (multiple.value && full.value)) {
			return;
		}

		write(multiple.value ? [...values.value, slug] : [slug]);
	}

	function remove(slug: string): void {
		if (lastStays.value) {
			return;
		}

		write(values.value.filter((value) => slugOf(value) !== slug));
	}

	function toggle(slug: string): void {
		if (has(slug)) {
			write(values.value.filter((value) => slugOf(value) !== slug));
		} else {
			add(slug);
		}
	}

	// A value that names nothing, swapped for what it most likely meant.
	function replace(value: string, slug: string): void {
		write(values.value.map((each) => each === value ? slug : each));
	}

	// What a translation shows from its original: the original's, when it
	// has none of its own, or always beside its own.
	const inheritedShown = computed(() => props.inherited !== undefined && (props.inherited.rule === 'add' || values.value.length === 0) ? props.inherited.values : []);

	// The entry itself is never offered; a term's parent leaves out its
	// branch too.
	const except = computed(() => {
		const source = relation.value?.key.split('.')[0];

		return props.self !== undefined && (options.branch === true || source === type.value) ? props.self : undefined;
	});

	// The first answer: every candidate, when there are few enough.
	const whole     = ref<ReferenceItem[] | null>(null);
	const tree      = ref(false);
	const total     = ref(0);
	const excluded  = ref(0);
	const suggested = ref<ReferenceItem[]>([]);
	const create    = ref(false);
	// The inverse's `max` each candidate's `taken` counts against (D-608).
	const inverseMax = ref<number | null>(null);
	const loading   = ref(true);
	const error     = ref('');

	async function start(): Promise<void> {
		error.value = '';

		try {
			const list = await loadReferences(type.value, {
				slugs: [...slugs.value, ...(props.inherited?.values ?? [])],
				upto: WHOLE,
				// Without a relation, nothing was linked through one.
				suggest: options.suggest === 'recent' && relation.value === undefined ? 'edited' : options.suggest,
				suggestions: options.suggestions,
				// The relation, for what it suggests and its targets' counts.
				...(relation.value !== undefined ? { from: relation.value.key } : {}),
				...(except.value === undefined ? {} : { except: except.value, branch: options.branch === true })
			});

			inverseMax.value = list.inverseMax ?? null;

			create.value    = relation.value === undefined ? list.create : relation.value.create;
			whole.value     = list.whole === true ? list.items.filter((item) => !item.missing && item.status !== 'trash') : null;
			tree.value      = list.tree;
			total.value     = list.total;
			excluded.value  = list.excluded ?? 0;
			suggested.value = list.suggested ?? [];
			remember([...list.items, ...suggested.value]);
		} catch (caught) {
			error.value = errorMessage(caught, `The ${names.value.items} couldn't be loaded.`);
		} finally {
			loading.value = false;
		}
	}

	watch(type, () => void start(), { immediate: true });

	// A value the field gains that nothing's said about yet (one a save
	// wrote) is asked about.
	watch(slugs, (next) => {
		const unknown = next.filter((slug) => !known.value.has(slug) && !fresh.value.has(slug));

		if (!loading.value && unknown.length > 0) {
			void loadReferences(type.value, { slugs: unknown, limit: 1 }).then((list) => remember(list.items)).catch(() => undefined);
		}
	});

	// The search: over the whole list, or asked of the server.
	const query     = ref('');
	const results   = ref<ReferenceItem[]>([]);
	const matched   = ref(0);
	const searching = ref(false);
	const active    = ref(0);
	let asked       = 0;

	function offered(items: ReferenceItem[]): ReferenceItem[] {
		return items.filter((item) => !item.missing && item.status !== 'trash' && (options.keepChosen === true || !has(item.slug)));
	}

	const ask = debounced(async (text: string, ticket: number) => {
		try {
			const list = await loadReferences(type.value, {
				search: text.trim(),
				limit: options.cap,
				upto: WHOLE,
				...(relation.value !== undefined ? { from: relation.value.key } : {}),
				...(except.value === undefined ? {} : { except: except.value, branch: options.branch === true })
			});

			// An answer for a search already typed past is dropped.
			if (ticket === asked) {
				remember(list.items);
				results.value = offered(list.items);
				matched.value = list.total;
			}
		} catch {
			if (ticket === asked) {
				results.value = [];
				matched.value = 0;
			}
		} finally {
			if (ticket === asked) {
				searching.value = false;
			}
		}
	}, 200);

	watch(query, (text) => {
		ask.cancel();
		asked++;
		active.value = 0;

		if (text.trim() === '') {
			results.value   = [];
			matched.value   = 0;
			searching.value = false;

			return;
		}

		if (whole.value !== null) {
			const found = offered(ranked(whole.value, text));

			results.value   = found.slice(0, options.cap);
			matched.value   = found.length;
			searching.value = false;

			return;
		}

		searching.value = true;
		ask(text, asked);
	});

	// The last line of a capped list.
	const more = computed(() => query.value.trim() === '' || searching.value || matched.value === 0 ? '' : moreLine(results.value.length, matched.value));

	// What's offered before anything's typed.
	const before = computed(() => suggested.value.filter((item) => !item.missing && (options.keepChosen === true || !has(item.slug))));

	// New ones are written published, so the account needs both (D-596).
	const canCreate = computed(() => create.value && canType(type.value, 'create') && canType(type.value, 'publish'));

	// Whether the typed text can be added as a new entry: nothing matches
	// it exactly, the relation takes new ones, and the account may create
	// them.
	const creatable = computed(() => {
		const typed = query.value.trim();

		return canCreate.value && !full.value && typed !== '' && slugOf(typed) !== '' && !searching.value
			&& !results.value.some((item) => item.slug === slugOf(typed)) && !has(slugOf(typed));
	});

	/**
	 * Takes a result, or with none, adds what was typed as a new entry,
	 * written when the entry is saved (D-607).
	 */
	function choose(item: ReferenceItem | null): void {
		const typed = query.value.trim().replace(/,/g, ' ').replace(/\s+/g, ' ');

		query.value = '';

		if (item !== null) {
			if (options.keepChosen === true && has(item.slug)) {
				toggle(item.slug);
			} else {
				add(item.slug);
			}
		} else if (typed !== '' && canCreate.value && !full.value && !has(slugOf(typed))) {
			fresh.value = new Set([...fresh.value, slugOf(typed)]);
			write(multiple.value ? [...values.value, typed] : [typed]);
		}
	}

	// The list's keys: arrows move, Enter takes the highlighted result
	// (creating only when Create is all there is), Escape clears.
	function searchKey(event: KeyboardEvent, list: ReferenceItem[]): void {
		const count = list.length + (creatable.value ? 1 : 0);

		if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
			event.preventDefault();
			active.value = count === 0 ? 0 : (active.value + (event.key === 'ArrowDown' ? 1 : count - 1)) % count;
		} else if (event.key === 'Enter') {
			event.preventDefault();

			if (count > 0) {
				choose(list[active.value] ?? null);
			}
		} else if (event.key === 'Escape' && query.value !== '') {
			event.preventDefault();
			event.stopPropagation();
			query.value = '';
		}
	}

	return {
		type, names, source, multiple, relation, known, values, slugs, has, write, itemOf, isNew, isMissing, missing, added, remember,
		max, minimum, full, ordered, reorder, lastStays, add, remove, toggle, replace, inheritedShown,
		whole, tree, total, excluded, suggested, create, inverseMax, loading, error, start,
		query, results, matched, searching, active, more, before, canCreate, creatable, choose, searchKey
	};
}

/**
 * The field's state at a glance, for its heading (D-607): a count, a
 * count against a limit, what's missing, or what comes from the
 * original. `tone` is `need` while something's missing and `bad` once
 * publishing was tried without it.
 */
export function pickerMeta(field: FieldDescription, count: number, options: { inherited?: InheritedValues; people?: boolean; attempted?: boolean } = {}): { text: string; tone: '' | 'need' | 'bad' | 'full' } {
	const relation = field.relation;
	const min      = Math.max(relation?.min ?? 0, field.required === true ? 1 : 0);
	const max      = field.multiple === false ? null : relation?.max ?? null;
	const from     = options.inherited;

	if (from !== undefined && from.values.length > 0 && (from.rule === 'add' || count === 0)) {
		return { text: from.rule === 'add' ? `${count} + ${from.values.length} from ${from.language}` : `From ${from.language}`, tone: '' };
	}

	if (min > 0 && count < min) {
		return { text: count === 0 && options.attempted !== true ? `Needs ${min}` : `${count} of at least ${min}`, tone: options.attempted === true ? 'bad' : 'need' };
	}

	if (max !== null && max > 1) {
		return { text: `${count} of ${max}`, tone: count >= max ? 'full' : '' };
	}

	if (count === 0) {
		return { text: '', tone: '' };
	}

	return { text: options.people === true ? `${count} ${count === 1 ? 'person' : 'people'}` : `${count.toLocaleString()} selected`, tone: '' };
}
