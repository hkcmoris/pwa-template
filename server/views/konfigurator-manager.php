<?php

if (!isset($role) || $role === 'guest') {
    echo '<h1>Access denied</h1><p>Please sign in to view your configurations.</p>';
    return;
}

$userId = isset($currentUser['id']) ? (int) $currentUser['id'] : 0;
if ($userId <= 0) {
    echo '<h1>Access denied</h1><p>Unable to resolve your account.</p>';
    return;
}

$pdo = get_db_connection();
$stmt = $pdo->prepare(
    'SELECT id, created_at, updated_at
     FROM configurations
     WHERE user_id = :user_id
     ORDER BY updated_at DESC, created_at DESC, id DESC'
);
$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->execute();
/** @var array<int, array<string, mixed>> $configurations */
$configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Konfigurace</h1>
<button
  hx-get="<?= htmlspecialchars($BASE) ?>/konfigurator"
  hx-push-url="true"
  hx-target="#content"
  hx-select="#content"
  hx-swap="outerHTML"
>Vytvořit novou konfiguraci</button>
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
