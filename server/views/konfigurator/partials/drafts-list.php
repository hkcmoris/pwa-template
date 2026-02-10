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
          <form
            class="configuration-inline-form"
            hx-post="<?= htmlspecialchars($BASE) ?>/configurator/wizard/rename"
            hx-target="#draft-form-errors"
            hx-swap="outerHTML"
          >
            <input type="hidden" name="draft_id" value="<?= htmlspecialchars((string) $draftId) ?>">
            <input
              class="configuration-rename-input"
              type="text"
              name="title"
              maxlength="191"
              placeholder="Přejmenovat návrh"
              value="<?= htmlspecialchars($draftTitle) ?>"
              required
            >
            <button class="configuration-entry-action" type="submit">Přejmenovat</button>
          </form>
          <button
            class="configuration-entry-action configuration-entry-action--danger"
            hx-post="<?= htmlspecialchars($BASE) ?>/configurator/wizard/delete"
            hx-vals='{"draft_id": "<?= htmlspecialchars((string) $draftId) ?>"}'
            hx-target="#draft-form-errors"
            hx-swap="outerHTML"
            hx-confirm="Opravdu chcete tento návrh odstranit?"
          >Smazat</button>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php else : ?>
  <p class="configurations-empty" id="draft-list">Nemáte žádné rozpracované návrhy.</p>
<?php endif; ?>
