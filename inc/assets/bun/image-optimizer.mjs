import { promises as fs } from 'fs';
import path from 'path';

function readArgs(argv) {
	const args = {};

	for (let index = 0; index < argv.length; index += 1) {
		const item = argv[index];

		if (!item.startsWith('--')) {
			continue;
		}

		const key = item.slice(2);
		const next = argv[index + 1];

		if (!next || next.startsWith('--')) {
			args[key] = true;
		} else {
			args[key] = next;
			index += 1;
		}
	}

	return args;
}

function ensureBunRuntime() {
	if (typeof Bun === 'undefined' || typeof Bun.file !== 'function') {
		throw new Error('Bun runtime is required for AMS Cache image optimizer.');
	}

	const probe = Bun.file(process.argv[1] || '.');

	if (typeof probe.image !== 'function' && typeof Bun.Image === 'undefined') {
		throw new Error('Bun Image API is not available in this Bun version.');
	}
}

async function realpathSafe(value) {
	try {
		return await fs.realpath(value);
	} catch {
		return '';
	}
}

async function assertInsideUploads(input, output, uploads) {
	const uploadRoot = await realpathSafe(uploads);
	const inputPath = await realpathSafe(input);
	const outputDir = await realpathSafe(path.dirname(output));

	if (!uploadRoot || !inputPath || !outputDir) {
		throw new Error('Missing readable input, output directory, or uploads root.');
	}

	const root = uploadRoot.endsWith(path.sep) ? uploadRoot : `${uploadRoot}${path.sep}`;
	const isInsideUploads = (candidate) => candidate === uploadRoot || candidate.startsWith(root);

	if (!isInsideUploads(inputPath) || !isInsideUploads(outputDir)) {
		throw new Error('Input and output must stay inside the WordPress uploads directory.');
	}
}

async function buildImagePipeline(input, quality) {
	return Bun.file(input).image().webp({ quality });
}

async function main() {
	ensureBunRuntime();
	const args = readArgs(process.argv.slice(2));

	if (args.check) {
		process.stdout.write(JSON.stringify({
			ok: true,
			engine: 'bun-image',
			bun: Bun.version,
			platform: process.platform,
			webp: true,
			placeholder: true
		}));
		return;
	}

	const input = String(args.input || '');
	const output = String(args.output || '');
	const uploads = String(args.uploads || '');
	const format = String(args.format || '').toLowerCase();
	const quality = Math.max(1, Math.min(100, Number.parseInt(args.quality || '82', 10) || 82));
	const includePlaceholder = args.placeholder !== false && args.placeholder !== 'no';

	if (!input || !output || !uploads || format !== 'webp') {
		throw new Error('Missing input, output, uploads, or valid WebP format.');
	}

	await fs.mkdir(path.dirname(output), { recursive: true });
	await assertInsideUploads(input, output, uploads);

	const pipeline = await buildImagePipeline(input, quality);
	await pipeline.write(output);

	const [sourceStat, targetStat] = await Promise.all([
		fs.stat(input),
		fs.stat(output)
	]);

	let placeholder = '';

	if (includePlaceholder) {
		try {
			placeholder = await Bun.file(input).image().placeholder();
		} catch {
			placeholder = '';
		}
	}

	process.stdout.write(JSON.stringify({
		ok: true,
		engine: 'bun-image',
		format,
		sourceBytes: sourceStat.size,
		targetBytes: targetStat.size,
		savedBytes: Math.max(0, sourceStat.size - targetStat.size),
		placeholder
	}));
}

main().catch((error) => {
	process.stderr.write(`${error.message}\n`);
	process.exit(1);
});
