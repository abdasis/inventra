<?php
/**
 * Fragmen satu field form master data. Dipakai fragmen master-list.php (modal)
 * dan master-form.php (halaman penuh) supaya markup input, pesan error per
 * field, dan atribut aksesibilitas tidak diduplikasi antar entitas.
 *
 * Semua variabel di berkas ini berawalan $field agar tidak menimpa variabel
 * pemanggilnya.
 *
 * @var array<string, mixed> $field
 */

use Inventra\Web\Support\View;

$fieldName = (string)$field['name'];
$fieldType = (string)($field['type'] ?? 'text');
$fieldCol = (string)($field['col'] ?? 'col-md-6');
$fieldLabel = (string)($field['label'] ?? $fieldName);
$fieldHint = (string)($field['hint'] ?? '');
$fieldPlaceholder = (string)($field['placeholder'] ?? '');
$fieldRequired = !empty($field['required']);
$fieldId = 'input-' . $fieldName;
$errorId = 'error-' . $fieldName;
$describedBy = ' aria-describedby="' . View::e($errorId) . '"';
$hasSource = isset($field['source']);
?>
<div class="<?= View::e($fieldCol) ?>">
  <label for="<?= View::e($fieldId) ?>" class="form-label small fw-semibold">
    <?= View::e($fieldLabel) ?><?= $fieldRequired ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?>
  </label>

  <?php if ($fieldType === 'textarea'): ?>
    <textarea class="form-control" id="<?= View::e($fieldId) ?>" name="<?= View::e($fieldName) ?>"
              rows="<?= (int)($field['rows'] ?? 2) ?>"<?= $describedBy ?><?= $fieldRequired ? ' required' : '' ?><?= $fieldPlaceholder !== '' ? ' placeholder="' . View::e($fieldPlaceholder) . '"' : '' ?>></textarea>
  <?php elseif ($fieldType === 'select'): ?>
    <select class="form-select" id="<?= View::e($fieldId) ?>" name="<?= View::e($fieldName) ?>"<?= $describedBy ?><?= $fieldRequired ? ' required' : '' ?><?php if ($hasSource): ?> data-source="<?= View::e((string)$field['source']) ?>" data-empty-label="<?= View::e((string)($field['emptyLabel'] ?? 'Pilih salah satu')) ?>"<?php endif; ?>>
      <?php if (!$hasSource): ?>
        <?php if (isset($field['emptyLabel'])): ?>
          <option value=""><?= View::e((string)$field['emptyLabel']) ?></option>
        <?php endif; ?>
        <?php foreach (($field['options'] ?? []) as $option): ?>
          <option value="<?= View::e((string)$option['value']) ?>"<?= ($field['default'] ?? null) === $option['value'] ? ' selected' : '' ?>><?= View::e((string)$option['label']) ?></option>
        <?php endforeach; ?>
      <?php endif; ?>
    </select>
  <?php elseif ($fieldType === 'switch'): ?>
    <div class="form-check form-switch pt-2">
      <input class="form-check-input" type="checkbox" role="switch" id="<?= View::e($fieldId) ?>"
             name="<?= View::e($fieldName) ?>" value="1"<?= $describedBy ?><?= ($field['default'] ?? true) ? ' checked' : '' ?>>
      <label class="form-check-label small" for="<?= View::e($fieldId) ?>"><?= View::e((string)($field['switchLabel'] ?? 'Aktif')) ?></label>
    </div>
  <?php else: ?>
    <input type="<?= View::e($fieldType) ?>" class="form-control" id="<?= View::e($fieldId) ?>" name="<?= View::e($fieldName) ?>"
           <?= isset($field['autocomplete']) ? 'autocomplete="' . View::e((string)$field['autocomplete']) . '"' : '' ?><?= isset($field['min']) ? ' min="' . View::e((string)$field['min']) . '"' : '' ?><?= isset($field['step']) ? ' step="' . View::e((string)$field['step']) . '"' : '' ?><?= $describedBy ?><?= $fieldRequired ? ' required' : '' ?><?= $fieldPlaceholder !== '' ? ' placeholder="' . View::e($fieldPlaceholder) . '"' : '' ?>>
  <?php endif; ?>

  <div class="text-danger small mt-1" id="<?= View::e($errorId) ?>" hidden></div>
  <?php if ($fieldHint !== ''): ?>
    <small class="text-muted"><?= View::e($fieldHint) ?></small>
  <?php endif; ?>
</div>
