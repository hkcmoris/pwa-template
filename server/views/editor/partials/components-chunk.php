<?php
/**
 * @var array<int, array<string, mixed>> $componentsPage
 */
$items = $componentsPage ?? [];

foreach ($items as $node) {
    $id = isset($node['id']) ? (int) $node['id'] : 0;
    if ($id <= 0) {
        continue;
    }

    $definitionId = isset($node['definition_id']) ? (int) $node['definition_id'] : 0;
    $parentId = $node['parent_id'] === null ? '' : (string) (int) $node['parent_id'];
    $position = isset($node['position']) ? (int) $node['position'] : 0;
    $rawEffectiveTitle = isset($node['effective_title']) ? (string) $node['effective_title'] : '';
    $effectiveTitle = htmlspecialchars($rawEffectiveTitle, ENT_QUOTES, 'UTF-8');
    $definitionTitleRaw = isset($node['definition_title']) ? (string) $node['definition_title'] : '';
    $definitionTitle = htmlspecialchars($definitionTitleRaw, ENT_QUOTES, 'UTF-8');
    $alternateTitleValue = $node['alternate_title'] ?? null;
    $rawAlternateTitle = is_string($alternateTitleValue) ? $alternateTitleValue : '';
    $alternateTitle = $rawAlternateTitle !== ''
        ? htmlspecialchars($rawAlternateTitle, ENT_QUOTES, 'UTF-8')
        : '';
    $rawDescription = isset($node['description']) ? (string) $node['description'] : '';
    $description = $rawDescription !== ''
        ? htmlspecialchars($rawDescription, ENT_QUOTES, 'UTF-8')
        : '';
    $rawImage = isset($node['image']) ? (string) $node['image'] : '';
    $image = $rawImage !== ''
        ? htmlspecialchars($rawImage, ENT_QUOTES, 'UTF-8')
        : '';
    $rawColor = isset($node['color']) ? (string) $node['color'] : '';
    $color = $rawColor !== ''
        ? htmlspecialchars($rawColor, ENT_QUOTES, 'UTF-8')
        : '';
    $dependencyCount = isset($node['dependency_tree']) && is_array($node['dependency_tree'])
        ? count($node['dependency_tree'])
        : 0;
    $childCount = isset($node['children_count']) ? (int) $node['children_count'] : 0;
    $depth = isset($node['depth']) ? (int) $node['depth'] : 0;
    $mediaType = $rawColor !== '' ? 'color' : 'image';
    $latestPrice = isset($node['latest_price']) && is_array($node['latest_price'])
        ? $node['latest_price']
        : null;
    $latestAmountRaw = $latestPrice !== null && isset($latestPrice['amount'])
        ? (string) $latestPrice['amount']
        : '';
    $latestCurrencyRaw = $latestPrice !== null && isset($latestPrice['currency'])
        ? (string) $latestPrice['currency']
        : 'CZK';
    $latestCurrency = strtoupper($latestCurrencyRaw);
    $priceHistory = isset($node['price_history']) && is_array($node['price_history'])
        ? array_slice($node['price_history'], 0, 10)
        : [];
    $priceHistoryJsonRaw = json_encode($priceHistory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($priceHistoryJsonRaw === false) {
        $priceHistoryJsonRaw = '[]';
    }
    $priceHistoryJson = htmlspecialchars($priceHistoryJsonRaw, ENT_QUOTES, 'UTF-8');
    $metaParts = [
        'ID ' . $id,
        'pozice ' . $position,
        'definice ' . $definitionTitle . ' (#' . $definitionId . ')',
    ];
    $metaLine = htmlspecialchars(implode(' | ', $metaParts), ENT_QUOTES, 'UTF-8');
    $depthAttr = ' data-depth="' . $depth . '" style="--component-depth:' . $depth . ';"';
    ?>
    <li
      class="component-item"
      data-id="<?= $id ?>"
      data-parent="<?= htmlspecialchars($parentId, ENT_QUOTES, 'UTF-8') ?>"
      data-position="<?= $position ?>"
      data-children-count="<?= $childCount ?>"
      data-definition-id="<?= $definitionId ?>"
      <?= $depthAttr ?>
    >
      <div class="component-node">
        <div class="component-node-header">
          <div class="component-node-info">
            <strong><?= $effectiveTitle ?></strong>
            <?php if ($alternateTitle !== '') : ?>
              <span class="component-alias">alias "<?= $alternateTitle ?>"</span>
            <?php endif; ?>
            <span class="component-meta"><?= $metaLine ?></span>
          </div>
          <div class="component-actions">
            <button
              type="button"
              class="component-action"
              data-action="create-child"
              data-parent-id="<?= $id ?>"
              data-parent-title="<?= $effectiveTitle ?>"
              data-parent-children="<?= $childCount ?>"
            >Přidat podkomponentu</button>
            <button
              type="button"
              class="component-action"
              data-action="edit"
              data-component-id="<?= $id ?>"
              data-title="<?= $effectiveTitle ?>"
              data-definition-id="<?= $definitionId ?>"
              data-alternate-title="<?= htmlspecialchars($rawAlternateTitle, ENT_QUOTES, 'UTF-8') ?>"
              data-description="<?= htmlspecialchars($rawDescription, ENT_QUOTES, 'UTF-8') ?>"
              data-image="<?= htmlspecialchars($rawImage, ENT_QUOTES, 'UTF-8') ?>"
              data-color="<?= htmlspecialchars($rawColor, ENT_QUOTES, 'UTF-8') ?>"
              data-media-type="<?= $mediaType ?>"
              data-position="<?= $position ?>"
              data-price-amount="<?= htmlspecialchars($latestAmountRaw, ENT_QUOTES, 'UTF-8') ?>"
              data-price-currency="<?= htmlspecialchars($latestCurrency, ENT_QUOTES, 'UTF-8') ?>"
              data-price-history="<?= $priceHistoryJson ?>"
            >Upravit</button>
            <button
              type="button"
              class="component-action component-action--danger"
              data-action="delete"
              data-id="<?= $id ?>"
              data-title="<?= $effectiveTitle ?>"
            >Smazat</button>
          </div>
        </div>
        <?php $hasDetails = $description !== '' || $image !== '' || $color !== '' || $dependencyCount > 0; ?>
        <?php if ($hasDetails) : ?>
          <dl class="component-node-details">
            <?php if ($description !== '') : ?>
              <div><dt>Popis</dt><dd><?= $description ?></dd></div>
            <?php endif; ?>
            <?php if ($image !== '') : ?>
              <div><dt>Obrázek</dt><dd><?= $image ?></dd></div>
            <?php endif; ?>
            <?php if ($color !== '') : ?>
              <div>
                <dt>Barva</dt>
                <dd>
                  <span class="component-color-chip" style="--chip-color:<?= $color ?>;"></span>
                  <?= $color ?>
                </dd>
              </div>
            <?php endif; ?>
            <div><dt>Závislosti</dt><dd><?= $dependencyCount ?></dd></div>
          </dl>
        <?php endif; ?>
      </div>
    </li>
    <?php
}
