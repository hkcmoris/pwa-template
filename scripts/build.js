const { execSync } = require('child_process');
const { cpSync, rmSync, mkdirSync, readdirSync, statSync } = require('fs');
const { join } = require('path');

const distDir = 'dist';

// clean dist directory
rmSync(distDir, { recursive: true, force: true });

// type-check without emitting files
execSync('tsc --noEmit', { stdio: 'inherit' });

// run vite build
execSync('vite build', { stdio: 'inherit' });

// ensure dist directory exists (vite build should have created it)
mkdirSync(distDir, { recursive: true });

// copy root php files and other necessary files
const rootEntries = readdirSync('.');
for (const entry of rootEntries) {
  if (entry.endsWith('.php') || entry === '.htaccess' || entry === 'index.html') {
    cpSync(entry, join(distDir, entry));
  }
}

// copy api directory if present
try {
  const apiStat = statSync('api');
  if (apiStat.isDirectory()) {
    cpSync('api', join(distDir, 'api'), { recursive: true });
  }
} catch {}
