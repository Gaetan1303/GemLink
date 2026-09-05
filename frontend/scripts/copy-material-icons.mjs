import { copyFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const frontendDir = resolve(scriptDir, '..');

const candidates = [
  resolve(frontendDir, 'node_modules/@fontsource/material-icons/files/material-icons-latin-400-normal.woff2'),
  resolve(frontendDir, 'node_modules/@fontsource/material-icons/files/material-icons-latin-400-normal.woff'),
];

const source = candidates.find(existsSync);
if (!source) {
  console.error('[material-icons] Font file not found. Run npm ci/npm install first.');
  process.exit(1);
}

const destination = resolve(frontendDir, 'public/assets/fonts/MaterialIcons-Regular.woff2');
mkdirSync(dirname(destination), { recursive: true });
copyFileSync(source, destination);
console.log(`[material-icons] Copied ${source} -> ${destination}`);
