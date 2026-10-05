<script setup lang="ts">
/**
 * A role's capabilities as sections (D-359, from the capability sections
 * sketch): one row per group of the site's capabilities (Media,
 * Structure, Site, People, and any an extension adds), and one per
 * content type, each a name, a sentence saying what the role can do,
 * and a triangle that opens its checkboxes. **Every Type**, first,
 * grants on all types, including ones added later (additive, D-360);
 * what it grants shows ticked and fixed in each type, and a type it
 * grants everything on is marked "Set by Every Type", with no ⋮. A row's ⋮ sets its whole section, a
 * section holding an unsaved change says so, and the toolbar opens or
 * closes them all and shows each capability's key.
 *
 * Anyone's (`.others`) needs their own: ticking one ticks the other, and
 * clearing their own clears anyone's. You can't give a capability you
 * don't have, so those are shown but can't be ticked.
 *
 * Media (D-407) is one grid, as every section is: uploading by kind
 * (**Upload every kind**, `media.*.upload`, ticks and fixes each), then
 * changing and deleting their own files or anyone's, paired as content
 * actions are; its sentence and ⋮ say and set it the same way.
 */

import { computed, ref, useId } from 'vue';
import AdminIcon from './AdminIcon.vue';
import MenuButton from './MenuButton.vue';
import TypeIcon from './TypeIcon.vue';
import type { IconName } from '../icons';
import { showKeys, type CapabilityInfo, type RoleType } from '../people';
import { can, type ContentAction } from '../session';

const props = defineProps<{
	capabilities: CapabilityInfo[];
	types: RoleType[];
	// What's saved, to mark the sections that changed; none for a new role.
	base?: string[];
	readonly?: boolean;
}>();

const model = defineModel<string[]>({ required: true });

const EVERY = '*';
const id    = useId();

// The actions in a section, in pairs: their own, then anyone's.
const ACTIONS: { action: ContentAction; label: string }[] = [
	{ action: 'create', label: 'Create entries' },
	{ action: 'edit', label: 'Edit their own' },
	{ action: 'edit.others', label: 'Edit anyone\'s' },
	{ action: 'publish', label: 'Publish their own' },
	{ action: 'publish.others', label: 'Publish anyone\'s' },
	{ action: 'delete', label: 'Delete their own' },
	{ action: 'delete.others', label: 'Delete anyone\'s' }
];

// A section's ⋮ for a type: the whole section at once.
const PRESETS: { key: string; label: string; text: string; actions: ContentAction[] }[] = [
	{ key: 'full', label: 'Full access', text: 'Create, and edit, publish, and delete anyone\'s.', actions: ACTIONS.map((item) => item.action) },
	{ key: 'own', label: 'Their own only', text: 'Create, and edit, publish, and delete their own.', actions: ['create', 'edit', 'publish', 'delete'] },
	{ key: 'drafts', label: 'Drafts only', text: 'Create, and edit and delete their own drafts. Never publish.', actions: ['create', 'edit', 'delete'] },
	{ key: 'none', label: 'No access', text: 'Nothing; the type is left out of their admin.', actions: [] }
];

// Media's kinds, by the names the library gives them.
const MEDIA_KINDS: { kind: string; label: string }[] = [
	{ kind: 'image', label: 'Images' },
	{ kind: 'video', label: 'Videos' },
	{ kind: 'audio', label: 'Audio' },
	{ kind: 'document', label: 'Documents' },
	{ kind: 'file', label: 'Other Files' }
];
const MEDIA_EVERY = 'media.*.upload';
const uploadKey   = (kind: string): string => `media.${kind}.upload`;

// Media's ⋮: the whole section at once.
const MEDIA_PRESETS: { key: string; label: string; text: string; names: string[] }[] = [
	{ key: 'full', label: 'Full access', text: 'Upload every kind, and change and delete anyone\'s files.', names: [MEDIA_EVERY, 'media.edit', 'media.edit.others', 'media.delete', 'media.delete.others'] },
	{ key: 'own', label: 'Their own only', text: 'Upload every kind, and change and delete their own files.', names: [MEDIA_EVERY, 'media.edit', 'media.delete'] },
	{ key: 'images', label: 'Images only', text: 'Upload images, and change their own files.', names: [uploadKey('image'), 'media.edit'] },
	{ key: 'none', label: 'No access', text: 'Nothing; the Media screen is left out of their admin.', names: [] }
];

const GROUP_ICONS: Record<string, IconName> = { Media: 'image', HTML: 'code', Structure: 'layers', Site: 'globe', Users: 'users', Themes: 'paintbrush', Plugins: 'plug', 'Icon Packs': 'shapes' };

const key = (type: string, action: ContentAction): string => `content.${type}.${action}`;

// Whether the viewer may give a capability.
const locked = (name: string): boolean => props.readonly === true || !can(name);

// The site's groups, in the order the server lists them.
const groups = computed(() => {
	const found = new Map<string, CapabilityInfo[]>();

	for (const capability of props.capabilities) {
		if (capability.type === undefined) {
			found.set(capability.group, [...(found.get(capability.group) ?? []), capability]);
		}
	}

	return [...found].map(([name, list]) => ({ name, icon: GROUP_ICONS[name] ?? 'plug', capabilities: list }));
});

// Types by their names, content before taxonomies.
const byLabel = (a: RoleType, b: RoleType): number => a.label.localeCompare(b.label);
const kinds   = computed(() => [
	{ key: 'content', label: 'Content types', types: props.types.filter((type) => type.kind !== 'taxonomy').sort(byLabel) },
	{ key: 'taxonomy', label: 'Taxonomies', types: props.types.filter((type) => type.kind === 'taxonomy').sort(byLabel) }
].filter((kind) => kind.types.length > 0));

const has = (name: string): boolean => model.value.includes(name);

// From Every Type, so fixed in each type.
const inherited = (type: string, action: ContentAction): boolean => type !== EVERY && has(key(EVERY, action));

// A type Every Type grants everything on: nothing in it can change.
const sealed = (type: string): boolean => ACTIONS.every((item) => inherited(type, item.action));
const granted   = (type: string, action: ContentAction): boolean => has(key(type, action)) || inherited(type, action);

function set(names: string[], on: boolean): void {
	const usable = names.filter((name) => !locked(name));

	model.value = on
		? [...model.value, ...usable.filter((name) => !model.value.includes(name))]
		: model.value.filter((name) => !usable.includes(name));
}

function toggle(type: string, action: ContentAction, on: boolean): void {
	const base   = action.replace('.others', '') as ContentAction;
	const others = `${base}.others` as ContentAction;

	if (action === base && !on && base !== 'create') {
		set([key(type, base), key(type, others)], false);
	} else if (action === others && on) {
		set([key(type, base), key(type, others)], true);
	} else {
		set([key(type, action)], on);
	}
}

function preset(type: string, actions: ContentAction[]): void {
	const names = ACTIONS.map((item) => key(type, item.action));
	const keep  = model.value.filter((name) => !names.includes(name) || locked(name));

	model.value = [...keep, ...actions.map((action) => key(type, action)).filter((name) => !locked(name) && !keep.includes(name))];
}

// Every Type's grants, moved into each type, so one can then differ.
function spread(): void {
	const moved = ACTIONS.filter((item) => has(key(EVERY, item.action))).map((item) => item.action);
	const names = props.types.flatMap((type) => moved.map((action) => key(type.name, action)));

	model.value = [
		...model.value.filter((name) => !moved.some((action) => name === key(EVERY, action))),
		...names.filter((name) => !model.value.includes(name))
	];
}

// What a role can do to a type's entries, in a sentence.
type Scope = 'none' | 'own' | 'any';

function scope(type: string, action: ContentAction): Scope {
	if (!granted(type, action)) {
		return 'none';
	}

	return granted(type, `${action}.others` as ContentAction) ? 'any' : 'own';
}

const whose = (value: Scope): string => value === 'any' ? 'anyone\'s' : 'their own';

function join(items: string[], word: string): string {
	return items.length < 2 ? (items[0] ?? '') : `${items.slice(0, -1).join(', ')} ${word} ${items[items.length - 1]}`;
}

function typeSentence(type: string): { text: string; none: boolean } {
	const create  = granted(type, 'create');
	const edit    = scope(type, 'edit');
	const publish = scope(type, 'publish');
	const remove  = scope(type, 'delete');

	if (!create && edit === 'none' && publish === 'none' && remove === 'none') {
		return { text: type === EVERY ? 'Nothing on every type; each type is set on its own.' : 'No access. Left out of their admin.', none: true };
	}

	// "Edit their own, publish their own, and delete their own" is one
	// fact said three times; the scope is said once when they agree.
	if (edit !== 'none' && edit === publish && publish === remove) {
		const tail = `edit, publish, and delete ${whose(edit)}`;

		return { text: create ? `Can create, and ${tail}.` : `Can ${tail}. Cannot create.`, none: false };
	}

	// Changing a live entry is publishing it, so without publishing,
	// editing and deleting reach drafts alone.
	const reach = (verb: string, value: Scope): string => {
		if (publish === 'none') {
			return `${verb} ${whose(value)} drafts`;
		}

		return value === 'any' && publish === 'own' ? `${verb} their own and anyone's drafts` : `${verb} ${whose(value)}`;
	};

	const yes: string[] = [];
	const no: string[]  = [];

	(create ? yes : no).push('create');
	edit === 'none' ? no.push('edit') : yes.push(reach('edit', edit));
	publish === 'none' ? no.push('publish') : yes.push(`publish ${whose(publish)}`);
	remove === 'none' ? no.push('delete') : yes.push(reach('delete', remove));

	return { text: `Can ${join(yes, 'and')}.${no.length ? ` Cannot ${join(no, 'or')}.` : ''}`, none: false };
}

// "Edit menus and edit regions" is what a plain join says; phrases that
// start with the same verb share it.
function phrases(list: string[], word: string): string {
	const shared: { verb: string; rest: string[] }[] = [];

	for (const phrase of list) {
		const [verb = '', ...words] = phrase.split(' ');
		const rest = words.join(' ');
		const last = shared[shared.length - 1];

		if (last !== undefined && last.verb === verb && rest !== '' && last.rest.length > 0) {
			last.rest.push(rest);
		} else {
			shared.push({ verb, rest: rest === '' ? [] : [rest] });
		}
	}

	return join(shared.map((item) => item.rest.length ? `${item.verb} ${join(item.rest, word)}` : item.verb), word);
}

function groupSentence(capabilities: CapabilityInfo[]): { text: string; none: boolean } {
	const phrase = (capability: CapabilityInfo): string => capability.label.charAt(0).toLowerCase() + capability.label.slice(1);
	const yes    = capabilities.filter((capability) => has(capability.name)).map(phrase);
	const no     = capabilities.filter((capability) => !has(capability.name)).map(phrase);

	return {
		text: [yes.length ? `Can ${phrases(yes, 'and')}.` : '', no.length ? `Cannot ${phrases(no, 'or')}.` : ''].filter(Boolean).join(' '),
		none: yes.length === 0
	};
}

// Media: what's granted, with Every kind fixing each kind.
const uploads = (kind: string): boolean => has(MEDIA_EVERY) || has(uploadKey(kind));

function mediaNames(): string[] {
	return [MEDIA_EVERY, ...MEDIA_KINDS.map((item) => uploadKey(item.kind)), 'media.edit', 'media.edit.others', 'media.delete', 'media.delete.others'];
}

// Their own and anyone's, paired as content actions are.
function toggleMedia(base: 'media.edit' | 'media.delete', others: boolean, on: boolean): void {
	if (!others && !on) {
		set([base, `${base}.others`], false);
	} else if (others && on) {
		set([base, `${base}.others`], true);
	} else {
		set([others ? `${base}.others` : base], on);
	}
}

function mediaPreset(names: string[]): void {
	const all  = mediaNames();
	const keep = model.value.filter((name) => !all.includes(name) || locked(name));

	model.value = [...keep, ...names.filter((name) => !locked(name) && !keep.includes(name))];
}

function mediaScope(base: 'media.edit' | 'media.delete'): Scope {
	return !has(base) ? 'none' : (has(`${base}.others`) ? 'any' : 'own');
}

function mediaSentence(): { text: string; none: boolean } {
	const kinds  = MEDIA_KINDS.filter((item) => uploads(item.kind)).map((item) => item.label.toLowerCase());
	const edit   = mediaScope('media.edit');
	const remove = mediaScope('media.delete');
	const yes: string[] = [];
	const no: string[]  = [];

	if (has(MEDIA_EVERY) || kinds.length === MEDIA_KINDS.length) {
		yes.push('upload every kind');
	} else if (kinds.length > 0) {
		yes.push(`upload ${join(kinds, 'and')}`);
	} else {
		no.push('upload');
	}

	if (edit !== 'none' && edit === remove) {
		yes.push(`change and delete ${whose(edit)} files`);
	} else {
		edit === 'none' ? no.push('change files') : yes.push(`change ${whose(edit)} files`);
		remove === 'none' ? no.push('delete files') : yes.push(`delete ${whose(remove)} files`);
	}

	return {
		text: [yes.length ? `Can ${join(yes, 'and')}.` : '', no.length ? `Cannot ${join(no, 'or')}.` : ''].filter(Boolean).join(' '),
		none: yes.length === 0
	};
}

// Which sections hold unsaved changes.
function changed(names: string[]): boolean {
	return props.base !== undefined && names.some((name) => model.value.includes(name) !== props.base?.includes(name));
}

const typeNames = (type: string): string[] => ACTIONS.map((item) => key(type, item.action));

// Open sections, and the toolbar's two switches.
const open     = ref(new Set<string>());
const sections = computed(() => [...groups.value.map((group) => `group-${group.name}`), ...props.types.map((type) => `type-${type.name}`), `type-${EVERY}`]);
const allOpen  = computed(() => sections.value.every((section) => open.value.has(section)));

function flip(section: string): void {
	const next = new Set(open.value);

	if (!next.delete(section)) {
		next.add(section);
	}

	open.value = next;
}

function flipAll(): void {
	open.value = allOpen.value ? new Set() : new Set(sections.value);
}
</script>

<template>
	<div class="sections" :class="{ 'sections--keys': showKeys }">
		<div class="sections__toolbar">
			<span class="sections__note">Open a section to {{ readonly ? 'see' : 'change' }} what it grants.</span>
			<button type="button" class="button button--small" @click="flipAll">
				<AdminIcon :name="allOpen ? 'chevron-up' : 'chevron-down'" />{{ allOpen ? 'Collapse all' : 'Expand all' }}
			</button>
			<button type="button" class="button button--small" :aria-pressed="showKeys" @click="showKeys = !showKeys">
				<AdminIcon name="key-round" />{{ showKeys ? 'Hide keys' : 'Show keys' }}
			</button>
		</div>

		<section class="panel" :aria-labelledby="`${id}-site`">
			<header class="panel__header">
				<h2 :id="`${id}-site`">Site Capabilities</h2>
				<p class="panel__hint">Not tied to a content type</p>
			</header>
			<div v-for="group in groups" :key="group.name" class="section">
				<button type="button" class="section__head" :aria-expanded="open.has(`group-${group.name}`)" :aria-controls="`${id}-group-${group.name}`" @click="flip(`group-${group.name}`)">
					<AdminIcon name="chevron-right" class="section__twisty" />
					<AdminIcon :name="group.icon" class="section__icon" />
					<span class="section__main">
						<span class="section__name">
							<span class="section__title">{{ group.name }}</span>
							<span v-if="changed(group.capabilities.map((capability) => capability.name))" class="pill pill--warn section__pill">Changes</span>
						</span>
						<code v-if="group.name === 'Media'" class="section__key">media.…</code>
						<span v-if="group.name === 'Media'" class="section__sentence" :class="{ 'is-none': mediaSentence().none }">{{ mediaSentence().text }}</span>
						<span v-else class="section__sentence" :class="{ 'is-none': groupSentence(group.capabilities).none }">{{ groupSentence(group.capabilities).text }}</span>
					</span>
				</button>
				<span v-if="!readonly" class="section__end">
					<MenuButton button-class="button button--ghost button--small button--icon section__menu" :label="`Set everything in ${group.name}`" floating>
						<template #button><AdminIcon name="ellipsis-vertical" /></template>
						<template v-if="group.name === 'Media'">
							<p class="menu-heading">Set Media</p>
							<button v-for="item in MEDIA_PRESETS" :key="item.key" type="button" class="menu-item menu-item--described" @click="mediaPreset(item.names)">
								<span>
									<span class="menu-item__name">{{ item.label }}</span>
									<span class="menu-item__text">{{ item.text }}</span>
								</span>
							</button>
						</template>
						<template v-else>
							<p class="menu-heading">Set this group</p>
							<button type="button" class="menu-item" @click="set(group.capabilities.map((capability) => capability.name), true)">Grant everything in {{ group.name }}</button>
							<button type="button" class="menu-item" @click="set(group.capabilities.map((capability) => capability.name), false)">Remove everything in {{ group.name }}</button>
						</template>
					</MenuButton>
				</span>
				<div v-if="open.has(`group-${group.name}`) && group.name === 'Media'" :id="`${id}-group-${group.name}`" class="section__body">
					<div class="section__grid">
						<label class="capability" :class="{ 'is-off': !has(MEDIA_EVERY), 'is-locked': locked(MEDIA_EVERY) }">
							<input type="checkbox" class="capability__input" :checked="has(MEDIA_EVERY)" :disabled="locked(MEDIA_EVERY)" @change="set([MEDIA_EVERY], ($event.target as HTMLInputElement).checked)">
							<span class="capability__box" aria-hidden="true"><AdminIcon name="check" /></span>
							<span class="capability__label">Upload every kind<span v-if="!readonly && !can(MEDIA_EVERY)" class="visually-hidden"> (you don't have it, so you can't give it)</span></span>
							<code class="capability__key">{{ MEDIA_EVERY }}</code>
						</label>
						<label v-for="item in MEDIA_KINDS" :key="item.kind" class="capability" :class="{ 'is-off': !uploads(item.kind), 'is-locked': locked(uploadKey(item.kind)) || has(MEDIA_EVERY) }">
							<input type="checkbox" class="capability__input" :checked="uploads(item.kind)" :disabled="locked(uploadKey(item.kind)) || has(MEDIA_EVERY)" @change="set([uploadKey(item.kind)], ($event.target as HTMLInputElement).checked)">
							<span class="capability__box" aria-hidden="true"><AdminIcon name="check" /></span>
							<span class="capability__label">Upload {{ item.label.toLowerCase() }}<span v-if="has(MEDIA_EVERY)" class="capability__from"> · every kind</span><span v-else-if="!readonly && !can(uploadKey(item.kind))" class="visually-hidden"> (you don't have it, so you can't give it)</span></span>
							<code class="capability__key">{{ uploadKey(item.kind) }}</code>
						</label>
						<template v-for="action in ([['media.edit', 'Change'], ['media.delete', 'Delete']] as const)" :key="action[0]">
							<label v-for="others in [false, true]" :key="String(others)" class="capability" :class="{ 'is-off': !has(others ? `${action[0]}.others` : action[0]), 'is-locked': locked(others ? `${action[0]}.others` : action[0]) }">
								<input type="checkbox" class="capability__input" :checked="has(others ? `${action[0]}.others` : action[0])" :disabled="locked(others ? `${action[0]}.others` : action[0])" @change="toggleMedia(action[0], others, ($event.target as HTMLInputElement).checked)">
								<span class="capability__box" aria-hidden="true"><AdminIcon name="check" /></span>
								<span class="capability__label">{{ action[1] }} {{ others ? 'anyone\'s files' : 'their own files' }}<span v-if="!readonly && !can(others ? `${action[0]}.others` : action[0])" class="visually-hidden"> (you don't have it, so you can't give it)</span></span>
								<code class="capability__key">{{ others ? `${action[0]}.others` : action[0] }}</code>
							</label>
						</template>
					</div>
					<p class="section__note">A file is its uploader's. Files from before uploads were recorded, or added by hand, are anyone's.</p>
				</div>
				<div v-else-if="open.has(`group-${group.name}`)" :id="`${id}-group-${group.name}`" class="section__body">
					<div class="section__grid">
						<label v-for="capability in group.capabilities" :key="capability.name" class="capability" :class="{ 'is-off': !has(capability.name), 'is-locked': locked(capability.name) }">
							<input type="checkbox" class="capability__input" :checked="has(capability.name)" :disabled="locked(capability.name)" @change="set([capability.name], ($event.target as HTMLInputElement).checked)">
							<span class="capability__box" aria-hidden="true"><AdminIcon name="check" /></span>
							<span class="capability__label">{{ capability.label }}<span v-if="!readonly && !can(capability.name)" class="visually-hidden"> (you don't have it, so you can't give it)</span></span>
							<code class="capability__key">{{ capability.name }}</code>
						</label>
					</div>
				</div>
			</div>
		</section>

		<section class="panel" :aria-labelledby="`${id}-content`">
			<header class="panel__header">
				<h2 :id="`${id}-content`">Content Capabilities</h2>
				<p class="panel__hint">One section per content type</p>
			</header>
			<div class="section section--every">
				<button type="button" class="section__head" :aria-expanded="open.has(`type-${EVERY}`)" :aria-controls="`${id}-type-every`" @click="flip(`type-${EVERY}`)">
					<AdminIcon name="chevron-right" class="section__twisty" />
					<AdminIcon name="pin" class="section__icon" />
					<span class="section__main">
						<span class="section__name">
							<span class="section__title">Every Type</span>
							<span class="index-mark">Includes new types</span>
							<span v-if="changed(typeNames(EVERY))" class="pill pill--warn section__pill">Changes</span>
						</span>
						<code class="section__key">content.*.…</code>
						<span class="section__sentence" :class="{ 'is-none': typeSentence(EVERY).none }">{{ typeSentence(EVERY).text }}</span>
					</span>
				</button>
				<span v-if="!readonly" class="section__end">
					<MenuButton button-class="button button--ghost button--small button--icon section__menu" label="Set everything in Every Type" floating>
						<template #button><AdminIcon name="ellipsis-vertical" /></template>
						<p class="menu-heading">Set every action</p>
						<button v-for="item in PRESETS" :key="item.key" type="button" class="menu-item menu-item--described" @click="preset(EVERY, item.actions)">
							<span>
								<span class="menu-item__name">{{ item.label }}</span>
								<span class="menu-item__text">{{ item.text }}</span>
							</span>
						</button>
						<div class="menu-divider" />
						<button type="button" class="menu-item menu-item--described" :disabled="!ACTIONS.some((item) => has(key(EVERY, item.action)))" @click="spread">
							<span>
								<span class="menu-item__name">Set each type separately</span>
								<span class="menu-item__text">Moves these into every type there is now, so one can differ. Types added later get nothing.</span>
							</span>
						</button>
					</MenuButton>
				</span>
				<div v-if="open.has(`type-${EVERY}`)" :id="`${id}-type-every`" class="section__body">
					<div class="section__grid">
						<label v-for="item in ACTIONS" :key="item.action" class="capability" :class="{ 'is-off': !granted(EVERY, item.action), 'is-locked': locked(key(EVERY, item.action)) }">
							<input type="checkbox" class="capability__input" :checked="granted(EVERY, item.action)" :disabled="locked(key(EVERY, item.action))" @change="toggle(EVERY, item.action, ($event.target as HTMLInputElement).checked)">
							<span class="capability__box" aria-hidden="true"><AdminIcon name="check" /></span>
							<span class="capability__label">{{ item.label }}<span v-if="!readonly && !can(key(EVERY, item.action))" class="visually-hidden"> (you don't have it, so you can't give it)</span></span>
							<code class="capability__key">{{ key(EVERY, item.action) }}</code>
						</label>
					</div>
					<p class="section__note">What's ticked here is granted on every content type, including ones added later, and shows fixed in each type's section below.</p>
				</div>
			</div>
			<template v-for="kind in kinds" :key="kind.key">
				<p class="sections__caption">{{ kind.label }}</p>
				<div v-for="type in kind.types" :key="type.name" class="section">
					<button type="button" class="section__head" :aria-expanded="open.has(`type-${type.name}`)" :aria-controls="`${id}-type-${type.name}`" @click="flip(`type-${type.name}`)">
						<AdminIcon name="chevron-right" class="section__twisty" />
						<TypeIcon :type="type" class="section__icon" />
						<span class="section__main">
							<span class="section__name">
								<span class="section__title">{{ type.label }}</span>
								<span v-if="sealed(type.name)" class="index-mark">Set by Every Type</span>
								<span v-if="changed(typeNames(type.name))" class="pill pill--warn section__pill">Changes</span>
							</span>
							<code class="section__key">content.{{ type.name }}.…</code>
							<span class="section__sentence" :class="{ 'is-none': typeSentence(type.name).none }">{{ typeSentence(type.name).text }}</span>
						</span>
					</button>
					<span v-if="!readonly && !sealed(type.name)" class="section__end">
						<MenuButton button-class="button button--ghost button--small button--icon section__menu" :label="`Set everything in ${type.label}`" floating>
							<template #button><AdminIcon name="ellipsis-vertical" /></template>
							<p class="menu-heading">Set every action</p>
							<button v-for="item in PRESETS" :key="item.key" type="button" class="menu-item menu-item--described" @click="preset(type.name, item.actions)">
								<span>
									<span class="menu-item__name">{{ item.label }}</span>
									<span class="menu-item__text">{{ item.text }}</span>
								</span>
							</button>
						</MenuButton>
					</span>
					<div v-if="open.has(`type-${type.name}`)" :id="`${id}-type-${type.name}`" class="section__body">
						<div class="section__grid">
							<label v-for="item in ACTIONS" :key="item.action" class="capability" :class="{ 'is-off': !granted(type.name, item.action), 'is-locked': locked(key(type.name, item.action)) || inherited(type.name, item.action) }">
								<input type="checkbox" class="capability__input" :checked="granted(type.name, item.action)" :disabled="locked(key(type.name, item.action)) || inherited(type.name, item.action)" @change="toggle(type.name, item.action, ($event.target as HTMLInputElement).checked)">
								<span class="capability__box" aria-hidden="true"><AdminIcon name="check" /></span>
								<span class="capability__label">{{ item.label }}<span v-if="inherited(type.name, item.action)" class="capability__from"> · every type</span><span v-else-if="!readonly && !can(key(type.name, item.action))" class="visually-hidden"> (you don't have it, so you can't give it)</span></span>
								<code class="capability__key">{{ key(type.name, item.action) }}</code>
							</label>
						</div>
					</div>
				</div>
			</template>

		</section>
	</div>
</template>

<style scoped>
.sections {
	display: grid;
	gap: var(--s-4);
}

.sections__toolbar {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
}

.sections__note {
	margin-right: auto;
	color: var(--fg-3);
	font-size: var(--text-sm);
}

/* A caption, so content types and taxonomies don't read as one list. */
.sections__caption {
	padding: 11px var(--pad-x) 7px;
	border-bottom: 1px solid var(--border);
	background: var(--surface-2);
	color: var(--fg-3);
	font-size: var(--text-2xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

/* A section: a name, a sentence, and a triangle. */
.section {
	display: flex;
	flex-wrap: wrap;
	align-items: stretch;
	border-bottom: 1px solid var(--border);
}

.section:last-child {
	border-bottom: 0;
	border-radius: 0 0 var(--r-3) var(--r-3);
}

.section__head {
	display: flex;
	flex: 1;
	align-items: center;
	gap: var(--s-3);
	min-width: 0;
	padding: var(--pad-row) var(--s-2) var(--pad-row) var(--pad-x);
	border: 0;
	background: none;
	color: inherit;
	font: inherit;
	text-align: left;
	cursor: pointer;
}

.section__head:hover,
.section:has(.section__head:hover) .section__end {
	background: var(--surface-2);
}

.section__twisty {
	width: 13px;
	height: 13px;
	color: var(--fg-3);
}

.section__head[aria-expanded="true"] .section__twisty {
	transform: rotate(90deg);
}

@media (prefers-reduced-motion: no-preference) {
	.section__twisty {
		transition: transform .12s;
	}
}

.section__icon {
	color: var(--fg-3);
}

.section__main {
	flex: 1;
	min-width: 0;
}

.section__name {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
	min-width: 0;
}

.section__title {
	overflow: hidden;
	font-family: var(--font-title);
	font-size: var(--title-size);
	font-weight: var(--title-weight);
	letter-spacing: var(--title-track);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.section__pill {
	height: 19px;
	padding: 0 8px 0 7px;
	font-size: var(--text-2xs);
}

.section__key {
	display: none;
	margin-top: 3px;
	color: var(--fg-3);
	font-family: var(--font-mono);
	font-size: var(--text-2xs);
}

.section__sentence {
	display: block;
	max-width: 76ch;
	margin-top: 4px;
	color: var(--fg-3);
	font-size: var(--text-sm);
	text-wrap: pretty;
}

.section__sentence.is-none {
	font-style: italic;
}

.section__end {
	display: flex;
	flex: none;
	align-items: center;
	padding-right: var(--pad-x);
}

/* The ⋮ shows on hover, and always to the keyboard. */
.section__end :deep(.section__menu) {
	opacity: 0;
}

.section:hover .section__end :deep(.section__menu),
.section__end :deep(.section__menu:focus-visible),
.section__end :deep(.section__menu[aria-expanded="true"]) {
	opacity: 1;
}

@media (hover: none) {
	.section__end :deep(.section__menu) {
		opacity: 1;
	}
}

/* Every Type, first, since it decides what's fixed in the types below. */
.section--every {
	border-bottom-color: var(--border-strong);
	background: var(--surface-2);
}

.section--every .section__head:hover,
.section--every:has(.section__head:hover) .section__end {
	background: var(--surface-3);
}

.section__body {
	flex: 0 0 100%;
	box-sizing: border-box;
	min-width: 0;
	padding: var(--s-3) var(--pad-x) var(--s-3) calc(var(--pad-x) + 25px);
	border-top: 1px solid var(--border);
	background: var(--surface-2);
}

/* Three columns, in every section, so the boxes line up down the page. */
.section__grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 0 var(--s-5);
	max-width: 880px;
}

.section__note {
	max-width: 60ch;
	padding-top: var(--s-2);
	color: var(--fg-3);
	font-size: var(--text-sm);
}

/* A capability: a drawn box, its label, and its key. */
.capability {
	position: relative;
	display: flex;
	align-items: center;
	gap: 10px;
	min-width: 0;
	padding: 7px 0;
	color: var(--fg-2);
	font-size: var(--text-sm);
	cursor: pointer;
}

.capability:hover {
	color: var(--fg);
}

.capability.is-off {
	color: var(--fg-3);
}

.capability.is-locked {
	cursor: default;
}

.capability__input {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	clip-path: inset(50%);
	white-space: nowrap;
}

.capability__box {
	display: grid;
	flex: none;
	place-items: center;
	width: 15px;
	height: 15px;
	border: 1px solid var(--border-strong);
	border-radius: 4px;
	background: var(--surface);
	color: transparent;
}

.capability__box svg {
	width: 11px;
	height: 11px;
	stroke-width: 2.8;
}

.capability:not(.is-locked):hover .capability__box {
	border-color: var(--accent);
}

.capability__input:checked + .capability__box {
	border-color: var(--accent);
	background: var(--accent);
	color: var(--accent-fg);
}

.capability__input:focus-visible + .capability__box {
	outline: 2px solid var(--accent);
	outline-offset: 2px;
}

.capability__input:disabled + .capability__box {
	opacity: .45;
}

.capability__label {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.capability__from {
	color: var(--fg-3);
}

.capability__key {
	display: none;
	flex: none;
	margin-left: auto;
	padding-left: var(--s-3);
	color: var(--fg-3);
	font-family: var(--font-mono);
	font-size: var(--text-2xs);
}

.sections--keys .section__key {
	display: block;
}

.sections--keys .capability__key {
	display: inline;
}

/* Two where three would cut the labels short. */
@media (width <= 1100px) {
	.section__grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (width <= 640px) {
	.sections__note {
		display: none;
	}

	.section__head {
		padding-left: var(--s-4);
	}

	.section__end {
		padding-right: var(--s-4);
	}

	.section__body {
		padding-right: var(--s-4);
		padding-left: calc(var(--s-4) + 23px);
	}

	.section__grid {
		grid-template-columns: minmax(0, 1fr);
	}

	.sections__caption {
		padding-inline: var(--s-4);
	}
}
</style>
