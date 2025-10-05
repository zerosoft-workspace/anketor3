<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$survey = $id ? $surveyService->getSurvey($id) : null;

if ($id && !$survey) {
    set_flash('danger', 'Anket bulunamadi.');
    redirect('surveys.php');
}

if (is_post()) {
    guard_csrf();
    $payload = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'status' => $_POST['status'] ?? 'draft',
        'start_date' => $_POST['start_date'] ?? null,
        'end_date' => $_POST['end_date'] ?? null,
        'category_id' => $_POST['category_id'] ?? null,
    ];

    if (!empty($_POST['new_category'])) {
        $payload['category_id'] = $surveyService->createCategory(trim($_POST['new_category']), null);
    }

    if ($id) {
        $surveyService->updateSurvey($id, $payload);
        set_flash('success', 'Anket guncellendi.');
        redirect('survey_edit.php?id=' . $id);
    } else {
        $newId = $surveyService->createSurvey($payload, current_user_id());
        set_flash('success', 'Anket olusturuldu. Simdi sorulari ekleyin.');
        redirect('survey_questions.php?id=' . $newId);
    }
}

$categories = $surveyService->getCategories();
$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <div class="panel-header">
        <h1><?php echo $survey ? 'Anketi Duzenle' : 'Yeni Anket'; ?></h1>
        <a class="button-secondary" href="surveys.php">Geri</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <form method="POST" class="card">
        <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
        <div class="form-grid">
            <div class="form-group">
                <label for="title">Anket Basligi</label>
                <input type="text" id="title" name="title" required value="<?php echo h($survey['title'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="category_id">Kategori</label>
                <select id="category_id" name="category_id">
                    <option value="">Seçiniz</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo (int)$category['id']; ?>" <?php echo isset($survey['category_id']) && (int)$survey['category_id'] === (int)$category['id'] ? 'selected' : ''; ?>>
                            <?php echo h($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="new_category">Yeni Kategori</label>
                <input type="text" id="new_category" name="new_category" placeholder="Yeni kategori adi">
            </div>
            <div class="form-group">
                <label for="status">Durum</label>
                <select id="status" name="status">
                    <?php $statuses = ['draft' => 'Taslak', 'scheduled' => 'Planlandi', 'active' => 'Aktif', 'closed' => 'Kapandi']; ?>
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo (($survey['status'] ?? 'draft') === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="start_date">Baslangic Tarihi</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo h($survey['start_date'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="end_date">Bitis Tarihi</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo h($survey['end_date'] ?? ''); ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="description">Aciklama</label>
            <textarea id="description" name="description" rows="4"><?php echo h($survey['description'] ?? ''); ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="button-primary"><?php echo $survey ? 'Guncelle' : 'Olustur'; ?></button>
            <?php if ($survey): ?>
                <a class="button-secondary" href="survey_questions.php?id=<?php echo (int)$survey['id']; ?>">Sorulara Git</a>
            <?php endif; ?>
        </div>
    </form>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
