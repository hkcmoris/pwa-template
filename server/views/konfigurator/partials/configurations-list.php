<?php

/** @var array<int, array<string, mixed>> $configurations */
$configurations = $configurations ?? [];
?>

<?php if (!$configurations) : ?>
  <p>Nemáte žádné uložené konfigurace.</p>
<?php else : ?>
  <ul>
    <?php foreach ($configurations as $configuration) : ?>
      <li>
        <span>Konfigurace #<?= htmlspecialchars((string) $configuration['id']) ?></span>
        <?php if (!empty($configuration['updated_at'])) : ?>
          <time datetime="<?= htmlspecialchars((string) $configuration['updated_at']) ?>">
            <?= htmlspecialchars((string) $configuration['updated_at']) ?>
          </time>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
