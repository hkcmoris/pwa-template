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
