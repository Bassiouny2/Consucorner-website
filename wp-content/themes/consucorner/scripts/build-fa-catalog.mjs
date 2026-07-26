import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const cssPath = path.resolve(
	__dirname,
	'../../../plugins/dokan-lite/assets/vendors/font-awesome/css/font-awesome.min.css'
);
const outDir = path.resolve(__dirname, '../assets/admin');
const outPath = path.join(outDir, 'fa-icon-catalog.json');

const css = fs.readFileSync(cssPath, 'utf8');
const skip = new Set([
	'solid', 'regular', 'brands', '1x', '2x', '3x', '4x', '5x', '6x', '7x', '8x', '9x', '10x',
	'2xs', 'xs', 'sm', 'lg', 'xl', '2xl', 'fw', 'ul', 'li', 'border', 'pull-left', 'pull-right',
	'beat', 'bounce', 'fade', 'flip', 'shake', 'spin', 'pulse', 'spin-pulse', 'stack', 'inverse',
	'sr-only', 'sr-only-focusable',
]);
const names = new Set();
const re = /\.fa-([a-z0-9-]+):before\{content:"\\/g;
let m;
while ((m = re.exec(css)) !== null) {
	const n = m[1];
	if (!skip.has(n) && !/^\d+$/.test(n)) {
		names.add(n);
	}
}
const icons = [...names].sort().map((n) => ({
	class: `fa-solid fa-${n}`,
	label: n.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
	terms: n,
}));
fs.mkdirSync(outDir, { recursive: true });
fs.writeFileSync(outPath, JSON.stringify(icons));
console.log(`Wrote ${icons.length} icons to ${outPath}`);
