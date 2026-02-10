<?php

/** @var array<int, array<string, mixed>> $configurations */
$configurations = $configurations ?? [];

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
      <li>
        <div class="configuration-entry-main">
          <strong>Konfigurace #<?= htmlspecialchars((string) $configuration['id']) ?></strong>
          <?php if (!empty($configuration['updated_at'])) : ?>
            <time datetime="<?= htmlspecialchars((string) $configuration['updated_at']) ?>">
                <?= htmlspecialchars((string) $configuration['updated_at']) ?>
            </time>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
