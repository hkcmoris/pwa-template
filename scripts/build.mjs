import { execSync } from 'node:child_process';

// type-check without emitting files
execSync('tsc --noEmit', { stdio: 'inherit' });

// run vite build
execSync('vite build', { stdio: 'inherit' });