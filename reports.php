<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$overview = $db->fetchAll(
    'SELECT s.id, s.title, s.status, s.start_date, s.end_date, COUNT(sr.id) AS responses
     FROM surveys s
     LEFT JOIN survey_responses sr ON sr.survey_id = s.id
     GROUP BY s.id
     ORDER BY s.created_at DESC
     LIMIT 10'
);

$recentReports = $db->fetchAll(
    'SELECT srp.*, s.title FROM survey_reports srp
     INNER JOIN surveys s ON s.id = srp.survey_id
     ORDER BY srp.created_at DESC
     LIMIT 5'
);

$pageTitle = 'Raporlar - ' . config('app.name', 'Anketor');
$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <header class="page-header">
        <div>
            <p class="eyebrow">Analitik</p>
            <h1>Genel Raporlar</h1>
            <p class="page-subtitle">Anket performansını, trendleri ve akıllı rapor çıktılarınızı tek ekranda izleyin.</p>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-header">
            <h2>Anket Performansı</h2>
        </div>
        <div class="panel-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Başlık</th>
                        <th>Durum</th>
                        <th>Dönem</th>
                        <th>Cevap</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overview as $row): ?>
                        <tr>
                            <td><?php echo h($row['title']); ?></td>
                            <td><span class="status status-<?php echo h($row['status']); ?>"><?php echo h($row['status']); ?></span></td>
                            <td>
                                <?php echo $row['start_date'] ? h(format_date($row['start_date'])) : '-'; ?>
                                <?php if ($row['end_date']): ?> &ndash; <?php echo h(format_date($row['end_date'])); ?><?php endif; ?>
                            </td>
                            <td><?php echo (int)$row['responses']; ?></td>
                            <td><a class="button-link" href="survey_reports.php?id=<?php echo (int)$row['id']; ?>">Detay</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Son Akıllı Raporlar</h2>
        </div>
        <div class="panel-body">
            <?php if ($recentReports): ?>
                <ul class="question-list">
                    <?php foreach ($recentReports as $item): ?>
                        <?php $payload = json_decode($item['payload'] ?? '', true) ?: []; ?>
                        <li class="question-item">
                            <strong><?php echo h($item['title']); ?></strong>
                            <p class="help-text"><?php echo h($item['created_at']); ?> &mdash; Tip: <?php echo h($item['report_type']); ?></p>
                            <?php if (!empty($payload['content'])): ?>
                                <p><?php echo nl2br(h(mb_substr($payload['content'], 0, 280))); ?>...</p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Henüz rapor oluşturulmadı.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
