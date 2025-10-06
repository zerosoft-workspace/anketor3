<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$surveyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$survey = $surveyService->getSurvey($surveyId);
if (!$survey) {
    set_flash('danger', 'Anket bulunamadi.');
    redirect('surveys.php');
}
$pageTitle = 'Sorular - ' . ($survey['title'] ?? config('app.name', 'Anketor'));

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

    if ($action === 'add_from_library') {
        $libraryQuestionId = (int)($_POST['library_question_id'] ?? 0);
        $orderIndex = isset($_POST['order_index']) && $_POST['order_index'] !== ''
            ? (int)$_POST['order_index']
            : null;

        if ($libraryQuestionId <= 0) {
            set_flash('danger', 'Lutfen hazir sorulardan birini secin.');
            redirect('survey_questions.php?id=' . $surveyId);
        }

        try {
            $surveyService->addQuestionFromLibrary($surveyId, $libraryQuestionId, $orderIndex);
            set_flash('success', 'Hazır soru ankete eklendi.');
        } catch (InvalidArgumentException $e) {
            set_flash('danger', $e->getMessage());
        }

        redirect('survey_questions.php?id=' . $surveyId);
    }

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

        $questionData = [
            'question_text' => trim($_POST['question_text'] ?? ''),
            'question_type' => $type,
            'category_key' => ($categoryKey = trim($_POST['category_key'] ?? '')) !== '' ? $categoryKey : null,
            'is_required' => !empty($_POST['is_required']),
            'max_length' => $type === 'text' ? (int)($_POST['max_length'] ?? 0) : null,
            'order_index' => (int)($_POST['order_index'] ?? 0),
            'options' => $options,
        ];

        $surveyService->addQuestion($surveyId, $questionData);

        if (!empty($_POST['save_to_library'])) {
            $surveyService->addLibraryQuestion($questionData);
        }

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
$libraryQuestions = $surveyService->getQuestionLibrary();
$libraryByCategory = [];
foreach ($libraryQuestions as $libraryQuestion) {
    $groupKey = $libraryQuestion['category_key'] ?: 'Genel';
    if (!isset($libraryByCategory[$groupKey])) {
        $libraryByCategory[$groupKey] = [];
    }
    $libraryByCategory[$groupKey][] = $libraryQuestion;
}
$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <header class="page-header">
        <div>
            <p class="eyebrow">Soru Tasarımı</p>
            <h1><?php echo h($survey['title']); ?></h1>
            <p class="page-subtitle">Hazır soru havuzundan seçim yapın veya yeni sorular oluşturup AI önerileriyle zenginleştirin.</p>
        </div>
        <div class="page-header__actions">
            <a class="button-secondary" href="survey_edit.php?id=<?php echo (int)$survey['id']; ?>">Ayarlar</a>
            <a class="button-secondary" href="participants.php?id=<?php echo (int)$survey['id']; ?>">Katılımcılar</a>
            <a class="button-primary" href="answer_preview.php?id=<?php echo (int)$survey['id']; ?>" target="_blank">Anketi Gör</a>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-header">
            <h2>Hazır Sorudan Ekle</h2>
        </div>
        <div class="panel-body">
            <?php if (empty($libraryQuestions)): ?>
                <p>Henüz soru havuzunda kayıt bulunmuyor. Yeni sorular oluştururken "Soru havuzuna ekle" seçeneğini kullanabilirsiniz.</p>
            <?php else: ?>
                <form method="POST" class="form-horizontal">
                    <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="add_from_library">
                    <div class="form-group">
                        <label for="library_question_id">Hazır Soru</label>
                        <select id="library_question_id" name="library_question_id" required>
                            <option value="">Bir soru seçin</option>
                            <?php foreach ($libraryByCategory as $group => $items): ?>
                                <optgroup label="<?php echo h(str_replace('_', ' ', ucfirst($group))); ?>">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $label = $item['question_text'];
                                        if (function_exists('mb_strimwidth')) {
                                            $label = mb_strimwidth($label, 0, 120, '...');
                                        } elseif (strlen($label) > 120) {
                                            $label = substr($label, 0, 117) . '...';
                                        }
                                        ?>
                                        <option value="<?php echo (int)$item['id']; ?>"><?php echo h($label); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="library_order_index">Sırası</label>
                        <input type="number" id="library_order_index" name="order_index" value="<?php echo count($questions); ?>" min="0">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="button-primary">Hazır Soruyu Ekle</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>

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
                            <option value="multiple_choice">Çoktan Seçmeli</option>
                            <option value="rating">1-5 Dereceleme</option>
                            <option value="text">Açık Uçlu</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="category_key">Kategori Anahtarı</label>
                        <input type="text" id="category_key" name="category_key" placeholder="web_guvenligi">
                        <small class="help-text">Raporlamada kullanılacak kısa anahtar. Örn: web_guvenligi</small>
                    </div>
                    <div class="form-group">
                        <label for="order_index">Sırası</label>
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
                    <div class="form-group checkbox">
                        <label>
                            <input type="checkbox" name="save_to_library" value="1"> Soru havuzuna ekle
                        </label>
                        <small class="help-text">Secerseniz soru ve secenekleri ayni kategori anahtariyla hazir sorulara eklenir.</small>
                    </div>
                </div>
                <div class="form-group">
                    <label for="options">Seçenekler (her satıra bir)</label>
                    <textarea id="options" name="options" rows="4" placeholder="Evet\nHayir\nKismi"></textarea>
                    <small class="help-text">Yalnızca coktan secmeli sorular icin kullanilir.</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button-primary">Soru Ekle</button>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>AI Soru Önerisi</h2>
        </div>
        <div class="panel-body">
            <form method="POST" class="inline-form">
                <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="ai_suggest">
                <label for="topic">Tema</label>
                <input type="text" id="topic" name="topic" placeholder="Orn: Calisan bagliligi" required>
                <button type="submit" class="button-secondary">Öneri Al</button>
                <?php if (!$surveyService->aiClient()->isEnabled()): ?>
                    <span class="tag">Demo modu</span>
                <?php endif; ?>
            </form>
            <?php if (!empty($aiSuggestions)): ?>
                <div class="suggestions">
                    <h3>Önerilen sorular</h3>
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
                <p>Bu ankette henüz soru yok.</p>
            <?php else: ?>
                <ul class="question-list">
                    <?php foreach ($questions as $question): ?>
                        <li class="question-item">
                            <div>
                                <strong>#<?php echo (int)$question['order_index']; ?> &raquo; <?php echo h($question['question_text']); ?></strong>
                                <span class="tag tag-<?php echo h($question['question_type']); ?>"><?php echo h($question['question_type']); ?></span>
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




