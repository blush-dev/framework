/**
 * Entries' statuses (D-850): core's and those plugins register, with
 * their labels and tones, from the shell's config.
 */

import { config, type StatusDefinition } from './config';

const byName = new Map(config.statuses.map((status) => [status.name, status]));

/**
 * Returns a status by name, or one made for a name nothing registers
 * (a plugin that's off): not live, and named by itself.
 */
export function statusOf(name: string): StatusDefinition {
	return byName.get(name) ?? { name, label: name, plural: name, live: false, active: true, tone: 'neutral', known: false };
}

/**
 * Returns the pill class for a status's tone: none for neutral.
 */
export function pillKind(status: StatusDefinition): string {
	return status.tone === 'neutral' ? '' : `pill--${status.tone}`;
}
