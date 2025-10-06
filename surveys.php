<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$surveys = $surveyService->getSurveys(200);
$statusLabels = [
    'draft' => 'Taslak',
    'scheduled' => 'Planlandı',
    'active' => 'Yayında',
    'closed' => 'Kapatıldı',
];
$statusCounts = [
    'draft' => 0,
    'scheduled' => 0,
    'active' => 0,
    'closed' => 0,
];
$nextSurveyDate = null;
$nextSurveyTimestamp = null;

foreach ($surveys as $survey) {
    $statusKey = strtolower((string)($survey['status'] ?? ''));
    if (array_key_exists($statusKey, $statusCounts)) {
        $statusCounts[$statusKey]++;
    }

    $startTimestamp = null;
    if (!empty($survey['start_date'])) {
        $startTimestamp = strtotime($survey['start_date']);
    }

    if ($startTimestamp !== false && $startTimestamp !== null && $startTimestamp > time()) {
        if ($nextSurveyTimestamp === null || $startTimestamp < $nextSurveyTimestamp) {
            $nextSurveyTimestamp = $startTimestamp;
            $nextSurveyDate = $survey['start_date'];
        }
    }
}

$totalSurveys = count($surveys);
$activeSurveys = $statusCounts['active'];
$pageTitle = 'Anketler - ' . config('app.name', 'Anketor');
$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="page page--surveys">
    <header class="page-hero">
        <div class="page-hero__content container">
            <div class="page-hero__text">
                <span class="page-hero__eyebrow">Anketler</span>
                <h1 class="page-hero__title">Stratejik Anketler</h1>
                <p class="page-hero__subtitle"><?php echo count($surveys); ?> kayıtlı anket ile organizasyon güvenliğini ölçün.</p>
            </div>
            <div class="page-hero__actions">
                <a class="button-primary" href="survey_edit.php">Yeni Anket</a>
                <a class="button-secondary" href="dashboard.php">Gösterge Paneli</a>
            </div>
        </div>
    </header>

    <div class="container">

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <?php if (!empty($surveys)): ?>
        <?php
        $nextSurveyHint = $nextSurveyDate ? 'Sonraki başlangıç ' . format_date($nextSurveyDate) : 'Yeni tarih ekleyin';
        $insights = [
            [
                'label' => 'Toplam Anket',
                'value' => $totalSurveys,
                'hint' => 'Sistemde kayıtlı',
            ],
            [
                'label' => 'Yayında',
                'value' => $activeSurveys,
                'hint' => 'Katılımcılara açık',
            ],
            [
                'label' => 'Planlanan',
                'value' => $statusCounts['scheduled'],
                'hint' => $nextSurveyHint,
            ],
            [
                'label' => 'Taslaklar',
                'value' => $statusCounts['draft'],
                'hint' => 'Hazır fakat yayında değil',
            ],
            [
                'label' => 'Kapatıldı',
                'value' => $statusCounts['closed'],
                'hint' => 'Arşivlenen çalışmalar',
            ],
        ];
        ?>
        <section class="insight-grid" aria-label="Anket özetleri">
            <?php foreach ($insights as $insight): ?>
                <article class="insight-card">
                    <div class="insight-card__meta">
                        <span class="insight-card__label"><?php echo h($insight['label']); ?></span>
                        <span class="insight-card__hint"><?php echo h($insight['hint']); ?></span>
                    </div>
                    <strong class="insight-card__value"><?php echo h((string)$insight['value']); ?></strong>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if (empty($surveys)): ?>
        <section class="empty-state">
            <h2>Henüz anket oluşturulmadı</h2>
            <p>Hazır soru havuzunu kullanarak dakikalar içinde ilk anketinizi yayınlayabilirsiniz.</p>
            <a class="button-primary" href="survey_edit.php">İlk anketi oluştur</a>
        </section>
    <?php else: ?>
        <section class="survey-grid" aria-label="Anket listesi">
            <?php foreach ($surveys as $survey): ?>
                <?php
                $period = $survey['start_date'] ? format_date($survey['start_date']) : '-';
                if (!empty($survey['end_date'])) {
                    $period .= ' – ' . format_date($survey['end_date']);
                }
                $description = $survey['description'] ?? '';
                if ($description) {
                    if (function_exists('mb_strimwidth')) {
                        $description = mb_strimwidth($description, 0, 140, '...');
                    } elseif (strlen($description) > 140) {
                        $description = substr($description, 0, 137) . '...';
                    }
                }
                $statusKey = strtolower((string)($survey['status'] ?? ''));
                $statusLabel = $statusLabels[$statusKey] ?? ($survey['status'] ? ucfirst((string)$survey['status']) : 'Bilinmiyor');
                ?>
                <article class="survey-card">
                    <div class="survey-card__body">
                        <header class="survey-card__header">
                            <div class="survey-card__status">
                                <span class="status status-<?php echo h($statusKey ?: 'unknown'); ?>"><?php echo h($statusLabel); ?></span>
                                <?php if (!empty($survey['created_at'])): ?>
                                    <span class="survey-card__status-date">Oluşturma: <?php echo h(format_date($survey['created_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <h2 class="survey-card__title"><?php echo h($survey['title']); ?></h2>
                        </header>
                        <?php if ($description): ?>
                            <p class="survey-card__description"><?php echo h($description); ?></p>
                        <?php endif; ?>
                    </div>
                    <dl class="survey-meta">
                        <div>
                            <dt>Dönem</dt>
                            <dd><?php echo h($period); ?></dd>
                        </div>
                        <div>
                            <dt>Kategori</dt>
                            <dd><?php echo h($survey['category_name'] ?? '-'); ?></dd>
                        </div>
                        <div>
                            <dt>Sahip</dt>
                            <dd><?php echo h($survey['owner_name'] ?? '-'); ?></dd>
                        </div>
                    </dl>
                    <footer class="survey-card__footer">
                        <div class="survey-card__chips">
                            <?php if ($statusKey === 'active'): ?>
                                <span class="chip chip-success">Yanıt topluyor</span>
                            <?php elseif ($statusKey === 'scheduled'): ?>
                                <span class="chip chip-warning">
                                    <?php
                                    $scheduledHint = !empty($survey['start_date'])
                                        ? 'Başlangıç ' . format_date($survey['start_date'])
                                        : 'Planlandı';
                                    echo h($scheduledHint);
                                    ?>
                                </span>
                            <?php elseif ($statusKey === 'draft'): ?>
                                <span class="chip chip-muted">Taslak aşamasında</span>
                            <?php elseif ($statusKey === 'closed'): ?>
                                <span class="chip chip-danger">Kapatıldı</span>
                            <?php endif; ?>

                            <?php if (!empty($survey['end_date'])): ?>
                                <span class="chip chip-muted">Bitiş: <?php echo h(format_date($survey['end_date'])); ?></span>
                            <?php endif; ?>

                            <?php if (!empty($survey['owner_name'])): ?>
                                <span class="chip">Sorumlu: <?php echo h($survey['owner_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="survey-card__actions">
                            <a class="button-secondary" href="survey_questions.php?id=<?php echo (int)$survey['id']; ?>">Sorular</a>
                            <a class="button-secondary" href="participants.php?id=<?php echo (int)$survey['id']; ?>">Katılımcılar</a>
                            <a class="button-link" href="survey_edit.php?id=<?php echo (int)$survey['id']; ?>">Ayarlar</a>
                            <a class="button-link" href="survey_reports.php?id=<?php echo (int)$survey['id']; ?>">Rapor</a>
                        </div>
                    </footer>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>

