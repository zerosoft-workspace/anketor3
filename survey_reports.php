<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$surveyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$survey = $surveyService->getSurvey($surveyId);
if (!$survey) {
    set_flash('danger', 'Anket bulunamadi.');
    redirect('surveys.php');
}

if (is_post()) {
    guard_csrf();
    if (($_POST['action'] ?? '') === 'smart_report') {
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
        set_flash('success', 'Akilli rapor olusturuldu.');
        redirect('survey_reports.php?id=' . $surveyId);
    }
}

$analytics = get_survey_analytics($db, $surveyId);
$smartReports = $surveyService->getReports($surveyId, 'smart');

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
    <div class="panel-header">
        <h1>Rapor &raquo; <?php echo h($survey['title']); ?></h1>
        <div class="inline-actions">
            <a class="button-secondary" href="surveys.php">Anketlere Don</a>
            <a class="button-secondary" href="export_excel.php?survey_id=<?php echo $surveyId; ?>" target="_blank">Excel</a>
            <a class="button-secondary" href="export_pdf.php?survey_id=<?php echo $surveyId; ?>" target="_blank">PDF</a>
            <a class="button-secondary" href="export_chart.php?survey_id=<?php echo $surveyId; ?>" target="_blank">Grafik</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <section class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">Toplam Cevap</span>
            <span class="stat-value"><?php echo (int)($analytics['totals']['responses'] ?? 0); ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Katilimci</span>
            <span class="stat-value"><?php echo (int)($analytics['totals']['participants'] ?? 0); ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Son Kayit</span>
            <span class="stat-value"><?php echo !empty($analytics['totals']['last_response']) ? h(format_date($analytics['totals']['last_response'], 'd.m.Y H:i')) : '-'; ?></span>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Soru Bazli Sonuclar</h2>
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
                                <li><?php echo h($dist['label']); ?> — <?php echo (int)$dist['count']; ?> cevap (%<?php echo $dist['percent']; ?>)</li>
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
                            <p>Yanit bulunmuyor.</p>
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
                <h2>Trend Karsilastirma</h2>
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
            <h2>Akilli Rapor</h2>
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
                <p>Henuz akilli rapor olusturulmadi.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
