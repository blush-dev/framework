/**
 * What the Plugins screen and a plugin's details screen share (D-385):
 * the installed plugins (`GET plugins`), and turning one on or off and
 * deleting one, each with its question, toast, or failure.
 *
 * Turning a plugin on or off saves the local plugins turned on in
 * `user/data/settings.json` (`PUT plugins/{vendor}/{name}`), over
 * `config/plugins.php`, and has the server compile and reindex for it,
 * since plugins' providers run at boot. Deleting removes a folder in
 * `user/plugins` (`DELETE plugins/{folder}`).
 */

import { computed, ref } from 'vue';
import { ApiError, request, type PluginRequirement, type Plugins, type PluginSummary } from './api';
import { confirmAction } from './confirm';
import { loadCounts } from './counts';
import { toast } from './toast';
import { folderName } from './themes';

export function usePlugins() {
	const answer = ref<Plugins | null>(null);
	const error  = ref('');
	// The plugin being turned on or off.
	const busy   = ref<string | null>(null);

	const plugins = computed(() => answer.value?.plugins ?? []);

	async function load(): Promise<void> {
		try {
			answer.value = await request<Plugins>('GET', '/plugins');
			error.value  = '';
		} catch (caught) {
			error.value = caught instanceof ApiError ? caught.message : 'The plugins couldn\'t be loaded.';
		}
	}

	function find(name: string): PluginSummary | null {
		return plugins.value.find((plugin) => plugin.name === name) ?? null;
	}

	// The plugins that require one.
	function requiredBy(plugin: PluginSummary): PluginSummary[] {
		return plugin.requiredBy.map(find).filter((other): other is PluginSummary => other !== null);
	}

	// Turns a plugin on or off, saying what happened, and which other
	// plugins started or stopped with it. The toast offers an Undo only
	// when nothing else started or stopped, since turning the one plugin
	// back wouldn't put the others back; the reverse offers none.
	async function toggle(plugin: PluginSummary, on: boolean, offer = true): Promise<void> {
		busy.value = plugin.name;

		try {
			const saved = await request<{ started: string[]; stopped: string[]; refresh: boolean }>('PUT', `/plugins/${plugin.name}`, { enabled: on });

			if (saved.refresh) {
				await request('POST', '/settings/refresh').catch(() => undefined);
			}

			await load();

			const others = on ? saved.started : saved.stopped;
			const also   = others.length === 0 ? '' : `, and ${list(others)} ${on ? 'started' : 'stopped'} with it`;

			toast(`Turned ${on ? 'on' : 'off'} ${plugin.label}${also}`, {
				kind: on ? 'good' : 'danger',
				undo: offer && others.length === 0 ? () => void toggle(find(plugin.name) ?? plugin, !on, false) : undefined
			});
		} catch (caught) {
			toast(caught instanceof ApiError ? caught.message : `${plugin.label} couldn't be turned ${on ? 'on' : 'off'}`, { kind: 'warn' });
		} finally {
			busy.value = null;
		}
	}

	// Puts `config/plugins.php`'s list back in charge.
	async function useConfig(): Promise<void> {
		try {
			const saved = await request<{ refresh: boolean }>('PATCH', '/settings', { unset: ['plugins.enabled'] });

			if (saved.refresh) {
				await request('POST', '/settings/refresh').catch(() => undefined);
			}

			await load();
			toast('Using the plugins config/plugins.php turns on and off');
		} catch (caught) {
			error.value = caught instanceof ApiError ? caught.message : 'The plugins couldn\'t be saved.';
		}
	}

	// Asks, then deletes a plugin's folder; resolves whether it was deleted.
	async function remove(plugin: PluginSummary): Promise<boolean> {
		if (plugin.folder === null) {
			return false;
		}

		const body    = [`The folder **${plugin.folder}** and everything in it is removed from the server. This can't be undone.`];
		const needing = requiredBy(plugin);

		body.push('Content that used what it added keeps its text, without what the plugin drew.');

		if (needing.length > 0) {
			body.push(`${list(needing.map((other) => other.label))} require${needing.length === 1 ? 's' : ''} it, and can't be turned on without it.`);
		}

		if (!await confirmAction({ title: `Delete ${plugin.label}?`, body, confirm: `Delete ${plugin.label}`, danger: true })) {
			return false;
		}

		try {
			await request('DELETE', `/plugins/${encodeURIComponent(folderName(plugin.folder))}`);
			void loadCounts();
			toast(`Deleted ${plugin.label}`, { kind: 'danger' });

			return true;
		} catch (caught) {
			error.value = caught instanceof ApiError ? caught.message : `${plugin.label} couldn't be deleted.`;

			return false;
		}
	}

	return { answer, error, busy, plugins, load, find, requiredBy, toggle, useConfig, remove };
}

// A plugin's details screen's address: `/plugins/{vendor}/{name}`.
export function pluginRoute(name: string): { name: 'plugin'; params: { vendor: string; name: string } } {
	const [vendor = '', short = ''] = name.split('/');

	return { name: 'plugin', params: { vendor, name: short } };
}

// A requirement as a person reads it: `Blush ^2.0`, `the PHP extension
// intl`, `Shop ^2.0`.
export function requirementText(requirement: PluginRequirement): string {
	const constraint = requirement.constraint === '*' ? '' : ` ${requirement.constraint}`;

	switch (requirement.kind) {
		case 'blush':
			return `Blush${constraint}`;
		case 'php':
			return `PHP${constraint}`;
		case 'extension':
			return `the PHP extension ${requirement.name.slice(4)}${constraint}`;
		case 'plugin':
			return `${requirement.label || requirement.name}${constraint}`;
		default:
			return `${requirement.name}${constraint}`;
	}
}

// Names joined as a sentence says them: "A", "A and B", "A, B, and C".
function list(names: string[]): string {
	if (names.length < 3) {
		return names.join(' and ');
	}

	return `${names.slice(0, -1).join(', ')}, and ${names[names.length - 1] ?? ''}`;
}
