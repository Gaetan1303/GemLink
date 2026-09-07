import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { resolve } from 'node:path';
import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const frontendDir = resolve(scriptDir, '..');
const outDir = resolve(frontendDir, 'browser');
const indexPath = resolve(outDir, 'index.html');
const iconFontPath = resolve(outDir, 'assets/fonts/MaterialIcons-Regular.woff2');

const fail = (message) => {
  console.error(`[build-check] ${message}`);
  process.exit(1);
};

if (!existsSync(indexPath)) fail('browser/index.html is missing.');
const html = readFileSync(indexPath, 'utf8');
if (!/<base\s+href=["']\/["']\s*\/?\s*>/i.test(html)) {
  fail('index.html must contain <base href="/"> for Cloudflare root deployment.');
}
if (!existsSync(iconFontPath) || statSync(iconFontPath).size < 1000) {
  fail('MaterialIcons-Regular.woff2 is missing or invalid in the production assets.');
}
const js = readdirSync(outDir).filter((name) => name.endsWith('.js'));
if (js.length === 0) fail('No JavaScript bundles were emitted at the site root.');
console.log(`[build-check] OK — ${js.length} JS bundles, root base href, local Material Icons font.`);
