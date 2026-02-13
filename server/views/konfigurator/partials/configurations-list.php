<?php

/** @var array<int, array<string, mixed>> $configurations */
$configurations = $configurations ?? [];
$BASE = isset($BASE) ? (string) $BASE : '';

$finishedConfigurations = array_values(array_filter(
    $configurations,
    static function (array $configuration): bool {
        return ($configuration['status'] ?? '') !== 'draft';
    }
));
?>

<?php if (!$finishedConfigurations) : ?>
  <p class="configurations-empty">Nemáte žádné dokončené konfigurace.</p>
<?php else : ?>
  <ul class="configurations-list">
    <?php foreach ($finishedConfigurations as $configuration) : ?>
        <?php $configurationId = (int) ($configuration['id'] ?? 0); ?>
      <li>
        <div class="configuration-entry-main">
          <strong>Konfigurace #<?= htmlspecialchars((string) $configuration['id']) ?></strong>
          <?php if (!empty($configuration['updated_at'])) : ?>
            <time datetime="<?= htmlspecialchars((string) $configuration['updated_at']) ?>">
                <?= htmlspecialchars((string) $configuration['updated_at']) ?>
            </time>
          <?php endif; ?>
        </div>
        <?php if ($configurationId > 0) : ?>
          <div class="configuration-entry-actions">
            <a
              class="configuration-entry-action configuration-entry-action--solid"
              href="<?= htmlspecialchars($BASE) ?>/configurator/configuration/pdf?configuration_id=<?= htmlspecialchars((string) $configurationId) ?>"
            >Generovat PDF</a>
          </div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
