<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$surveyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$survey = $surveyService->getSurvey($surveyId);
if (!$survey) {
    set_flash('danger', 'Anket bulunamadi.');
    redirect('surveys.php');
}

if (isset($_GET['delete_question'])) {
    $questionId = (int)$_GET['delete_question'];
    $surveyService->deleteQuestion($questionId);
    set_flash('success', 'Soru silindi.');
    redirect('survey_questions.php?id=' . $surveyId);
}

$aiSuggestions = [];

if (is_post()) {
    guard_csrf();
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
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

        $surveyService->addQuestion($surveyId, [
            'question_text' => trim($_POST['question_text'] ?? ''),
            'question_type' => $type,
            'category_key' => trim($_POST['category_key'] ?? '') ?: null,
            'is_required' => !empty($_POST['is_required']),
            'max_length' => $type === 'text' ? (int)($_POST['max_length'] ?? 0) : null,
            'order_index' => (int)($_POST['order_index'] ?? 0),
            'options' => $options,
        ]);

        set_flash('success', 'Soru eklendi.');
        redirect('survey_questions.php?id=' . $surveyId);
    }

    if ($action === 'ai_suggest') {
        $topic = trim($_POST['topic'] ?? '');
        if ($topic) {
            $aiSuggestions = $surveyService->aiClient()->suggestQuestions($topic, 5);
        }
    }
}

$questions = $surveyService->getQuestions($surveyId);
$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <div class="panel-header">
        <h1>Sorular &raquo; <?php echo h($survey['title']); ?></h1>
        <div class="inline-actions">
            <a class="button-secondary" href="survey_edit.php?id=<?php echo (int)$survey['id']; ?>">Ayarlar</a>
            <a class="button-secondary" href="participants.php?id=<?php echo (int)$survey['id']; ?>">Katilimcilar</a>
            <a class="button-primary" href="answer_preview.php?id=<?php echo (int)$survey['id']; ?>" target="_blank">Anketi Gor</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-header">
            <h2>Yeni Soru</h2>
        </div>
        <div class="panel-body">
            <form method="POST" class="form-horizontal">
                <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label for="question_text">Soru Metni</label>
                    <textarea id="question_text" name="question_text" rows="3" required></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="question_type">Soru Tipi</label>
                        <select id="question_type" name="question_type">
                            <option value="multiple_choice">Coktan Secmeli</option>
                            <option value="rating">1-5 Dereceleme</option>
                            <option value="text">Acik Uclu</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="category_key">Kategori Anahtari</label>
                        <input type="text" id="category_key" name="category_key" placeholder="web_guvenligi">
                        <small class="help-text">Raporlamada kullanilacak kisa anahtar. Orn: web_guvenligi</small>
                    </div>
                    <div class="form-group">
                        <label for="order_index">Sirasi</label>
                        <input type="number" id="order_index" name="order_index" value="<?php echo count($questions); ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label for="max_length">Karakter Limiti</label>
                        <input type="number" id="max_length" name="max_length" placeholder="Opsiyonel">
                    </div>
                    <div class="form-group checkbox">
                        <label>
                            <input type="checkbox" name="is_required" value="1"> Zorunlu soru
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="options">Secenekler (her satira bir)</label>
                    <textarea id="options" name="options" rows="4" placeholder="Evet\nHayir\nKismi"></textarea>
                    <small class="help-text">Yalnizca coktan secmeli sorular icin kullanilir.</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button-primary">Soru Ekle</button>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>AI Soru Onerisi</h2>
        </div>
        <div class="panel-body">
            <form method="POST" class="inline-form">
                <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="ai_suggest">
                <label for="topic">Tema</label>
                <input type="text" id="topic" name="topic" placeholder="Orn: Calisan bagliligi" required>
                <button type="submit" class="button-secondary">Oneri Al</button>
                <?php if (!$surveyService->aiClient()->isEnabled()): ?>
                    <span class="tag">Demo modu</span>
                <?php endif; ?>
            </form>
            <?php if (!empty($aiSuggestions)): ?>
                <div class="suggestions">
                    <h3>Onerilen sorular</h3>
                    <ul>
                        <?php foreach ($aiSuggestions as $suggestion): ?>
                            <li>
                                <form method="POST" class="suggestion-form">
                                    <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
                                    <input type="hidden" name="action" value="create">
                                    <input type="hidden" name="question_type" value="text">
                                    <input type="hidden" name="question_text" value="<?php echo h(is_array($suggestion) ? ($suggestion['question'] ?? implode(' ', $suggestion)) : $suggestion); ?>">
                                    <button type="submit" class="button-link">Ekle</button>
                                    <span><?php echo h(is_array($suggestion) ? ($suggestion['question'] ?? implode(' ', $suggestion)) : $suggestion); ?></span>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Mevcut Sorular</h2>
        </div>
        <div class="panel-body">
            <?php if (empty($questions)): ?>
                <p>Bu ankette henuz soru yok.</p>
            <?php else: ?>
                <ul class="question-list">
                    <?php foreach ($questions as $question): ?>
                        <li class="question-item">
                            <div>
                                <strong>#<?php echo (int)$question['order_index']; ?> &raquo; <?php echo h($question['question_text']); ?></strong>
                                <span class="tag tag-<?php echo h($question['question_type']); ?>"><?php echo h($question['question_type']); ?></span>
                                <?php if (!empty($question['category_key'])): ?>
                                    <span class="tag tag-category"><?php echo h($question['category_key']); ?></span>
                                <?php endif; ?>
                                <?php if ($question['is_required']): ?><span class="tag">Zorunlu</span><?php endif; ?>
                            </div>
                            <?php if (!empty($question['options'])): ?>
                                <ul class="option-list">
                                    <?php foreach ($question['options'] as $option): ?>
                                        <li><?php echo h($option['option_text']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <div class="question-actions">
                                <a class="button-link" href="survey_question_edit.php?id=<?php echo (int)$question['id']; ?>&survey_id=<?php echo $surveyId; ?>">Duzenle</a>
                                <a class="button-link text-danger" href="?id=<?php echo $surveyId; ?>&delete_question=<?php echo (int)$question['id']; ?>" onclick="return confirm('Bu soruyu silmek istediginize emin misiniz?');">Sil</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>




