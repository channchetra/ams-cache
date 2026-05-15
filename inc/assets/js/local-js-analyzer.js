const fs = require('fs');

const inputPath = process.argv[2];
const outputPath = process.argv[3];

if (!inputPath || !outputPath) {
	process.stderr.write('Missing input or output path.\n');
	process.exit(1);
}

const riskyPatterns = [
	{
		name: 'document.write',
		pattern: /\bdocument\.write\s*\(/i
	},
	{
		name: 'document.currentScript',
		pattern: /\bdocument\.currentScript\b/i
	},
	{
		name: 'synchronous XHR',
		pattern: /\bXMLHttpRequest\b[\s\S]{0,220}\.open\s*\([^)]*,\s*false\s*\)/i
	},
	{
		name: 'document.readyState',
		pattern: /\bdocument\.readyState\b/i
	},
	{
		name: 'parser-sensitive current node',
		pattern: /\bdocument\.(?:scripts|body|head)\b/i
	}
];

let payload;

try {
	payload = JSON.parse(fs.readFileSync(inputPath, 'utf8'));
} catch (error) {
	process.stderr.write(`Invalid input: ${error.message}\n`);
	process.exit(1);
}

const scripts = Array.isArray(payload.scripts) ? payload.scripts : [];
const result = scripts.map((script) => {
	const content = String(script.content || '');
	const reasons = riskyPatterns
		.filter((entry) => entry.pattern.test(content))
		.map((entry) => entry.name);

	return {
		src: String(script.src || ''),
		safeToDefer: content.trim() !== '' && reasons.length === 0,
		reasons
	};
});

fs.writeFileSync(
	outputPath,
	JSON.stringify({
		scripts: result,
		analyzed: result.length,
		safeToDefer: result.filter((script) => script.safeToDefer).length
	})
);
