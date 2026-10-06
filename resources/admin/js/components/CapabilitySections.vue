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
import CapabilityCheck from './CapabilityCheck.vue';
import CapabilitySection from './CapabilitySection.vue';
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

// One the viewer can't give, said to a screen reader where it's shown.
const unheld = (name: string): boolean => props.readonly !== true && !can(name);

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
			<template v-for="group in groups" :key="group.name">
				<CapabilitySection
					v-if="group.name === 'Media'"
					:title="group.name"
					:open="open.has(`group-${group.name}`)"
					:body-id="`${id}-group-${group.name}`"
					:sentence="mediaSentence()"
					:changed="changed(group.capabilities.map((capability) => capability.name))"
					key-text="media.…"
					note="A file is its uploader's. Files from before uploads were recorded, or added by hand, are anyone's."
					:menu-label="readonly ? undefined : `Set everything in ${group.name}`"
					menu-heading="Set Media"
					:presets="MEDIA_PRESETS"
					@toggle="flip(`group-${group.name}`)"
					@preset="(item) => mediaPreset(item.names)"
				>
					<template #icon><AdminIcon :name="group.icon" class="section__icon" /></template>
					<CapabilityCheck :name="MEDIA_EVERY" label="Upload every kind" :checked="has(MEDIA_EVERY)" :disabled="locked(MEDIA_EVERY)" :unheld="unheld(MEDIA_EVERY)" @change="(on) => set([MEDIA_EVERY], on)" />
					<CapabilityCheck
						v-for="item in MEDIA_KINDS"
						:key="item.kind"
						:name="uploadKey(item.kind)"
						:label="`Upload ${item.label.toLowerCase()}`"
						:checked="uploads(item.kind)"
						:disabled="locked(uploadKey(item.kind)) || has(MEDIA_EVERY)"
						:from="has(MEDIA_EVERY) ? 'every kind' : undefined"
						:unheld="unheld(uploadKey(item.kind))"
						@change="(on) => set([uploadKey(item.kind)], on)"
					/>
					<template v-for="action in ([['media.edit', 'Change'], ['media.delete', 'Delete']] as const)" :key="action[0]">
						<CapabilityCheck
							v-for="others in [false, true]"
							:key="String(others)"
							:name="others ? `${action[0]}.others` : action[0]"
							:label="`${action[1]} ${others ? 'anyone\'s files' : 'their own files'}`"
							:checked="has(others ? `${action[0]}.others` : action[0])"
							:disabled="locked(others ? `${action[0]}.others` : action[0])"
							:unheld="unheld(others ? `${action[0]}.others` : action[0])"
							@change="(on) => toggleMedia(action[0], others, on)"
						/>
					</template>
				</CapabilitySection>
				<CapabilitySection
					v-else
					:title="group.name"
					:open="open.has(`group-${group.name}`)"
					:body-id="`${id}-group-${group.name}`"
					:sentence="groupSentence(group.capabilities)"
					:changed="changed(group.capabilities.map((capability) => capability.name))"
					:menu-label="readonly ? undefined : `Set everything in ${group.name}`"
					menu-heading="Set this group"
					@toggle="flip(`group-${group.name}`)"
				>
					<template #icon><AdminIcon :name="group.icon" class="section__icon" /></template>
					<template #menu>
						<button type="button" class="menu-item" @click="set(group.capabilities.map((capability) => capability.name), true)">Grant everything in {{ group.name }}</button>
						<button type="button" class="menu-item" @click="set(group.capabilities.map((capability) => capability.name), false)">Remove everything in {{ group.name }}</button>
					</template>
					<CapabilityCheck
						v-for="capability in group.capabilities"
						:key="capability.name"
						:name="capability.name"
						:label="capability.label"
						:checked="has(capability.name)"
						:disabled="locked(capability.name)"
						:unheld="unheld(capability.name)"
						@change="(on) => set([capability.name], on)"
					/>
				</CapabilitySection>
			</template>
		</section>

		<section class="panel" :aria-labelledby="`${id}-content`">
			<header class="panel__header">
				<h2 :id="`${id}-content`">Content Capabilities</h2>
				<p class="panel__hint">One section per content type</p>
			</header>
			<CapabilitySection
				title="Every Type"
				:open="open.has(`type-${EVERY}`)"
				:body-id="`${id}-type-every`"
				:sentence="typeSentence(EVERY)"
				:changed="changed(typeNames(EVERY))"
				key-text="content.*.…"
				note="What's ticked here is granted on every content type, including ones added later, and shows fixed in each type's section below."
				every
				:menu-label="readonly ? undefined : 'Set everything in Every Type'"
				menu-heading="Set every action"
				:presets="PRESETS"
				@toggle="flip(`type-${EVERY}`)"
				@preset="(item) => preset(EVERY, item.actions)"
			>
				<template #icon><AdminIcon name="pin" class="section__icon" /></template>
				<template #marks><span class="index-mark">Includes new types</span></template>
				<template #menu>
					<div class="menu-divider" />
					<button type="button" class="menu-item menu-item--described" :disabled="!ACTIONS.some((item) => has(key(EVERY, item.action)))" @click="spread">
						<span>
							<span class="menu-item__name">Set each type separately</span>
							<span class="menu-item__text">Moves these into every type there is now, so one can differ. Types added later get nothing.</span>
						</span>
					</button>
				</template>
				<CapabilityCheck
					v-for="item in ACTIONS"
					:key="item.action"
					:name="key(EVERY, item.action)"
					:label="item.label"
					:checked="granted(EVERY, item.action)"
					:disabled="locked(key(EVERY, item.action))"
					:unheld="unheld(key(EVERY, item.action))"
					@change="(on) => toggle(EVERY, item.action, on)"
				/>
			</CapabilitySection>
			<template v-for="kind in kinds" :key="kind.key">
				<p class="sections__caption eyebrow">{{ kind.label }}</p>
				<CapabilitySection
					v-for="type in kind.types"
					:key="type.name"
					:title="type.label"
					:open="open.has(`type-${type.name}`)"
					:body-id="`${id}-type-${type.name}`"
					:sentence="typeSentence(type.name)"
					:changed="changed(typeNames(type.name))"
					:key-text="`content.${type.name}.…`"
					:menu-label="readonly || sealed(type.name) ? undefined : `Set everything in ${type.label}`"
					menu-heading="Set every action"
					:presets="PRESETS"
					@toggle="flip(`type-${type.name}`)"
					@preset="(item) => preset(type.name, item.actions)"
				>
					<template #icon><TypeIcon :type="type" class="section__icon" /></template>
					<template v-if="sealed(type.name)" #marks><span class="index-mark">Set by Every Type</span></template>
					<CapabilityCheck
						v-for="item in ACTIONS"
						:key="item.action"
						:name="key(type.name, item.action)"
						:label="item.label"
						:checked="granted(type.name, item.action)"
						:disabled="locked(key(type.name, item.action)) || inherited(type.name, item.action)"
						:from="inherited(type.name, item.action) ? 'every type' : undefined"
						:unheld="unheld(key(type.name, item.action))"
						@change="(on) => toggle(type.name, item.action, on)"
					/>
				</CapabilitySection>
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
}

/* A section's icon, which this file gives it. */
.section__icon {
	color: var(--fg-3);
}

@media (width <= 640px) {
	.sections__note {
		display: none;
	}

	.sections__caption {
		padding-inline: var(--s-4);
	}
}
</style>
