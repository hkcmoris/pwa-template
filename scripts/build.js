import { execSync } from 'node:child_process';
import {
    cpSync,
    rmSync,
    mkdirSync,
    readdirSync,
    statSync,
    existsSync,
} from 'node:fs';
import { join } from 'node:path';

const out = 'server';

// clean dist directory
rmSync(out, { recursive: true, force: true });

// type-check without emitting files
execSync('tsc --noEmit', { stdio: 'inherit' });

// run vite build
execSync('vite build', { stdio: 'inherit' });

// ensure dist directory exists (vite build should have created it)
mkdirSync(join(out, 'public'), { recursive: true });

// move assets folder where PHP expects it
// final structure: server/public/assets/* 
cpSync(join(out, 'assets'), join(out, 'public', 'assets'), { recursive: true });
rmSync(join(out, 'assets'), { recursive: true, force: true });

// copy PHP & server files (but NOT index.html)
for (const entry of readdirSync('.')) {
    if (entry.endsWith('.php') || entry === '.htaccess') {
        cpSync(entry, join(out, entry));
    }
}

for (const dir of ['api', 'server', 'views', 'public']) {
    if (existsSync(dir) && statSync(dir).isDirectory()) {
        // if you already keep a public/ of images, copy it too (won't overwrite assets/)
        cpSync(dir, join(out, dir), { recursive: true });
    }
}

// copy api directory if present
try {
    const apiStat = statSync('api');
    if (apiStat.isDirectory()) {
        cpSync('api', join(out, 'api'), { recursive: true });
    }
    const serverStat = statSync('server');
    if (serverStat.isDirectory()) {
        cpSync('server', join(out, 'server'), { recursive: true });
    }
} catch {
    /* empty */
}
