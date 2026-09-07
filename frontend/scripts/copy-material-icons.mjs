import { copyFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const frontendDir = resolve(scriptDir, '..');
const source = resolve(
  frontendDir,
  'node_modules/@fontsource/material-icons/files/material-icons-latin-400-normal.woff2',
);

if (!existsSync(source)) {
  console.error('[material-icons] Expected local WOFF2 font is missing. Run npm ci first.');
  process.exit(1);
}

const destination = resolve(frontendDir, 'public/assets/fonts/MaterialIcons-Regular.woff2');
mkdirSync(dirname(destination), { recursive: true });
copyFileSync(source, destination);
console.log(`[material-icons] Copied ${source} -> ${destination}`);
