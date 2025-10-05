<?php
require __DIR__ . '/includes/bootstrap.php';

$responseId = isset($_GET['response']) ? (int)$_GET['response'] : 0;
$participantId = isset($_GET['participant']) ? (int)$_GET['participant'] : 0;
$token = $_GET['token'] ?? null;

if ($participantId > 0) {
    $bundle = $surveyService->getParticipantResponses($participantId);
} elseif ($responseId > 0) {
    $bundle = $surveyService->getResponseReport($responseId);
} else {
    http_response_code(404);
    exit('Rapor bulunamadı.');
}

$report = $surveyService->generatePersonalReport($bundle);
if (!$report) {
    http_response_code(404);
    exit('Rapor bulunamadı.');
}

$surveyMeta = is_array($report['survey'] ?? null) ? $report['survey'] : [];
$participantInfo = $report['participant'] ?? [];
$responseMeta = is_array($report['response'] ?? null) ? $report['response'] : [];
$responseId = $responseMeta['id'] ?? $responseId;
$allowed = false;

if (current_user_id()) {
    $allowed = true;
} else {
    if (!empty($participantInfo['token']) && $token && hash_equals($participantInfo['token'], $token)) {
        $allowed = true;
    } elseif (!empty($_SESSION['personal_report_response']) && (int)$_SESSION['personal_report_response'] === $responseId) {
        $allowed = true;
    }
}

if (!$allowed) {
    http_response_code(403);
    exit('Bu rapora erişim yetkiniz yok.');
}

if (!empty($_SESSION['personal_report_response']) && (int)$_SESSION['personal_report_response'] === $responseId) {
    unset($_SESSION['personal_report_response']);
}

$responseMeta = is_array($responseMeta) ? $responseMeta : [];
$submittedAt = $responseMeta['submitted_at'] ?? null;
$hasCategories = !empty($report['categories']);
$averageScore = $report['overview']['average_score'] ?? null;
$strengths = $report['overview']['strengths'] ?? [];
$gaps = $report['overview']['gaps'] ?? [];
$advice = $hasCategories ? ($report['advice'] ?? '') : '';

include __DIR__ . '/templates/header.php';
if (current_user_id()) {
    include __DIR__ . '/templates/navbar.php';
}
?>
<main class="container personal-report">
    <section class="panel">
        <div class="panel-header">
            <h1>Kişisel Güvenlik Raporu</h1>
            <?php if ($submittedAt): ?>
                <span class="badge badge-time"><?php echo h(format_date($submittedAt, 'd.m.Y H:i')); ?></span>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <?php if (!empty($surveyMeta['title'])): ?>
                <p class="report-subtitle">Anket: <?php echo h($surveyMeta['title']); ?></p>
            <?php endif; ?>
            <?php if (!empty($participantInfo['email'])): ?>
                <p class="report-subtitle">Sayın <?php echo h($participantInfo['email']); ?>, verdiğiniz yanıtların kısa özeti:</p>
            <?php endif; ?>
            <div class="report-overview">
                <div class="overview-item">
                    <span class="label">Genel Ortalama</span>
                    <span class="value"><?php echo $averageScore !== null ? h($averageScore) : ' - '; ?></span>
                </div>
                <div class="overview-item">
                    <span class="label">Güçlü Alanlar</span>
                    <span class="value"><?php echo $strengths ? h(implode(', ', $strengths)) : 'Henüz belirlenmedi'; ?></span>
                </div>
                <div class="overview-item">
                    <span class="label">Gelişim Alanları</span>
                    <span class="value"><?php echo $gaps ? h(implode(', ', $gaps)) : 'Belirlenmedi'; ?></span>
                </div>
            </div>
        </div>
    </section>

    <?php if ($hasCategories): ?>
        <?php foreach ($report['categories'] as $category): ?>
            <section class="panel category-card">
                <div class="panel-header">
                    <h2><?php echo h($category['label']); ?></h2>
                    <?php if ($category['average_score'] !== null): ?>
                        <span class="badge badge-score"><?php echo h($category['average_score']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="panel-body">
                    <ul class="answer-list">
                        <?php foreach ($category['questions'] as $question): ?>
                            <li>
                                <strong><?php echo h($question['question']); ?></strong>
                                <div class="answer-value">
                                    <?php if ($question['type'] === 'rating'): ?>
                                        <span class="score-chip">Puan: <?php echo h($question['answer']); ?></span>
                                    <?php elseif ($question['type'] === 'multiple_choice'): ?>
                                        <span class="choice-chip"><?php echo h($question['answer']); ?></span>
                                    <?php else: ?>
                                        <span class="text-answer"><?php echo nl2br(h($question['answer'] ?? '')); ?></span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
        <section class="panel category-card">
            <div class="panel-body">
                <p>Bu katılımcı için henüz yanıt kaydedilmemiştir.</p>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($advice)): ?>
        <section class="panel">
            <div class="panel-header">
                <h2>Önerilen Aksiyon Planı</h2>
            </div>
            <div class="panel-body">
                <p class="advice-text"><?php echo nl2br(h($advice)); ?></p>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-body report-actions">
            <a class="button-secondary" href="index.php">Ana Sayfaya Dön</a>
            <?php if (current_user_id() && !empty($surveyMeta['id'])): ?>
                <a class="button-secondary" href="survey_reports.php?id=<?php echo (int)$surveyMeta['id']; ?>">Anket Raporuna Git</a>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
