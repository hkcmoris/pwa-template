// Island for Images: modal preview, context menu (files + folders), drag & drop move

import './images.css';

const BASE =
  (typeof document !== 'undefined' &&
    document.documentElement?.dataset?.base) ||
  '';

const qs = <T extends Element = Element>(root: ParentNode, sel: string) =>
  root.querySelector<T>(sel);

const swapGridFromHTML = (html: string) => {
  const tmp = document.createElement('div');
  tmp.innerHTML = html;
  const incoming = tmp.querySelector('#image-grid');
  const current = document.getElementById('image-grid');
  if (incoming && current && current.parentElement) {
    current.parentElement.replaceChild(incoming, current);
    // Re-process htmx attributes on the newly inserted grid so hx-trigger works
    try {
      (window as any).htmx?.process?.(incoming);
    } catch {}
  }
};

async function postAndSwap(url: string, data: Record<string, string>) {
  const fd = new FormData();
  Object.entries(data).forEach(([k, v]) => fd.append(k, v));
  const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
  const text = await res.text();
  swapGridFromHTML(text);
}

function mount(el: HTMLElement) {
  const grid = () => document.getElementById('image-grid') as HTMLElement | null;
  const modal = qs<HTMLElement>(el, '#img-modal')!;
  const menu = qs<HTMLElement>(el, '#img-context-menu')!;
  const newFolderBtn = qs<HTMLButtonElement>(el, '#new-folder-btn');
  const syncCurrentFromGrid = () => {
    const g = grid();
    if (g) el.dataset.currentPath = g.dataset.currentPath || '';
  };
  document.body.addEventListener('htmx:afterSwap', (evt) => {
    const target = (evt as CustomEvent).detail?.target as HTMLElement | undefined;
    if (target?.id === 'image-grid') syncCurrentFromGrid();
  });

  // New folder creation via compact modal
  newFolderBtn?.addEventListener('click', () => {
    openDialog('Nová složka', `
      <form id="mkdir-form">
        <label>Název složky<br>
          <input type="text" name="name" value="" required>
        </label>
        <div style="display:flex;gap:.5rem;margin-top:.5rem;justify-content:flex-end">
          <button type="button" data-cancel>Storno</button>
          <button type="submit" data-ok>Vytvořit</button>
        </div>
      </form>
    `, (root) => {
      const form = root.querySelector<HTMLFormElement>('#mkdir-form')!;
      const input = form.querySelector<HTMLInputElement>('input[name="name"]');
      input?.focus();
      form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const fd = new FormData(form);
        const name = (fd.get('name') as string).trim();
        if (name) {
          await postAndSwap(`${BASE}/editor/images-mkdir`, { current: el.dataset.currentPath || '', name });
          syncCurrentFromGrid();
        }
        closeModal();
      });
      root.querySelector('[data-cancel]')?.addEventListener('click', closeModal);
    });
  });

  // Modal helpers
  const openImagePreview = (url: string, alt: string) => {
    modal.innerHTML = `
      <div class="overlay" data-role="overlay"></div>
      <div class="dialog" role="dialog" aria-modal="true">
        <button class="close" aria-label="Zavřít">×</button>
        <img src="${url}" alt="${alt}">
      </div>`;
    modal.dataset.kind = 'preview';
    modal.classList.remove('hidden');
  };
  const closeModal = () => { modal.classList.add('hidden'); modal.innerHTML = ''; (modal as HTMLElement).removeAttribute('data-kind'); };
  modal.addEventListener('click', (e) => {
    if ((e.target as HTMLElement).closest('.close, [data-role="overlay"]')) closeModal();
  });
  window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

  // Generic dialog helper
  const openDialog = (title: string, bodyHTML: string, onReady?: (root: HTMLElement) => void) => {
    modal.innerHTML = `
      <div class="overlay" data-role="overlay"></div>
      <div class="dialog dialog--small" role="dialog" aria-modal="true">
        <button class="close" aria-label="Zavřít">×</button>
        <div class="dialog-body">
          <h3 style="margin:.25rem 0 .5rem 0">${title}</h3>
          ${bodyHTML}
        </div>
      </div>`;
    modal.dataset.kind = 'small';
    modal.classList.remove('hidden');
    if (onReady) onReady(modal.querySelector('.dialog-body') as HTMLElement);
  };

  // Context menu with folder + image actions
  const hideMenu = () => {
    menu.classList.add('hidden');
    menu.querySelectorAll('button.hover').forEach((b) => b.classList.remove('hover'));
    menu.innerHTML = '';
  };
  document.addEventListener('click', hideMenu);
  menu.addEventListener('mousemove', (e) => {
    const btn = (e.target as HTMLElement).closest<HTMLButtonElement>('button');
    if (!btn) return;
    menu.querySelectorAll('button.hover').forEach((b) => b.classList.remove('hover'));
    btn.classList.add('hover');
  });
  menu.addEventListener('mouseleave', () => {
    menu.querySelectorAll('button.hover').forEach((b) => b.classList.remove('hover'));
  });

  el.addEventListener('contextmenu', (e) => {
    const targetEl = e.target as HTMLElement;
    const imgTile = targetEl.closest<HTMLElement>('.tile.image');
    const folderTile = targetEl.closest<HTMLElement>('.tile.folder');
    if (!imgTile && !folderTile) return;
    e.preventDefault();
    const x = (e as MouseEvent).clientX;
    const y = (e as MouseEvent).clientY;
    const rel = imgTile ? imgTile.dataset.imageRel! : (folderTile!.dataset.folderRel || '');
    const name = (qs<HTMLElement>(imgTile || folderTile!, '.label')?.textContent || '').trim();
    const isFolder = !!folderTile && !imgTile;

    menu.innerHTML = `
      <button type="button" data-action="rename">Přejmenovat</button>
      <button type="button" data-action="delete">Smazat</button>
    `;
    menu.style.left = `${x}px`;
    menu.style.top = `${y}px`;
    menu.classList.remove('hidden');

    const onClick = async (ev: Event) => {
      const btn = (ev.target as HTMLElement).closest('button');
      if (!btn) return;
      if (btn.dataset.action === 'rename') {
        if (isFolder) {
          openDialog('Přejmenovat složku', `
            <form id="rename-form">
              <label>Nový název složky<br>
                <input type="text" name="newName" value="${name}" required>
              </label>
              <div style="display:flex;gap:.5rem;margin-top:.5rem;justify-content:flex-end">
                <button type="button" data-cancel>Storno</button>
                <button type="submit" data-ok>OK</button>
              </div>
            </form>
          `, (root) => {
            const form = root.querySelector<HTMLFormElement>('#rename-form')!;
            form.addEventListener('submit', async (ev) => {
              ev.preventDefault();
              const fd = new FormData(form);
              const nn = (fd.get('newName') as string).trim();
              if (nn) {
                await postAndSwap(`${BASE}/editor/images-dir-rename`, { dir: rel, newName: nn, current: el.dataset.currentPath || '' });
                syncCurrentFromGrid();
              }
              closeModal();
            });
            (root.querySelector('[data-cancel]') as HTMLElement)?.addEventListener('click', closeModal);
          });
        } else {
          openDialog('Přejmenovat obrázek', `
            <form id="rename-form">
              <label>Nový název souboru (bez .webp)<br>
                <input type="text" name="newName" value="${name.replace(/\.webp$/i,'')}" required>
              </label>
              <div style="display:flex;gap:.5rem;margin-top:.5rem;justify-content:flex-end">
                <button type="button" data-cancel>Storno</button>
                <button type="submit" data-ok>OK</button>
              </div>
            </form>
          `, (root) => {
            const form = root.querySelector<HTMLFormElement>('#rename-form')!;
            form.addEventListener('submit', async (ev) => {
              ev.preventDefault();
              const fd = new FormData(form);
              const nn = (fd.get('newName') as string).trim();
              if (nn) {
                await postAndSwap(`${BASE}/editor/images-rename`, { file: rel, newName: nn, current: el.dataset.currentPath || '' });
                syncCurrentFromGrid();
              }
              closeModal();
            });
            (root.querySelector('[data-cancel]') as HTMLElement)?.addEventListener('click', closeModal);
          });
        }
      }
      if (btn.dataset.action === 'delete') {
        if (isFolder) {
          openDialog('Smazat složku', `
            <form id="delete-form">
              <p>Opravdu smazat složku "${name}"?</p>
              <label style="display:flex;gap:.5rem;align-items:center;margin:.5rem 0">
                <input type="checkbox" name="confirm" required>
                <span>Rozumím, že budou smazány všechny soubory uvnitř této složky.</span>
              </label>
              <div style="display:flex;gap:.5rem;justify-content:flex-end">
                <button type="button" data-cancel>Storno</button>
                <button type="submit" data-ok class="danger">Smazat</button>
              </div>
            </form>
          `, (root) => {
            const form = root.querySelector<HTMLFormElement>('#delete-form')!;
            form.addEventListener('submit', async (ev) => {
              ev.preventDefault();
              const fd = new FormData(form);
              if (fd.get('confirm')) {
                await postAndSwap(`${BASE}/editor/images-dir-delete`, { dir: rel, recursive: '1', current: el.dataset.currentPath || '' });
                syncCurrentFromGrid();
              }
              closeModal();
            });
            (root.querySelector('[data-cancel]') as HTMLElement)?.addEventListener('click', closeModal);
          });
        } else {
          openDialog('Smazat obrázek', `
            <form id="delete-form">
              <p>Opravdu smazat soubor "${name}"?</p>
              <div style="display:flex;gap:.5rem;justify-content:flex-end">
                <button type="button" data-cancel>Storno</button>
                <button type="submit" data-ok class="danger">Smazat</button>
              </div>
            </form>
          `, (root) => {
            const form = root.querySelector<HTMLFormElement>('#delete-form')!;
            form.addEventListener('submit', async (ev) => {
              ev.preventDefault();
              await postAndSwap(`${BASE}/editor/images-delete`, { file: rel, current: el.dataset.currentPath || '' });
              syncCurrentFromGrid();
              closeModal();
            });
            (root.querySelector('[data-cancel]') as HTMLElement)?.addEventListener('click', closeModal);
          });
        }
      }
      hideMenu();
      menu.removeEventListener('click', onClick);
    };
    menu.addEventListener('click', onClick);
  });

  // DnD minimal implementation (files to folders)
  let dragging: HTMLElement | null = null;
  let dragGhost: HTMLElement | null = null;
  let dragRel: string | null = null;
  let dragStart = { x: 0, y: 0 };
  const DRAG_THRESHOLD = 5;

  const createGhost = (from: HTMLElement) => {
    const g = document.createElement('div');
    g.className = 'drag-ghost';
    g.textContent = qs<HTMLElement>(from, '.label')?.textContent || 'Přesunout';
    document.body.appendChild(g);
    return g;
  };

  const onMouseMove = (e: MouseEvent) => {
    if (!dragging) return;
    const dx = Math.abs(e.clientX - dragStart.x);
    const dy = Math.abs(e.clientY - dragStart.y);
    if (!dragGhost && (dx > DRAG_THRESHOLD || dy > DRAG_THRESHOLD)) {
      dragGhost = createGhost(dragging);
    }
    if (dragGhost) {
      dragGhost.style.transform = `translate(${e.clientX + 8}px, ${e.clientY + 8}px)`;
    }
    document.querySelectorAll('.tile.folder').forEach((el) => el.classList.remove('droptarget'));
    const elUnder = document.elementFromPoint(e.clientX, e.clientY) as HTMLElement | null;
    const folder = elUnder?.closest<HTMLElement>('.tile.folder');
    folder?.classList.add('droptarget');
  };

  const endDrag = () => {
    if (dragGhost) { dragGhost.remove(); dragGhost = null; }
    document.removeEventListener('mousemove', onMouseMove);
    document.removeEventListener('mouseup', onMouseUp);
    document.querySelectorAll('.tile.folder').forEach((el) => el.classList.remove('droptarget'));
    dragging = null; dragRel = null;
  };

  const onMouseUp = async (e: MouseEvent) => {
    if (!dragging || !dragRel) return endDrag();
    const elUnder = document.elementFromPoint(e.clientX, e.clientY) as HTMLElement | null;
    const folder = elUnder?.closest<HTMLElement>('.tile.folder');
    if (folder) {
      const to = folder.dataset.folderRel || '';
      await postAndSwap(`${BASE}/editor/images-move`, { file: dragRel, to, current: el.dataset.currentPath || '' });
      syncCurrentFromGrid();
    }
    endDrag();
  };

  el.addEventListener('mousedown', (e) => {
    const tile = (e.target as HTMLElement).closest<HTMLElement>('.tile.image');
    if (!tile) return;
    dragging = tile;
    dragRel = tile.dataset.imageRel || null;
    dragStart = { x: (e as MouseEvent).clientX, y: (e as MouseEvent).clientY };
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
  });

  // Double click image to open modal
  el.addEventListener('dblclick', (e) => {
    const tile = (e.target as HTMLElement).closest<HTMLElement>('.tile.image');
    if (!tile) return;
    const url = tile.dataset.imageUrl!;
    const alt = qs<HTMLElement>(tile, '.label')?.textContent || '';
    openImagePreview(url, alt);
  });
}

export default mount;


