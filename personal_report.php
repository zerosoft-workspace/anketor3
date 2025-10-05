<?php
require __DIR__ . '/includes/bootstrap.php';

$responseId = isset($_GET['response']) ? (int)$_GET['response'] : 0;
$token = $_GET['token'] ?? null;

if ($responseId <= 0) {
    http_response_code(404);
    exit('Rapor bulunamadý.');
}

$report = $surveyService->generatePersonalReport($responseId);
if (!$report) {
    http_response_code(404);
    exit('Rapor bulunamadý.');
}

$participantInfo = $report['participant'] ?? [];
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
    exit('Bu rapora eriþim yetkiniz yok.');
}

if (!empty($_SESSION['personal_report_response']) && (int)$_SESSION['personal_report_response'] === $responseId) {
    unset($_SESSION['personal_report_response']);
}

$submittedAt = $report['response']['submitted_at'] ?? null;
$averageScore = $report['overview']['average_score'] ?? null;
$strengths = $report['overview']['strengths'] ?? [];
$gaps = $report['overview']['gaps'] ?? [];
$advice = $report['advice'] ?? '';

include __DIR__ . '/templates/header.php';
if (current_user_id()) {
    include __DIR__ . '/templates/navbar.php';
}
?>
<main class="container personal-report">
    <section class="panel">
        <div class="panel-header">
            <h1>Kiþisel Güvenlik Raporu</h1>
            <?php if ($submittedAt): ?>
                <span class="badge badge-time"><?php echo h(format_date($submittedAt, 'd.m.Y H:i')); ?></span>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <?php if (!empty($participantInfo['email'])): ?>
                <p class="report-subtitle">Sayýn <?php echo h($participantInfo['email']); ?>, verdiðiniz yanýtlarýn kýsa özeti:</p>
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
                    <span class="label">Geliþim Alanlarý</span>
                    <span class="value"><?php echo $gaps ? h(implode(', ', $gaps)) : 'Belirlenmedi'; ?></span>
                </div>
            </div>
        </div>
    </section>

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

    <?php if (!empty($advice)): ?>
        <section class="panel">
            <div class="panel-header">
                <h2>Önerilen Aksiyon Planý</h2>
            </div>
            <div class="panel-body">
                <p class="advice-text"><?php echo nl2br(h($advice)); ?></p>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-body report-actions">
            <a class="button-secondary" href="thank_you.php">Dönüþ Yap</a>
            <?php if (current_user_id()): ?>
                <a class="button-secondary" href="survey_reports.php?id=<?php echo (int)$report['survey']['id']; ?>">Anket Raporuna Git</a>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
