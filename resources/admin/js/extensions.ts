/**
 * What every extension screen shares about requirements (D-431):
 * plugins, themes, and icon packs each list their `require`, checked the
 * same way, and the extensions of any kind that require them.
 */

import type { ExtensionDependent, ExtensionRequirement } from './api';

type ExtensionKind = ExtensionDependent['kind'];

// An extension's details screen's address, by its kind:
// `/plugins/{vendor}/{name}`, `/themes/…`, or `/icon-packs/…`.
export function extensionRoute(kind: ExtensionKind, name: string): { name: ExtensionKind; params: { vendor: string; name: string } } {
	const [vendor = '', short = ''] = name.split('/');

	return { name: kind, params: { vendor, name: short } };
}

// The kind of an installed extension a requirement names, or `null`.
export function requirementKind(requirement: ExtensionRequirement): ExtensionKind | null {
	return requirement.kind === 'plugin' || requirement.kind === 'theme' || requirement.kind === 'icon-pack' ? requirement.kind : null;
}

// A requirement as a person reads it: `Blush ^2.0`, `the PHP extension
// intl`, `Shop ^2.0`.
export function requirementText(requirement: ExtensionRequirement): string {
	const constraint = requirement.constraint === '*' ? '' : ` ${requirement.constraint}`;

	switch (requirement.kind) {
		case 'blush':
			return `Blush${constraint}`;
		case 'php':
			return `PHP${constraint}`;
		case 'extension':
			return `the PHP extension ${requirement.name.slice(4)}${constraint}`;
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

