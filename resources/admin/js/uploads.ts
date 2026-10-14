/**
 * Media's upload rules (D-406), as the Media settings screen edits them:
 * what every file does (All Files), and what each kind does of its own.
 * A kind's empty box takes All Files' value. The rules travel as
 * `MediaUploads::toArray()` writes them; the form holds them as text, one
 * box each, so a half-typed size stays as typed.
 */

import type { UploadsInfo } from './api';
import { t } from './messages';

/**
 * One row of the grid, as its boxes hold it.
 */
export interface UploadRow {
	enabled: boolean;
	size: string;
	path: string;
}

/**
 * The grid: All Files, then each kind by key.
 */
export interface UploadGrid {
	all: UploadRow;
	kinds: Record<string, UploadRow>;
}

interface Rule {
	enabled?: unknown;
	maxSize?: unknown;
	path?: unknown;
}

/**
 * The grid from the rules the server sends.
 */
export function toGrid(input: unknown, info: UploadsInfo): UploadGrid {
	const rules = (typeof input === 'object' && input !== null ? input : {}) as Rule & { kinds?: Record<string, Rule> };
	const row   = (rule: Rule | undefined, all: boolean): UploadRow => ({
		enabled: rule?.enabled !== false,
		size: typeof rule?.maxSize === 'number' ? String(rule.maxSize) : '',
		path: typeof rule?.path === 'string' ? rule.path : (all ? '{year}/{month}' : '')
	});

	return {
		all: row(rules, true),
		kinds: Object.fromEntries(info.kinds.map((kind) => [kind.key, row(rules.kinds?.[kind.key], false)]))
	};
}

/**
 * The rules to save, from the grid. A size that isn't a whole number
 * is sent as typed, so the server says what's wrong with it.
 */
export function fromGrid(grid: UploadGrid): Record<string, unknown> {
	const size = (text: string): number | string | null => {
		const trimmed = text.trim();

		return trimmed === '' ? null : (/^\d+$/.test(trimmed) ? Number(trimmed) : trimmed);
	};
	const kinds: Record<string, unknown> = {};

	for (const [key, row] of Object.entries(grid.kinds)) {
		const path = row.path.trim();

		if (!row.enabled || row.size.trim() !== '' || path !== '') {
			kinds[key] = { enabled: row.enabled, maxSize: size(row.size), path: path === '' ? null : path };
		}
	}

	return { enabled: grid.all.enabled, maxSize: size(grid.all.size), path: grid.all.path.trim(), kinds };
}

/**
 * A row's size as a problem, if it has one.
 */
export function sizeProblem(text: string, serverLimit: number | null): string | null {
	const trimmed = text.trim();

	if (trimmed === '') {
		return null;
	}

	if (!/^\d+$/.test(trimmed) || Number(trimmed) < 1) {
		return t('uploads.size_whole', 'A whole number of megabytes, 1 or more.');
	}

	// The box can't be typed past it; a value saved before the server's
	// limit went down can be.
	return serverLimit !== null && Number(trimmed) * 1024 * 1024 > serverLimit ? t('uploads.size_over', 'Over the server\'s {size} MB, so it won\'t hold.', { size: megabytes(serverLimit) }) : null;
}

/**
 * Bytes as whole megabytes, rounded down.
 */
export function megabytes(bytes: number): number {
	return Math.floor(bytes / (1024 * 1024));
}

/**
 * Where a file would go: a pattern's tokens filled in for an example.
 */
export function expand(pattern: string, info: UploadsInfo, kind: string, file: string): string {
	const folder = info.kinds.find((item) => item.key === kind)?.folder ?? kind;
	const path   = pattern
		.replaceAll('{year}', info.now.year)
		.replaceAll('{month}', info.now.month)
		.replaceAll('{day}', info.now.day)
		.replaceAll('{kind}', folder)
		.replace(/\/{2,}/g, '/')
		.replace(/^\/|\/$/g, '');

	return `${path === '' ? '' : `${path}/`}${file}`;
}

/**
 * The pattern a kind uses: its own, or All Files'.
 */
export function patternOf(grid: UploadGrid, kind: string): string {
	return grid.kinds[kind]?.path.trim() || grid.all.path.trim();
}

/**
 * What the grid does, in a line, for the panel's header.
 */
export function summary(grid: UploadGrid): string {
	if (!grid.all.enabled) {
		return t('uploads.summary_off', 'No files can be uploaded');
	}

	const rows = Object.values(grid.kinds);
	const own  = rows.filter((row) => row.enabled && (row.size.trim() !== '' || row.path.trim() !== '')).length;
	const off  = rows.filter((row) => !row.enabled).length;
	const parts = [
		...(own > 0 ? [t('uploads.summary_own', '{count, plural, one {# kind set on its own} other {# kinds set on their own}}', { count: own })] : []),
		...(off > 0 ? [t('uploads.summary_off_kinds', '{count, plural, one {# kind turned off} other {# kinds turned off}}', { count: off })] : [])
	];

	return parts.length > 0 ? parts.join(' · ') : t('uploads.summary_all', 'Every kind follows All Files');
}
