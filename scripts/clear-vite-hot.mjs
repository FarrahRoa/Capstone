/**
 * Remove public/hot after `vite build` so Laravel @vite uses public/build/manifest.json
 * instead of a stale dev-server URL (which causes the SPA to hang on the Blade loading shell).
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const hot = path.join(__dirname, '..', 'public', 'hot');

try {
    fs.unlinkSync(hot);
} catch (e) {
    if (e.code !== 'ENOENT') throw e;
}
