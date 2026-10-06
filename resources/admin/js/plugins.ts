/**
 * What the Plugins screen and a plugin's details screen share (D-385):
 * the installed plugins (`GET plugins`), and turning one on or off and
 * deleting one, each with its question, toast, or failure.
 *
 * Turning a plugin on or off saves the local plugins turned on in
 * `user/data/settings.json` (`PUT plugins/{vendor}/{name}`), over
 * `config/plugins.php`, and has the server compile and reindex for it,
 * since plugins' providers run at boot. Deleting removes a folder in
 * `extensions/` (`DELETE plugins/{vendor}/{name}`), a broken plugin's too
 * (D-394).
 */

import { computed } from 'vue';
import type { BrokenPluginSummary, ExtensionDependent, Plugins, PluginSummary } from './api';
import { folderName, list, useExtensionList } from './extensions';

export function usePlugins() {
	const { answer, error, busy, load, turn, deleteFolder, restoreConfig } = useExtensionList<Plugins>('plugin');

	const plugins = computed(() => answer.value?.plugins ?? []);
	const broken  = computed(() => answer.value?.invalid ?? []);

	function find(name: string): PluginSummary | null {
		return plugins.value.find((plugin) => plugin.name === name) ?? null;
	}

	// The extensions, of every kind, that require one (D-431).
	function requiredBy(plugin: PluginSummary): ExtensionDependent[] {
		return plugin.requiredBy;
	}

	// Turns a plugin on or off, loading the list again before the toast.
	// Its Undo turns it back with no Undo of its own; so does an Undo's.
	async function toggle(plugin: PluginSummary, on: boolean, offer = true): Promise<void> {
		await turn(plugin, on, {
			then: load,
			undo: offer ? () => void toggle(find(plugin.name) ?? plugin, !on, false) : undefined
		});
	}

	// Asks, then deletes a broken plugin's folder; resolves whether it
	// was deleted.
	function removeBroken(plugin: BrokenPluginSummary): Promise<boolean> {
		return deleteFolder(folderName(plugin.where), plugin.where);
	}

	// Puts `config/plugins.php`'s list back in charge.
	function useConfig(): Promise<void> {
		return restoreConfig('plugins.enabled', () => 'Using the plugins config/plugins.php turns on and off', 'The plugins couldn\'t be saved.');
	}

	// Asks, then deletes a plugin's folder; resolves whether it was deleted.
	async function remove(plugin: PluginSummary): Promise<boolean> {
		if (plugin.folder === null) {
			return false;
		}

		const body    = ['Content that used what it added keeps its text, without what the plugin drew.'];
		const needing = requiredBy(plugin);

		if (needing.length > 0) {
			body.push(`${list(needing.map((other) => other.label))} require${needing.length === 1 ? 's' : ''} it, and can't be turned on without it.`);
		}

		return deleteFolder(plugin.label, plugin.folder, body);
	}

	return { answer, error, busy, plugins, broken, load, find, requiredBy, toggle, useConfig, remove, removeBroken };
}
