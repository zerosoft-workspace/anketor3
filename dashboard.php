<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$stats = [
    'surveys' => $db->fetch('SELECT COUNT(*) AS total FROM surveys')['total'] ?? 0,
    'active' => $db->fetch("SELECT COUNT(*) AS total FROM surveys WHERE status = 'active'")['total'] ?? 0,
    'responses' => $db->fetch('SELECT COUNT(*) AS total FROM survey_responses')['total'] ?? 0,
    'pending' => $db->fetch('SELECT COUNT(*) AS total FROM survey_participants WHERE responded_at IS NULL')['total'] ?? 0,
];

$recentSurveys = $surveyService->getSurveys(5);
$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <section class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">Toplam Anket</span>
            <span class="stat-value"><?php echo (int)$stats['surveys']; ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Aktif Anket</span>
            <span class="stat-value"><?php echo (int)$stats['active']; ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Toplanan Cevap</span>
            <span class="stat-value"><?php echo (int)$stats['responses']; ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Bekleyen Davet</span>
            <span class="stat-value"><?php echo (int)$stats['pending']; ?></span>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Son Anketler</h2>
            <a class="button-primary" href="survey_edit.php">Yeni Anket</a>
        </div>
        <div class="panel-body">
            <?php if (empty($recentSurveys)): ?>
                <p>Henüz anket bulunmuyor.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Baslik</th>
                            <th>Kategori</th>
                            <th>Durum</th>
                            <th>Baslangic</th>
                            <th>Bitis</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSurveys as $survey): ?>
                            <tr>
                                <td><?php echo h($survey['title']); ?></td>
                                <td><?php echo h($survey['category_name'] ?? '-'); ?></td>
                                <td><span class="status status-<?php echo h($survey['status']); ?>"><?php echo h($survey['status']); ?></span></td>
                                <td><?php echo $survey['start_date'] ? h(format_date($survey['start_date'])) : '-'; ?></td>
                                <td><?php echo $survey['end_date'] ? h(format_date($survey['end_date'])) : '-'; ?></td>
                                <td>
                                    <a class="button-link" href="survey_edit.php?id=<?php echo (int)$survey['id']; ?>">Duzenle</a>
                                    <a class="button-link" href="survey_questions.php?id=<?php echo (int)$survey['id']; ?>">Sorular</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
