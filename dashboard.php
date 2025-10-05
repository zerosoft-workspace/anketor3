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
$primarySurvey = $recentSurveys[0] ?? null;
$totalInvites = (int)$stats['responses'] + (int)$stats['pending'];
$responseRate = $totalInvites > 0 ? round(($stats['responses'] / $totalInvites) * 100) : 0;
$pageTitle = 'Gösterge Paneli - ' . config('app.name', 'Anketor');
$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <section class="hero-card">
        <div class="hero-card__content">
            <p class="eyebrow">Kontrol Merkezi</p>
            <h1>Merhaba <?php echo h(current_user_name()); ?></h1>
            <p class="hero-lead">Ekibinizin siber güvenlik nabzını buradan yönetin. <?php echo (int)$stats['active']; ?> aktif anket ve <?php echo (int)$stats['pending']; ?> bekleyen davet sizi bekliyor.</p>
            <div class="hero-metrics">
                <div class="metric">
                    <span class="metric-label">Yanıt Oranı</span>
                    <strong class="metric-value"><?php echo $totalInvites > 0 ? $responseRate . '%': 'N/A'; ?></strong>
                </div>
                <div class="metric">
                    <span class="metric-label">Son Anket</span>
                    <strong class="metric-value"><?php echo $primarySurvey ? h($primarySurvey['title']) : 'Henüz yok'; ?></strong>
                </div>
            </div>
            <div class="hero-actions">
                <a class="button-primary" href="survey_edit.php">Yeni Anket Oluştur</a>
                <?php if (!empty($primarySurvey['id'])): ?>
                    <a class="button-secondary" href="participants.php?id=<?php echo (int)$primarySurvey['id']; ?>">Katılımcıları Yönet</a>
                <?php endif; ?>
                <a class="button-link" href="reports.php">Rapor Merkezi</a>
            </div>
        </div>
        <div class="hero-card__sidebar">
            <h3>Hızlı İşlemler</h3>
            <ul class="quick-actions">
                <li>
                    <span class="quick-actions__icon">✨</span>
                    <span>
                        <strong>AI önerilerini güncelleyin</strong>
                        <small>Raporlardan manuel not ekleyerek kişisel önerileri güçlendirin.</small>
                    </span>
                </li>
                <li>
                    <span class="quick-actions__icon">🗂️</span>
                    <span>
                        <strong>Soru havuzunu düzenleyin</strong>
                        <small>Hazır soruları anketlere tek tıkla ekleyin.</small>
                    </span>
                </li>
                <li>
                    <span class="quick-actions__icon">📧</span>
                    <span>
                        <strong>Davetleri yeniden gönderin</strong>
                        <small>Katılım oranını artırmak için hatırlatma planlayın.</small>
                    </span>
                </li>
            </ul>
        </div>
    </section>

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
            <a class="button-secondary" href="surveys.php">Tümünü Gör</a>
        </div>
        <div class="panel-body">
            <?php if (empty($recentSurveys)): ?>
                <p>Henüz anket bulunmuyor.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Başlık</th>
                            <th>Kategori</th>
                            <th>Durum</th>
                            <th>Başlangıç</th>
                            <th>Bitiş</th>
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
                                    <a class="button-link" href="survey_edit.php?id=<?php echo (int)$survey['id']; ?>">Düzenle</a>
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
