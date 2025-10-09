<?php
/**
 * @var array<int, array<string, mixed>> $componentsPage
 * @var int $componentPageSize
 * @var int $totalComponents
 */
$items = $componentsPage ?? [];
$pageCount = count($items);
$nextOffset = isset($nextOffset) ? (int) $nextOffset : $pageCount;
$hasMore = isset($hasMore)
    ? (bool) $hasMore
    : ($nextOffset < ($totalComponents ?? 0));
$isHx = isset($_SERVER['HTTP_HX_REQUEST']);
$sentinelAttrs = $isHx ? ' hx-swap-oob="true"' : '';
?>
<div class="components-list-wrapper" id="components-list-wrapper">
  <?php if (empty($items)) : ?>
    <p class="components-empty">Zatím nebyly vytvořeny žádné komponenty.</p>
  <?php else : ?>
    <ul
      class="component-tree"
      id="components-list"
      data-page-size="<?= $componentPageSize ?>"
      data-total="<?= $totalComponents ?>"
      data-next-offset="<?= $nextOffset ?>"
    >
      <?php include __DIR__ . '/components-chunk.php'; ?>
    </ul>
    <div
      id="components-list-sentinel"
      data-component-sentinel
      data-next-offset="<?= $nextOffset ?>"
      data-page-size="<?= $componentPageSize ?>"
      data-total="<?= $totalComponents ?>"
      data-has-more="<?= $hasMore ? '1' : '0' ?>"
      <?= $sentinelAttrs ?>
      aria-hidden="true"
    ></div>
  <?php endif; ?>
</div>
