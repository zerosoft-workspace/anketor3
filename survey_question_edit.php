<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$questionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$surveyId = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;
$question = $surveyService->getQuestion($questionId);

if (!$question) {
    set_flash('danger', 'Soru bulunamadi.');
    redirect('survey_questions.php?id=' . $surveyId);
}

if (is_post()) {
    guard_csrf();
    $type = $_POST['question_type'] ?? 'text';
    $options = [];
    if ($type === 'multiple_choice') {
        $rawOptions = explode("\n", $_POST['options'] ?? '');
        foreach ($rawOptions as $raw) {
            $trimmed = trim($raw);
            if ($trimmed !== '') {
                $options[] = $trimmed;
            }
        }
    }

    $surveyService->updateQuestion($questionId, [
        'question_text' => trim($_POST['question_text'] ?? ''),
        'question_type' => $type,
        'is_required' => !empty($_POST['is_required']),
        'max_length' => $type === 'text' ? (int)($_POST['max_length'] ?? 0) : null,
        'order_index' => (int)($_POST['order_index'] ?? 0),
        'options' => $options,
    ]);

    set_flash('success', 'Soru guncellendi.');
    redirect('survey_questions.php?id=' . ((int)$question['survey_id']));
}

$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <div class="panel-header">
        <h1>Soru Duzenle</h1>
        <a class="button-secondary" href="survey_questions.php?id=<?php echo (int)$question['survey_id']; ?>">Geri</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <form method="POST" class="card">
        <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
        <div class="form-group">
            <label for="question_text">Soru Metni</label>
            <textarea id="question_text" name="question_text" rows="3" required><?php echo h($question['question_text']); ?></textarea>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="question_type">Soru Tipi</label>
                <select id="question_type" name="question_type">
                    <option value="multiple_choice" <?php echo $question['question_type'] === 'multiple_choice' ? 'selected' : ''; ?>>Coktan Secmeli</option>
                    <option value="rating" <?php echo $question['question_type'] === 'rating' ? 'selected' : ''; ?>>1-5 Dereceleme</option>
                    <option value="text" <?php echo $question['question_type'] === 'text' ? 'selected' : ''; ?>>Acik Uclu</option>
                </select>
            </div>
            <div class="form-group">
                <label for="category_key">Kategori Anahtari</label>
                <input type="text" id="category_key" name="category_key" value="<?php echo h($question['category_key'] ?? ''); ?>" placeholder="web_guvenligi">
                <small class="help-text">Raporlamada kullanilacak kisa anahtar. Orn: web_guvenligi</small>
            </div>
            <div class="form-group">
                <label for="order_index">Sirasi</label>
                <input type="number" id="order_index" name="order_index" value="<?php echo (int)$question['order_index']; ?>">
            </div>
            <div class="form-group">
                <label for="max_length">Karakter Limiti</label>
                <input type="number" id="max_length" name="max_length" value="<?php echo (int)($question['max_length'] ?? 0); ?>">
            </div>
            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="is_required" value="1" <?php echo $question['is_required'] ? 'checked' : ''; ?>> Zorunlu
                </label>
            </div>
        </div>
        <div class="form-group">
            <label for="options">Secenekler</label>
            <textarea id="options" name="options" rows="4"><?php
                if (!empty($question['options'])) {
                    echo h(implode("\n", array_column($question['options'], 'option_text')));
                }
            ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="button-primary">Kaydet</button>
        </div>
    </form>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>


