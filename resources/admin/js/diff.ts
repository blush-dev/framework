/**
 * A line diff for comparing two versions of an entry's body: the lines
 * both share, and those only one has. Common lines at the start and end
 * are matched first, so a typical edit leaves a small middle for the
 * longest common subsequence.
 */

export interface DiffLine {
	kind: 'same' | 'theirs' | 'mine';
	text: string;
}

// A diff's line, or a run of unchanged lines left out.
export type Hunk = DiffLine | { kind: 'skip'; count: number };

// What differs between two versions: the fields, and the body's lines
// (null when the bodies match).
export interface Differences {
	rows: { label: string; theirs: string; mine: string }[];
	body: Hunk[] | null;
}

// Past this many line comparisons (2,000 lines against 2,000), the middle
// is shown as replaced rather than matched line by line.
const CELLS = 4_000_000;

export function diffLines(theirs: string, mine: string): DiffLine[] {
	const a = theirs.split('\n');
	const b = mine.split('\n');

	let start = 0;

	while (start < a.length && start < b.length && a[start] === b[start]) {
		start++;
	}

	let endA = a.length;
	let endB = b.length;

	while (endA > start && endB > start && a[endA - 1] === b[endB - 1]) {
		endA--;
		endB--;
	}

	const same = (lines: string[]): DiffLine[] => lines.map((text) => ({ kind: 'same', text }));

	return [...same(a.slice(0, start)), ...middle(a.slice(start, endA), b.slice(start, endB)), ...same(a.slice(endA))];
}

function middle(a: string[], b: string[]): DiffLine[] {
	if (a.length * b.length > CELLS) {
		return [...a.map((text): DiffLine => ({ kind: 'theirs', text })), ...b.map((text): DiffLine => ({ kind: 'mine', text }))];
	}

	// lengths[i][j]: the longest common subsequence of a[i..] and b[j..].
	const lengths = Array.from({ length: a.length + 1 }, () => new Uint32Array(b.length + 1));

	for (let i = a.length - 1; i >= 0; i--) {
		for (let j = b.length - 1; j >= 0; j--) {
			lengths[i]![j] = a[i] === b[j] ? lengths[i + 1]![j + 1]! + 1 : Math.max(lengths[i + 1]![j]!, lengths[i]![j + 1]!);
		}
	}

	const lines: DiffLine[] = [];
	let i = 0;
	let j = 0;

	while (i < a.length && j < b.length) {
		if (a[i] === b[j]) {
			lines.push({ kind: 'same', text: a[i]! });
			i++;
			j++;
		} else if (lengths[i + 1]![j]! >= lengths[i]![j + 1]!) {
			lines.push({ kind: 'theirs', text: a[i]! });
			i++;
		} else {
			lines.push({ kind: 'mine', text: b[j]! });
			j++;
		}
	}

	return [...lines, ...a.slice(i).map((text): DiffLine => ({ kind: 'theirs', text })), ...b.slice(j).map((text): DiffLine => ({ kind: 'mine', text }))];
}

/**
 * Leaves out long runs of unchanged lines, keeping two on each side of a
 * change.
 */
export function hunks(lines: DiffLine[]): Hunk[] {
	const near = lines.map((_, index) => lines.slice(Math.max(0, index - 2), index + 3).some((line) => line.kind !== 'same'));
	const out: Hunk[] = [];

	lines.forEach((line, index) => {
		if (near[index] === true) {
			out.push(line);

			return;
		}

		const last = out.at(-1);

		if (last !== undefined && last.kind === 'skip') {
			last.count++;
		} else {
			out.push({ kind: 'skip', count: 1 });
		}
	});

	return out;
}
