/**
 * The editor's side of the HTML rules (D-495): whether the signed-in
 * account could add a tag, or an address, as the server checks a save
 * (`HtmlRules`, `HtmlGuard`). The server counts only what a save adds;
 * the editor marks everything the account couldn't add, so HTML someone
 * else wrote shows too.
 */

import { config, type HtmlRules } from './config';
import { can } from './session';
import type { HtmlCheck } from './markdown';

type Access = 'none' | 'allowed' | 'unfiltered';

const TAG       = /^<([A-Za-z][A-Za-z0-9:-]*)((?:\s+[^\s"'>/=]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s"'=<>`]+))?)*)\s*\/?>$/;
const ATTRIBUTE = /([^\s"'>/=]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g;

/**
 * Whether an address could run script: `javascript:`, `vbscript:`,
 * `file:`, or `data:` that isn't a picture.
 */
export function isUnsafe(url: string): boolean {
	const plain = url.replace(/&colon;/gi, ':').replace(/&#0*58;|&#x0*3a;/gi, ':').replace(/[\u0000- ]+/g, '').toLowerCase();

	return /^(?:javascript|vbscript|file):/.test(plain) || (plain.startsWith('data:') && !/^data:image\/(?:png|gif|jpeg|webp)[;,]/.test(plain));
}

function refused(source: string, access: Access, rules: HtmlRules): boolean {
	// A closing tag or a comment adds nothing.
	if (source.startsWith('</') || source.startsWith('<!--')) {
		return false;
	}

	const match = TAG.exec(source);

	if (match === null) {
		return access !== 'unfiltered';
	}

	const name = (match[1] ?? '').toLowerCase();

	if (access === 'none' || rules.refused.includes(name)) {
		return true;
	}

	const allowed = rules.allowed[name];

	if (access === 'allowed' && allowed === undefined) {
		return true;
	}

	for (const part of (match[2] ?? '').matchAll(ATTRIBUTE)) {
		const attribute = (part[1] ?? '').toLowerCase();
		const value     = part[2] ?? part[3] ?? part[4] ?? '';
		const listed    = rules.global.includes(attribute) || (allowed ?? []).includes(attribute) || /^(?:aria|data)-[a-z0-9_.:-]+$/.test(attribute);

		if (attribute.startsWith('on') || rules.refusedAttributes.includes(attribute) || (access === 'allowed' && !listed)) {
			return true;
		}

		if (rules.urlAttributes.includes(attribute) && (attribute === 'srcset' ? value.split(',').some((candidate) => isUnsafe(candidate.trim())) : isUnsafe(value))) {
			return true;
		}
	}

	return false;
}

/**
 * The check the editor's highlighter marks HTML with, for the signed-in
 * account.
 */
export function htmlCheck(): HtmlCheck {
	const access: Access = can('html.unfiltered') ? 'unfiltered' : (can('html.allowed') ? 'allowed' : 'none');

	return { key: access, tag: (source) => refused(source, access, config.html), url: isUnsafe };
}
