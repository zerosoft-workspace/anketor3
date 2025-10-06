<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$surveys = $surveyService->getSurveys(200);
$pageTitle = 'Anketler - ' . config('app.name', 'Anketor');
$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <header class="page-header">
        <div>
            <p class="eyebrow">Anketler</p>
            <h1>Stratejik Anketler</h1>
            <p class="page-subtitle"><?php echo count($surveys); ?> kayıtlı anket ile organizasyon güvenliğini ölçün.</p>
        </div>
        <div class="page-header__actions">
            <a class="button-primary" href="survey_edit.php">Yeni Anket</a>
            <a class="button-secondary" href="dashboard.php">Gösterge Paneli</a>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <?php if (empty($surveys)): ?>
        <section class="empty-state">
            <h2>Henüz anket oluşturulmadı</h2>
            <p>Hazır soru havuzunu kullanarak dakikalar içinde ilk anketinizi yayınlayabilirsiniz.</p>
            <a class="button-primary" href="survey_edit.php">İlk anketi oluştur</a>
        </section>
    <?php else: ?>
        <section class="survey-grid">
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
                ?>
                <article class="survey-card">
                    <header class="survey-card__header">
                        <span class="status status-<?php echo h($survey['status']); ?>"><?php echo h($survey['status']); ?></span>
                        <h2><?php echo h($survey['title']); ?></h2>
                        <?php if ($description): ?>
                            <p><?php echo h($description); ?></p>
                        <?php endif; ?>
                    </header>
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
                    <div class="survey-card__actions">
                        <a class="button-secondary" href="survey_questions.php?id=<?php echo (int)$survey['id']; ?>">Sorular</a>
                        <a class="button-secondary" href="participants.php?id=<?php echo (int)$survey['id']; ?>">Katılımcılar</a>
                        <a class="button-link" href="survey_edit.php?id=<?php echo (int)$survey['id']; ?>">Ayarlar</a>
                        <a class="button-link" href="survey_reports.php?id=<?php echo (int)$survey['id']; ?>">Rapor</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>

