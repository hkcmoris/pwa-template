<?php

/** @var array<int, array<string, mixed>> $drafts */
$drafts = $drafts ?? [];
/** @var string $BASE */
$BASE = isset($BASE) ? (string) $BASE : '';
?>

<?php if ($drafts !== []) : ?>
  <ul id="draft-list" class="configurations-list">
    <?php foreach ($drafts as $draft) : ?>
        <?php
        $draftId = (int) $draft['id'];
        $draftTitle = trim((string) ($draft['title'] ?? ''));
        $draftLabel = $draftTitle !== '' ? $draftTitle : ('Návrh #' . $draftId);
        ?>
      <li>
        <div class="configuration-entry-main">
          <strong><?= htmlspecialchars($draftLabel) ?></strong>
          <?php if (!empty($draft['updated_at'])) : ?>
            <time datetime="<?= htmlspecialchars((string) $draft['updated_at']) ?>">
                <?= htmlspecialchars((string) $draft['updated_at']) ?>
            </time>
          <?php endif; ?>
        </div>
        <div class="configuration-entry-actions">
          <button
            class="configuration-entry-action"
            hx-get="<?= htmlspecialchars($BASE) ?>/konfigurator?draft=<?= htmlspecialchars((string) $draftId) ?>"
            hx-push-url="true"
            hx-target="#content"
            hx-select="#content"
            hx-swap="outerHTML"
          >Pokračovat</button>
          <button
            type="button"
            class="configuration-entry-action configuration-entry-action--icon"
            data-manager-action="rename"
            data-draft-id="<?= htmlspecialchars((string) $draftId) ?>"
            data-draft-title="<?= htmlspecialchars($draftTitle) ?>"
            aria-label="Přejmenovat návrh <?= htmlspecialchars($draftLabel) ?>"
            title="Přejmenovat"
          >
            <svg
              fill="currentColor"
              width="16px"
              height="16px"
              display="block"
              style="display: block;"
              aria-hidden="true"
            >
              <use href="#icon-rename"></use>
            </svg>
          </button>
          <button
            type="button"
            class="configuration-entry-action configuration-entry-action--danger configuration-entry-action--icon"
            data-manager-action="delete"
            data-draft-id="<?= htmlspecialchars((string) $draftId) ?>"
            data-draft-title="<?= htmlspecialchars($draftLabel) ?>"
            aria-label="Smazat návrh <?= htmlspecialchars($draftLabel) ?>"
            title="Smazat"
          >
            <svg
              fill="currentColor"
              width="16px"
              height="16px"
              display="block"
              style="display: block;"
              aria-hidden="true"
            >
              <use href="#icon-trash"></use>
            </svg>
          </button>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php else : ?>
  <p class="configurations-empty" id="draft-list">Nemáte žádné rozpracované návrhy.</p>
<?php endif; ?>
