/**
 * What every extension screen shares: the installed extensions of a kind,
 * loaded, turned on or off, deleted, and put back in config's charge
 * (D-385, D-509); their addresses and folders; and their requirements
 * (D-431): plugins, themes, and icon packs each list their `require`,
 * checked the same way, and the extensions of any kind that require them;
 * and the filters over each list (D-565).
 */

import { computed, ref, type Ref } from 'vue';
import { errorMessage, refreshIfAsked, request, saveSettings, type ExtensionDependent, type ExtensionRequirement } from './api';
import { confirmAction } from './confirm';
import { loadCounts } from './counts';
import type { IconName } from './icons';
import { useQueryState } from './query';
import { toast } from './toast';

export type ExtensionKind = ExtensionDependent['kind'];

// Each kind's part of the API's paths, its list screen's route name, and
// its capabilities' (`extensions.{kind}.{action}`, D-389).
export const KIND_PATHS: Record<ExtensionKind, string> = {
	'theme': 'themes',
	'plugin': 'plugins',
	'icon-pack': 'icon-packs'
};

// Each kind's glyph, as the Extend panel draws it, for a row naming an
// extension of that kind (D-565).
export const KIND_ICONS: Record<ExtensionKind, IconName> = {
	'theme': 'paintbrush',
	'plugin': 'plug',
	'icon-pack': 'shapes'
};

// What turning an extension on or off needs of it.
interface Switchable {
	name: string;
	label: string;
	stops: ExtensionDependent[];
}

interface SwitchOptions {
	// Run after the save, before the toast: loading the list again.
	then?: () => Promise<void>;
	// The toast's words, given what started or stopped with it.
	message?: (also: string) => string;
	// Turns it back, offered as the toast's Undo when nothing else
	// started or stopped, since turning the one back wouldn't put the
	// others back.
	undo?: () => void;
}

// A kind's installed extensions (`GET {kind}`), with what its screens do
// to one, each with its question, toast, or failure. `busy` is the one
// being turned on or off (or activated).
export function useExtensionList<T>(kind: ExtensionKind) {
	const answer = ref(null) as Ref<T | null>;
	const error  = ref('');
	const busy   = ref<string | null>(null);
	const path   = KIND_PATHS[kind];

	async function load(): Promise<void> {
		try {
			answer.value = await request<T>('GET', `/${path}`);
			error.value  = '';
		} catch (caught) {
			error.value = errorMessage(caught, `The ${path.replace('-', ' ')} couldn't be loaded.`);
		}
	}

	// Turns one on or off (`PUT {kind}/{vendor}/{name}`), asking first
	// when turning it on would stop others (D-440), and saying what
	// happened, naming the other extensions, of any kind, that started or
	// stopped with it (D-431); resolves whether it was saved.
	async function turn(extension: Switchable, on: boolean, options: SwitchOptions = {}): Promise<boolean> {
		if (on && extension.stops.length > 0 && !await confirmAction({ title: `Turn on ${extension.label}?`, body: stopsParagraph(extension.stops), confirm: `Turn On ${extension.label}` })) {
			return false;
		}

		busy.value = extension.name;

		try {
			const saved  = await request<{ started: string[]; stopped: string[]; refresh: boolean }>('PUT', `/${path}/${extension.name}`, { enabled: on });
			const others = on ? saved.started : saved.stopped;
			const also   = others.length === 0 ? '' : `, and ${list(others)} ${on ? 'started' : 'stopped'} with it`;

			await refreshIfAsked(saved);
			await options.then?.();
			toast(options.message?.(also) ?? `Turned ${on ? 'on' : 'off'} ${extension.label}${also}`, {
				kind: on ? 'good' : 'danger',
				undo: others.length === 0 ? options.undo : undefined
			});

			return true;
		} catch (caught) {
			toast(errorMessage(caught, `${extension.label} couldn't be turned ${on ? 'on' : 'off'}`), { kind: 'warn' });

			return false;
		} finally {
			busy.value = null;
		}
	}

	// Asks, then deletes a folder in `extensions/` (`DELETE
	// {kind}/{vendor}/{name}`), with what else deleting it does after the
	// first paragraph; resolves whether it was deleted.
	async function deleteFolder(label: string, folder: string, more: string[] = []): Promise<boolean> {
		const body = [`The folder **${folder}** and everything in it is removed from the server. This can't be undone.`, ...more];

		if (!await confirmAction({ title: `Delete ${label}?`, body, confirm: `Delete ${label}`, danger: true })) {
			return false;
		}

		try {
			await request('DELETE', `/${path}/${folderPath(folder)}`);
			void loadCounts();
			toast(`Deleted ${label}`, { kind: 'danger' });

			return true;
		} catch (caught) {
			error.value = errorMessage(caught, `${label} couldn't be deleted.`);

			return false;
		}
	}

	// Puts the kind's config file back in charge, by unsetting what
	// `user/data/settings.json` saved over it, then loads the list again
	// and says so.
	async function restoreConfig(setting: string, said: () => string, failure: string): Promise<void> {
		try {
			await saveSettings({ unset: [setting] });
			await load();
			toast(said());
		} catch (caught) {
			error.value = errorMessage(caught, failure);
		}
	}

	return { answer, error, busy, load, turn, deleteFolder, restoreConfig };
}

// An extension's details screen's address, by its kind:
// `/plugins/{vendor}/{name}`, `/themes/…`, or `/icon-packs/…`.
export function extensionRoute(kind: ExtensionKind, name: string): { name: ExtensionKind; params: { vendor: string; name: string } } {
	const [vendor = '', short = ''] = name.split('/');

	return { name: kind, params: { vendor, name: short } };
}

// The name a folder in `extensions/` gives (its last two parts,
// `acme/hello`; D-418), which `DELETE {kind}/{vendor}/{name}` takes.
export function folderName(folder: string): string {
	return folder.split('/').slice(-2).join('/');
}

// A folder's name as a URL path, each part encoded.
function folderPath(folder: string): string {
	return folderName(folder).split('/').map(encodeURIComponent).join('/');
}

// The kind of an installed extension a requirement names, or `null`.
export function requirementKind(requirement: ExtensionRequirement): ExtensionKind | null {
	return requirement.kind === 'plugin' || requirement.kind === 'theme' || requirement.kind === 'icon-pack' ? requirement.kind : null;
}

// A requirement as a person reads it: `Blush ^2.0`, `PHP Extension:
// intl`, `Shop ^2.0`.
export function requirementText(requirement: ExtensionRequirement): string {
	const constraint = requirement.constraint === '*' ? '' : ` ${requirement.constraint}`;

	switch (requirement.kind) {
		case 'blush':
			return `Blush${constraint}`;
		case 'php':
			return `PHP${constraint}`;
		case 'extension':
			return `PHP Extension: ${requirement.name.slice(4)}${constraint}`;
		case 'plugin':
		case 'theme':
		case 'icon-pack':
			return `${requirement.label || requirement.name}${constraint}`;
		default:
			return `${requirement.name}${constraint}`;
	}
}

// Names joined as a sentence says them: "A", "A and B", "A, B, and C".
export function list(names: string[]): string {
	if (names.length < 3) {
		return names.join(' and ');
	}

	return `${names.slice(0, -1).join(', ')}, and ${names[names.length - 1] ?? ''}`;
}

// What turning an extension on stops (D-440), as a confirmation's
// paragraph: the extensions that conflict with it or replace it can't run
// alongside it, and neither can what needs one of them. Empty when nothing
// stops.
export function stopsParagraph(stops: ExtensionDependent[]): string {
	if (stops.length === 0) {
		return '';
	}

	const names = list(stops.map((other) => `**${other.label}**`));

	return `It also stops ${names}. An extension that conflicts with it or replaces it can't run alongside it, and neither can one that needs it.`;
}


// Where an extension came from: a Composer package, a folder in
// `extensions/`, or Blush itself (the default theme, the core icons).
export type ExtensionSource = 'composer' | 'local' | 'framework';

// What a list's filters read of an extension (D-565): whether it's on (a
// theme: active), whether it needs attention (it can't run or turn on,
// it's broken, or it's abandoned), where it came from, and the words a
// filter finds it by (its label, name, description, and keywords).
export interface Filterable {
	on: boolean;
	attention: boolean;
	source: ExtensionSource;
	words: (string | null | undefined)[];
}

// A list's filters (D-565), in its address (D-505): `q` (words, matched
// at the start of a word, so "ai" doesn't find "mailing"), `status`
// (`on`, `off`, or `attention`), and `source`.
export function useExtensionFilter() {
	const { text, set } = useQueryState();

	const query  = computed({ get: () => text('q'), set: (value: string) => set({ q: value }) });
	const status = computed({ get: () => text('status'), set: (value: string) => set({ status: value }) });
	const source = computed({ get: () => text('source'), set: (value: string) => set({ source: value }) });

	const filtered = computed(() => query.value.trim() !== '' || status.value !== '' || source.value !== '');

	const pattern = computed(() => {
		const words = query.value.trim();

		return words === '' ? null : new RegExp(`(^|[^\\p{L}\\p{N}])${words.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`, 'iu');
	});

	function matches(item: Filterable): boolean {
		if (status.value === 'attention' ? !item.attention : (status.value !== '' && item.on !== (status.value === 'on'))) {
			return false;
		}

		if (source.value !== '' && item.source !== source.value) {
			return false;
		}

		return pattern.value === null || pattern.value.test(item.words.filter(Boolean).join(' '));
	}

	function clear(): void {
		set({ q: '', status: '', source: '' });
	}

	return { query, status, source, filtered, matches, clear };
}
