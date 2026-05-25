import fs from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';

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

async function main() {
	const args = readArgs(process.argv.slice(2));

	if (args.check) {
		process.stdout.write(JSON.stringify({
			ok: true,
			sharp: sharp.versions.sharp,
			webp: Boolean(sharp.format.webp?.output?.file),
			avif: Boolean(sharp.format.heif?.output?.file && sharp.format.heif?.output?.alias?.includes('avif'))
		}));
		return;
	}

	const input = String(args.input || '');
	const output = String(args.output || '');
	const uploads = String(args.uploads || '');
	const format = String(args.format || '').toLowerCase();
	const quality = Math.max(1, Math.min(100, Number.parseInt(args.quality || '82', 10) || 82));

	if (!input || !output || !uploads || !['webp', 'avif'].includes(format)) {
		throw new Error('Missing input, output, uploads, or valid format.');
	}

	await assertInsideUploads(input, output, uploads);
	await fs.mkdir(path.dirname(output), { recursive: true });

	let pipeline = sharp(input, {
		failOn: 'none',
		limitInputPixels: 268402689
	}).rotate();

	if (format === 'webp') {
		pipeline = pipeline.webp({ quality, effort: 4 });
	} else {
		pipeline = pipeline.avif({ quality, effort: 4 });
	}

	await pipeline.toFile(output);

	const [sourceStat, targetStat] = await Promise.all([
		fs.stat(input),
		fs.stat(output)
	]);

	process.stdout.write(JSON.stringify({
		ok: true,
		format,
		sourceBytes: sourceStat.size,
		targetBytes: targetStat.size,
		savedBytes: Math.max(0, sourceStat.size - targetStat.size)
	}));
}

main().catch((error) => {
	process.stderr.write(`${error.message}\n`);
	process.exit(1);
});
