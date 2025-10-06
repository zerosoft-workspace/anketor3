<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$surveyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$survey = $surveyService->getSurvey($surveyId);
if (!$survey) {
    set_flash('danger', 'Anket bulunamadı.');
    redirect('surveys.php');
}
$pageTitle = 'Anket Raporu - ' . ($survey['title'] ?? config('app.name', 'Anketor'));

if (is_post()) {
    guard_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'smart_report') {
        $analytics = get_survey_analytics($db, $surveyId);
        $payload = [
            'survey' => $survey,
            'responses' => $analytics['totals']['responses'] ?? 0,
            'highlights' => array_map(function ($item) {
                return $item['text'] ?? '';
            }, array_slice(array_values($analytics['questions']), 0, 3)),
        ];
        $aiReport = $surveyService->aiClient()->generateSmartReport($payload);
        $surveyService->recordReport($surveyId, 'smart', ['content' => $aiReport]);
        set_flash('success', 'Akıllı rapor oluşturuldu.');
        redirect('survey_reports.php?id=' . $surveyId);
    }

    if ($action === 'save_manual') {
        $prompt = trim($_POST['prompt'] ?? '');
        $suggestion = trim($_POST['suggestion'] ?? '');

        if ($suggestion === '') {
            set_flash('danger', 'AI bilgi kaydını oluşturmak için en azından öneriyi yazmanız gerekiyor.');
        } else {
            $surveyService->addAISuggestion($surveyId, $prompt, $suggestion);
            set_flash('success', 'AI bilgi bankasına yeni kayıt eklendi.');
        }

        redirect('survey_reports.php?id=' . $surveyId);
    }

    if ($action === 'delete_manual') {
        $suggestionId = (int)($_POST['suggestion_id'] ?? 0);
        if ($suggestionId > 0) {
            $surveyService->deleteAISuggestion($surveyId, $suggestionId);
            set_flash('success', 'Kayıt kaldırıldı.');
        }

        redirect('survey_reports.php?id=' . $surveyId);
    }
}

$analytics = get_survey_analytics($db, $surveyId);
$smartReports = $surveyService->getReports($surveyId, 'smart');
$manualSuggestions = $surveyService->getAISuggestions($surveyId);

$trendData = [];
if (!empty($survey['category_id'])) {
    $other = $db->fetchAll(
        'SELECT id, title, start_date FROM surveys WHERE category_id = ? AND id <> ? ORDER BY start_date DESC LIMIT 2',
        [$survey['category_id'], $surveyId]
    );
    foreach ($other as $row) {
        $trendAnalytics = get_survey_analytics($db, (int)$row['id']);
        $trendData[] = [
            'survey' => $row,
            'responses' => $trendAnalytics['totals']['responses'] ?? 0,
            'avg' => array_reduce($trendAnalytics['questions'], function ($carry, $item) {
                if ($item['type'] === 'rating' && $item['average']) {
                    $carry[] = $item['average'];
                }
                return $carry;
            }, []),
        ];
    }
}

$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <header class="page-header">
        <div>
            <p class="eyebrow">Raporlama</p>
            <h1><?php echo h($survey['title']); ?></h1>
            <p class="page-subtitle">Katılımcı yanıtları, trend analizleri ve AI önerileri tek ekranda.</p>
        </div>
        <div class="page-header__actions">
            <a class="button-secondary" href="surveys.php">Anketlere Dön</a>
            <a class="button-secondary" href="export_excel.php?survey_id=<?php echo $surveyId; ?>" target="_blank">Excel</a>
            <a class="button-secondary" href="export_pdf.php?survey_id=<?php echo $surveyId; ?>" target="_blank">PDF</a>
            <a class="button-secondary" href="export_chart.php?survey_id=<?php echo $surveyId; ?>" target="_blank">Grafik</a>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <section class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">Toplam Cevap</span>
            <span class="stat-value"><?php echo (int)($analytics['totals']['responses'] ?? 0); ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Katılımcı</span>
            <span class="stat-value"><?php echo (int)($analytics['totals']['participants'] ?? 0); ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Son Kayıt</span>
            <span class="stat-value"><?php echo !empty($analytics['totals']['last_response']) ? h(format_date($analytics['totals']['last_response'], 'd.m.Y H:i')) : '-'; ?></span>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Soru Bazlı Sonuçlar</h2>
        </div>
        <div class="panel-body">
            <?php foreach ($analytics['questions'] as $question): ?>
                <article class="question-item">
                    <header>
                        <strong><?php echo h($question['text']); ?></strong>
                        <span class="tag tag-<?php echo h($question['type']); ?>"><?php echo h($question['type']); ?></span>
                    </header>
                    <?php if ($question['type'] === 'rating'): ?>
                        <p>Ortalama Puan: <strong><?php echo $question['average'] !== null ? $question['average'] : '-'; ?></strong></p>
                    <?php elseif ($question['type'] === 'multiple_choice'): ?>
                        <ul class="option-list">
                            <?php foreach ($question['distribution'] as $dist): ?>
                                <li><?php echo h($dist['label']); ?> - <?php echo (int)$dist['count']; ?> cevap (%<?php echo $dist['percent']; ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <?php $recent = array_slice($question['answers'], 0, 5); ?>
                        <?php if ($recent): ?>
                            <ul class="option-list">
                                <?php foreach ($recent as $answer): ?>
                                    <li><?php echo h($answer); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>Yanıt bulunmuyor.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Anahtar Kelime Bulutu</h2>
        </div>
        <div class="panel-body">
            <?php if (!empty($analytics['keywords'])): ?>
                <div class="keyword-cloud">
                    <?php foreach ($analytics['keywords'] as $keyword => $count): ?>
                        <span style="font-size: <?php echo 0.8 + ($count * 0.2); ?>rem" class="tag"><?php echo h($keyword); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Acik uclu yanit bulunmuyor.</p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($trendData): ?>
        <section class="panel">
            <div class="panel-header">
                <h2>Trend Karşılaştırma</h2>
            </div>
            <div class="panel-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Anket</th>
                            <th>Cevap</th>
                            <th>Genel Ortalama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo h($survey['title']); ?></td>
                            <td><?php echo (int)($analytics['totals']['responses'] ?? 0); ?></td>
                            <td><?php echo average_from_questions($analytics['questions']); ?></td>
                        </tr>
                        <?php foreach ($trendData as $trend): ?>
                            <tr>
                                <td><?php echo h($trend['survey']['title']); ?></td>
                                <td><?php echo (int)$trend['responses']; ?></td>
                                <td><?php echo average_from_values($trend['avg']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-header">
            <h2>Akıllı Rapor</h2>
            <form method="POST">
                <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="smart_report">
                <button type="submit" class="button-primary">Raporu Yenile</button>
            </form>
        </div>
        <div class="panel-body">
            <?php if ($smartReports): ?>
                <article class="card">
                    <pre style="white-space: pre-wrap; font-family: inherit; margin: 0;"><?php echo h($smartReports[0]['payload']['content'] ?? ''); ?></pre>
                    <p class="help-text">Olusturulma: <?php echo h($smartReports[0]['created_at']); ?></p>
                </article>
            <?php else: ?>
                <p>Henüz akıllı rapor oluşturulmadı.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>AI Bilgi Bankası</h2>
        </div>
        <div class="panel-body">
            <form method="POST" class="stacked-form">
                <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="save_manual">
                <div class="form-group">
                    <label for="prompt">Açıklama / Başlık</label>
                    <input type="text" id="prompt" name="prompt" placeholder="Örn: Web Güvenliği için özelleştirilmiş not">
                </div>
                <div class="form-group">
                    <label for="suggestion">AI Önerisi</label>
                    <textarea id="suggestion" name="suggestion" rows="4" placeholder="Kendi uzman önerilerinizi ekleyin"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button-secondary">Bilgiyi Kaydet</button>
                </div>
            </form>

            <?php if ($manualSuggestions): ?>
                <ul class="knowledge-list">
                    <?php foreach ($manualSuggestions as $item): ?>
                        <li class="knowledge-item">
                            <header>
                                <strong><?php echo h($item['prompt'] ?: 'Serbest not'); ?></strong>
                                <span class="help-text"><?php echo h(format_date($item['created_at'], 'd.m.Y H:i')); ?></span>
                            </header>
                            <p><?php echo nl2br(h($item['suggestion'])); ?></p>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="action" value="delete_manual">
                                <input type="hidden" name="suggestion_id" value="<?php echo (int)$item['id']; ?>">
                                <button type="submit" class="button-link text-danger" onclick="return confirm('Kaydı silmek istediğinizden emin misiniz?');">Sil</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="help-text">Henüz manuel bilgi eklenmedi. Yukaridaki form ile AI raporlarina dahil edilecek temel bilgileri paylasabilirsiniz.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
