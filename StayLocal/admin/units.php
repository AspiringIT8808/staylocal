<?php
require_once __DIR__ . '/../config/database.php';
requireAdmin();

$action = (string)($_GET['action'] ?? 'list');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$error = null;
$dbError = null;
$uploadDir = __DIR__ . '/../assets/uploads/units';
$uploadError = null;
$editing = ['id' => 0, 'apartment_id' => 0, 'floor_number' => 1, 'unit_number' => '', 'unit_type' => 'Studio', 'size_sqm' => '', 'monthly_rent' => '', 'image_url' => 'assets/unit-studio.jpg', 'features' => '', 'status' => 'available'];

try {
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyAdminCsrf($_POST['csrf'] ?? null)) {
            $error = 'Your form expired. Refresh the page and try again.';
        } elseif (($_POST['form_action'] ?? '') === 'delete') {
            $pdo->prepare('DELETE FROM units WHERE id = :id')->execute(['id' => (int)$_POST['id']]);
            header('Location: units.php?notice=deleted');
            exit;
        } elseif (($_POST['form_action'] ?? '') === 'save') {
            $uploadedImage = null;
            if (isset($_FILES['unit_image']) && $_FILES['unit_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['unit_image'];
                $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $uploadError = 'The unit image could not be uploaded. Please try again.';
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $uploadError = 'Images must be 5 MB or smaller.';
                } elseif (!isset($allowedTypes[$mimeType])) {
                    $uploadError = 'Please upload a JPG, PNG, or WebP image.';
                } else {
                    $uploadDirReady = is_dir($uploadDir) || mkdir($uploadDir, 0755, true);
                    if (!$uploadDirReady) {
                        $uploadError = 'The unit image folder is not writable.';
                    } else {
                        $filename = 'unit-' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mimeType];
                        if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                            $uploadedImage = 'assets/uploads/units/' . $filename;
                        } else {
                            $uploadError = 'The unit image could not be saved. Check the assets folder permissions.';
                        }
                    }
                }
            }
            $editing = ['id' => (int)($_POST['id'] ?? 0), 'apartment_id' => (int)($_POST['apartment_id'] ?? 0), 'floor_number' => max(1, (int)($_POST['floor_number'] ?? 1)), 'unit_number' => trim((string)($_POST['unit_number'] ?? '')), 'unit_type' => trim((string)($_POST['unit_type'] ?? '')), 'size_sqm' => (float)($_POST['size_sqm'] ?? 0), 'monthly_rent' => (float)($_POST['monthly_rent'] ?? 0), 'image_url' => $uploadedImage ?: trim((string)($_POST['image_url'] ?? 'assets/unit-studio.jpg')), 'features' => trim((string)($_POST['features'] ?? '')), 'status' => (string)($_POST['status'] ?? 'available')];
            if ($uploadError !== null) {
                $error = $uploadError;
                $action = $editing['id'] ? 'edit' : 'new';
                $id = $editing['id'];
            } elseif (!$editing['apartment_id'] || $editing['unit_number'] === '' || $editing['unit_type'] === '' || $editing['size_sqm'] <= 0 || $editing['monthly_rent'] <= 0) {
                $error = 'Apartment, unit number, type, size, and monthly rent are required.';
                $action = $editing['id'] ? 'edit' : 'new';
                $id = $editing['id'];
            } else {
                if (!in_array($editing['status'], ['available', 'occupied', 'reserved'], true)) $editing['status'] = 'available';
                if ($editing['id']) {
                    $statement = $pdo->prepare('UPDATE units SET apartment_id=:apartment_id, floor_number=:floor_number, unit_number=:unit_number, unit_type=:unit_type, size_sqm=:size_sqm, monthly_rent=:monthly_rent, image_url=:image_url, features=:features, status=:status WHERE id=:id');
                    $statement->execute($editing);
                } else {
                    $statement = $pdo->prepare('INSERT INTO units (apartment_id, floor_number, unit_number, unit_type, size_sqm, monthly_rent, image_url, features, status) VALUES (:apartment_id, :floor_number, :unit_number, :unit_type, :size_sqm, :monthly_rent, :image_url, :features, :status)');
                    $statement->execute(array_diff_key($editing, ['id' => true]));
                }
                header('Location: units.php?notice=saved');
                exit;
            }
        }
    }
    if ($action === 'edit' && $id && !$error) {
        $statement = $pdo->prepare('SELECT * FROM units WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $editing = $statement->fetch() ?: $editing;
    }
    $apartments = $pdo->query('SELECT id, name FROM apartments ORDER BY name')->fetchAll();
    $units = $pdo->query('SELECT u.*, a.name AS apartment_name FROM units u JOIN apartments a ON a.id = u.apartment_id ORDER BY a.name, u.floor_number, u.unit_number')->fetchAll();
} catch (Throwable $exception) {
    $dbError = 'Unable to load units. Verify the MySQL connection and schema.';
    $apartments = $units = [];
}

$pageTitle = $action === 'new' ? 'Add unit' : ($action === 'edit' ? 'Edit unit' : 'Units');
$currentAdminPage = 'units';
require __DIR__ . '/partials/header.php';
?>
<?php if ($dbError): ?><div class="database-notice" role="status"><strong>Database unavailable.</strong><span><?= e($dbError) ?></span></div><?php endif; ?>
<?php if ($action === 'new' || $action === 'edit'): ?>
    <div class="admin-page-actions"><a class="text-link" href="units.php">← Back to units</a></div>
    <?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="admin-form-card"><input type="hidden" name="csrf" value="<?= e(adminCsrfToken()) ?>"><input type="hidden" name="form_action" value="save"><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><div class="admin-form-heading"><div><p class="eyebrow">UNIT DETAILS</p><h2><?= $editing['id'] ? 'Update this unit' : 'Create a new unit' ?></h2></div><button class="button button-dark" type="submit">Save unit →</button></div><div class="form-grid"><label>Apartment<select name="apartment_id" required><option value="">Choose apartment</option><?php foreach ($apartments as $apartment): ?><option value="<?= (int)$apartment['id'] ?>" <?= (int)$editing['apartment_id'] === (int)$apartment['id'] ? 'selected' : '' ?>><?= e($apartment['name']) ?></option><?php endforeach; ?></select></label><label>Floor number<input type="number" min="1" name="floor_number" required value="<?= e((string)$editing['floor_number']) ?>"></label><label>Unit number<input type="text" name="unit_number" required value="<?= e($editing['unit_number']) ?>"></label><label>Unit type<input type="text" name="unit_type" required value="<?= e($editing['unit_type']) ?>"></label><label>Size (sqm)<input type="number" min="1" step="0.01" name="size_sqm" required value="<?= e((string)$editing['size_sqm']) ?>"></label><label>Monthly rent (₱)<input type="number" min="1" step="0.01" name="monthly_rent" required value="<?= e((string)$editing['monthly_rent']) ?>"></label><label>Status<select name="status"><?php foreach (['available','occupied','reserved'] as $status): ?><option value="<?= $status ?>" <?= $editing['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></label><label>Unit image<input type="file" name="unit_image" accept="image/jpeg,image/png,image/webp"><small>Choose a JPG, PNG, or WebP image up to 5 MB. Leave blank to keep the current image.</small><?php if (!empty($editing['image_url'])): ?><img class="admin-image-preview" src="../<?= e($editing['image_url']) ?>" alt="Current unit image"><?php endif; ?></label><input type="hidden" name="image_url" value="<?= e($editing['image_url']) ?>"></div><label class="admin-wide-label">Features, separated by commas<textarea name="features" rows="4" placeholder="Fiber Ready, Balcony, Air Conditioning"><?= e($editing['features']) ?></textarea></label></form>
<?php else: ?>
    <section class="admin-page-toolbar"><div><p class="admin-lede">Control availability, pricing, and the details renters see.</p></div><a class="button button-dark" href="units.php?action=new">+ Add unit</a></section>
    <?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <div class="admin-panel admin-list-panel"><div class="admin-table-wrap"><table class="admin-table admin-table-wide"><thead><tr><th>Unit</th><th>Building</th><th>Type / size</th><th>Rent</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($units as $unit): ?><tr><td><strong><?= e($unit['unit_number']) ?></strong><small>Floor <?= (int)$unit['floor_number'] ?></small></td><td><?= e($unit['apartment_name']) ?></td><td><?= e($unit['unit_type']) ?><small><?= (float)$unit['size_sqm'] ?> sqm</small></td><td><strong><?= pesos((float)$unit['monthly_rent']) ?></strong><small>per month</small></td><td><span class="status-pill status-<?= e($unit['status']) ?>"><?= e($unit['status']) ?></span></td><td><div class="table-actions"><a href="units.php?action=edit&id=<?= (int)$unit['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this unit?');"><input type="hidden" name="csrf" value="<?= e(adminCsrfToken()) ?>"><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$unit['id'] ?>"><button type="submit">Delete</button></form><a href="../unit.php?id=<?= (int)$unit['id'] ?>">View ↗</a></div></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
