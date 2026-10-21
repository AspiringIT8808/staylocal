<?php
require_once __DIR__ . '/../config/database.php';
requireAdmin();

$action = (string)($_GET['action'] ?? 'list');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$error = null;
$dbError = null;
$uploadDir = __DIR__ . '/../assets/uploads/apartments';
$uploadError = null;
$amenityLabels=['parking'=>'On-site parking','beach'=>'Near beach / coast','elevator'=>'Elevator','pwd_accessible'=>'PWD-accessible route'];
$editing = ['id' => 0, 'name' => '', 'area' => '', 'address' => '', 'description' => '', 'policies' => '', 'amenities'=>[], 'pet_friendly' => 0, 'image_url' => 'assets/feat-view.jpg', 'featured' => 0];

try {
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyAdminCsrf($_POST['csrf'] ?? null)) {
            $error = 'Your form expired. Refresh the page and try again.';
        } elseif (($_POST['form_action'] ?? '') === 'delete') {
            $delete = $pdo->prepare('DELETE FROM apartments WHERE id = :id');
            $delete->execute(['id' => (int)$_POST['id']]);
            header('Location: apartments.php?notice=deleted');
            exit;
        } elseif (($_POST['form_action'] ?? '') === 'save') {
            $uploadedImage = null;
            if (isset($_FILES['apartment_image']) && $_FILES['apartment_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['apartment_image'];
                $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $uploadError = 'The image could not be uploaded. Please try again.';
                } elseif ($file['size'] > 5 * 1024 * 1024) {
                    $uploadError = 'Images must be 5 MB or smaller.';
                } elseif (!isset($allowedTypes[$mimeType])) {
                    $uploadError = 'Please upload a JPG, PNG, or WebP image.';
                } else {
                    $uploadDirReady = is_dir($uploadDir) || mkdir($uploadDir, 0755, true);
                    if (!$uploadDirReady) {
                        $uploadError = 'The upload folder is not writable.';
                    } else {
                        $filename = 'apartment-' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mimeType];
                        if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                            $uploadedImage = 'assets/uploads/apartments/' . $filename;
                        } else {
                            $uploadError = 'The image could not be saved. Check the assets folder permissions.';
                        }
                    }
                }
            }
            $editing = [
                'id' => (int)($_POST['id'] ?? 0),
                'name' => trim((string)($_POST['name'] ?? '')),
                'area' => trim((string)($_POST['area'] ?? '')),
                'address' => trim((string)($_POST['address'] ?? '')),
                'description' => trim((string)($_POST['description'] ?? '')),
                'policies' => trim((string)($_POST['policies'] ?? '')),
                'pet_friendly' => isset($_POST['pet_friendly']) ? 1 : 0,
                'image_url' => $uploadedImage ?: trim((string)($_POST['image_url'] ?? 'assets/feat-view.jpg')),
                'featured' => isset($_POST['featured']) ? 1 : 0,
                'amenities'=>array_values(array_intersect(array_keys($amenityLabels),array_map('strval',(array)($_POST['amenities'] ?? [])))),
            ];
            if ($uploadError !== null) {
                $error = $uploadError;
                $action = $editing['id'] ? 'edit' : 'new';
                $id = $editing['id'];
            } elseif ($editing['name'] === '' || $editing['area'] === '' || $editing['address'] === '' || $editing['description'] === '') {
                $error = 'Name, area, address, and description are required.';
                $action = $editing['id'] ? 'edit' : 'new';
                $id = $editing['id'];
            } else {
                if ($editing['image_url'] === '') $editing['image_url'] = 'assets/feat-view.jpg';
                $apartmentData=array_diff_key($editing,['amenities'=>true]);
                if ($editing['id']) {
                    $statement = $pdo->prepare('UPDATE apartments SET name=:name, area=:area, address=:address, description=:description, policies=:policies, pet_friendly=:pet_friendly, image_url=:image_url, featured=:featured WHERE id=:id');
                    $statement->execute($apartmentData);$apartmentId=$editing['id'];
                } else {
                    $statement = $pdo->prepare('INSERT INTO apartments (name, area, address, description, policies, pet_friendly, image_url, featured) VALUES (:name, :area, :address, :description, :policies, :pet_friendly, :image_url, :featured)');
                    $statement->execute(array_diff_key($apartmentData, ['id' => true]));$apartmentId=(int)$pdo->lastInsertId();
                }
                $pdo->prepare('DELETE FROM apartment_amenities WHERE apartment_id=:id')->execute(['id'=>$apartmentId]);
                $amenityInsert=$pdo->prepare('INSERT INTO apartment_amenities(apartment_id,amenity) VALUES(:id,:amenity)');
                foreach($editing['amenities'] as $amenity){$amenityInsert->execute(['id'=>$apartmentId,'amenity'=>$amenity]);}
                header('Location: apartments.php?notice=saved');
                exit;
            }
        }
    }
    if ($action === 'edit' && $id && !$error) {
        $statement = $pdo->prepare('SELECT * FROM apartments WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $editing = $statement->fetch() ?: $editing;
        $amenityStatement=$pdo->prepare('SELECT amenity FROM apartment_amenities WHERE apartment_id=:id');$amenityStatement->execute(['id'=>$id]);$editing['amenities']=$amenityStatement->fetchAll(PDO::FETCH_COLUMN);
    }
    $apartments = $pdo->query("SELECT a.*, COUNT(u.id) AS unit_count, SUM(u.status = 'available') AS available_count FROM apartments a LEFT JOIN units u ON u.apartment_id = a.id GROUP BY a.id ORDER BY a.featured DESC, a.name ASC")->fetchAll();
} catch (Throwable $exception) {
    $dbError = 'Unable to load apartments. Verify the MySQL connection and schema.';
    $apartments = [];
}

$pageTitle = $action === 'new' ? 'Add apartment' : ($action === 'edit' ? 'Edit apartment' : 'Apartments');
$currentAdminPage = 'apartments';
require __DIR__ . '/partials/header.php';
?>
<?php if ($dbError): ?><div class="database-notice" role="status"><strong>Database unavailable.</strong><span><?= e($dbError) ?></span></div><?php endif; ?>
<?php if ($action === 'new' || $action === 'edit'): ?>
    <div class="admin-page-actions"><a class="text-link" href="apartments.php">← Back to apartments</a></div>
    <?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="admin-form-card"><input type="hidden" name="csrf" value="<?= e(adminCsrfToken()) ?>"><input type="hidden" name="form_action" value="save"><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><div class="admin-form-heading"><div><p class="eyebrow">LISTING DETAILS</p><h2><?= $editing['id'] ? 'Update this apartment' : 'Create a new apartment' ?></h2></div><button class="button button-dark" type="submit">Save apartment →</button></div><div class="form-grid"><label>Apartment name<input type="text" name="name" required value="<?= e($editing['name']) ?>"></label><label>Area / neighborhood<input type="text" name="area" required value="<?= e($editing['area']) ?>"></label><label>Street address<input type="text" name="address" required value="<?= e($editing['address']) ?>"></label><label>Apartment image<input type="file" name="apartment_image" accept="image/jpeg,image/png,image/webp"><small>Choose a JPG, PNG, or WebP image up to 5 MB. Leave blank to keep the current image.</small><?php if (!empty($editing['image_url'])): ?><img class="admin-image-preview" src="../<?= e($editing['image_url']) ?>" alt="Current apartment image"><?php endif; ?></label><input type="hidden" name="image_url" value="<?= e($editing['image_url']) ?>"><label class="check-field"><input type="checkbox" name="pet_friendly" <?= $editing['pet_friendly'] ? 'checked' : '' ?>> Pet friendly</label><label class="check-field"><input type="checkbox" name="featured" <?= $editing['featured'] ? 'checked' : '' ?>> Feature on public homepage</label></div><fieldset class="admin-wide-label"><legend>Preferences &amp; amenities</legend><div class="admin-amenity-options"><?php foreach($amenityLabels as $key=>$label): ?><label class="check-field"><input type="checkbox" name="amenities[]" value="<?= e($key) ?>" <?= in_array($key,$editing['amenities'] ?? [],true)?'checked':'' ?>> <?= e($label) ?></label><?php endforeach; ?></div><small>Only select features that are actually available at this property.</small></fieldset><label class="admin-wide-label">Description<textarea name="description" rows="5" required><?= e($editing['description']) ?></textarea></label><label class="admin-wide-label">Policies &amp; guidelines<textarea name="policies" rows="8" placeholder="Enter one policy bullet per line, including quiet hours, guests, safety, utilities, parking, and accessibility."><?= e($editing['policies'] ?? '') ?></textarea><small>Shown to renters in the apartment policy pop-up. Only enter rules approved by the property owner.</small></label></form>
<?php else: ?>
    <section class="admin-page-toolbar"><div><p class="admin-lede">Keep your building directory accurate and renter-ready.</p></div><a class="button button-dark" href="apartments.php?action=new">+ Add apartment</a></section>
    <?php if ($error): ?><div class="form-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <div class="admin-panel admin-list-panel"><div class="admin-table-wrap"><table class="admin-table admin-table-wide"><thead><tr><th>Apartment</th><th>Area</th><th>Pet policy</th><th>Units</th><th>Actions</th></tr></thead><tbody><?php foreach ($apartments as $apartment): ?><tr><td><strong><?= e($apartment['name']) ?></strong><small><?= e($apartment['address']) ?></small></td><td><?= e($apartment['area']) ?></td><td><span class="status-pill status-<?= $apartment['pet_friendly'] ? 'paid' : 'occupied' ?>"><?= $apartment['pet_friendly'] ? 'Pet friendly' : 'No pets' ?></span></td><td><strong><?= (int)$apartment['available_count'] ?></strong> available<small><?= (int)$apartment['unit_count'] ?> total</small></td><td><div class="table-actions"><a href="apartments.php?action=edit&id=<?= (int)$apartment['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this apartment and its units?');"><input type="hidden" name="csrf" value="<?= e(adminCsrfToken()) ?>"><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int)$apartment['id'] ?>"><button type="submit">Delete</button></form><a href="../apartment.php?id=<?= (int)$apartment['id'] ?>">View ↗</a></div></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
