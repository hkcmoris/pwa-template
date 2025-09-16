import { execSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

function loadServerEnvForProductionBuild() {
  try {
    const file = resolve(process.cwd(), '.env.production');
    if (!existsSync(file)) return;
    const preferred = new Set(['APP_BASE', 'VITE_API_BASE_URL']);
    const lines = readFileSync(file, 'utf8').split(/\r?\n/);
    for (const raw of lines) {
      const line = raw.trim();
      if (!line || line.startsWith('#')) continue;
      const m = line.match(/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/);
      if (!m) continue;
      const key = m[1];
      if (!preferred.has(key)) continue;
      let value = m[2].trim();
      if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith('\'') && value.endsWith('\''))) {
        value = value.slice(1, -1);
      }
      if (!process.env[key]) process.env[key] = value;
    }
  } catch {}
}

// Ensure Vite sees APP_BASE and VITE_API_BASE_URL from server/.env.production
loadServerEnvForProductionBuild();

// type-check without emitting files
execSync('tsc --noEmit', { stdio: 'inherit' });

// run vite build
execSync('vite build', { stdio: 'inherit' });

// After build, version the Service Worker by copying to sw-<BUILD_HASH>.js
import { readdirSync, copyFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

try {
  const manifestPath = resolve(process.cwd(), 'server/public/assets/.vite/manifest.json');
  const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
  const main = manifest['src/main.ts'];
  if (!main || !main.file) {
    throw new Error('Cannot locate src/main.ts in manifest to derive build hash');
  }
  const m = /main-([^.]+)\.js$/.exec(main.file);
  if (!m) {
    throw new Error(`Unexpected main entry filename: ${main.file}`);
  }
  const buildHash = m[1];

  const serverDir = resolve(process.cwd(), 'server');
  const swSource = resolve(serverDir, 'sw.js');
  const destName = `sw-${buildHash}.js`;
  const swDest = resolve(serverDir, destName);

  // Remove old versioned SW files to keep the directory clean
  for (const name of readdirSync(serverDir)) {
    if (name.startsWith('sw-') && name.endsWith('.js')) {
      try { execSync(process.platform === 'win32' ? `del /f /q "${join(serverDir, name)}"` : `rm -f "${join(serverDir, name)}"`); } catch {}
    }
  }

  // Create a versioned copy and stamp CACHE_NAME to include the build hash
  let swCode = readFileSync(swSource, 'utf8');
  swCode = swCode.replace(/const\s+CACHE_NAME\s*=\s*['"][^'"]+['"];?/, `const CACHE_NAME = 'runtime-${buildHash}';`);
  writeFileSync(swDest, swCode, 'utf8');

  console.log(`[build] Service Worker written: ${destName}`);
} catch (e) {
  console.warn('[build] Skipped SW versioning:', e?.message || e);
}
