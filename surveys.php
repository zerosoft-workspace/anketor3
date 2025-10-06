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

$filterKey = strtolower($_GET['status'] ?? 'all');
$validFilters = array_merge(['all'], array_keys($statusLabels));
if (!in_array($filterKey, $validFilters, true)) {
    $filterKey = 'all';
}

$filteredSurveys = array_values(array_filter($surveys, function ($survey) use ($filterKey) {
    if ($filterKey === 'all') {
        return true;
    }
    $status = strtolower((string)($survey['status'] ?? ''));
    return $status === $filterKey;
}));

$totalSurveys = count($surveys);
$activeSurveys = $statusCounts['active'];
$aiConfig = config('ai', config('openai', []));
$aiProviderLabels = [
    'openai' => 'OpenAI',
    'azure_openai' => 'Azure OpenAI',
    'google_gemini' => 'Google Gemini',
    'mock' => 'Test Motoru',
];
$aiProviderKey = $aiConfig['provider'] ?? 'openai';
$aiProviderLabel = $aiProviderLabels[$aiProviderKey] ?? ucfirst((string)$aiProviderKey);
$aiModel = $aiConfig['model'] ?? 'gpt-4o-mini';
$aiActive = !empty($aiConfig['api_key']);
$aiStatusLabel = $aiActive ? 'Aktif' : 'Pasif';
$aiStatusClass = $aiActive ? 'status-badge--active' : 'status-badge--inactive';
$nextSurveyHint = $nextSurveyDate ? 'Sonraki başlangıç ' . format_date($nextSurveyDate) : 'Yeni tarih ekleyin';
$pageTitle = 'Anketler - ' . config('app.name', 'Anketor');
$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="page page--surveys">
    <section class="surveys-hero">
        <div class="container">
            <div class="surveys-hero__layout">
                <div class="surveys-hero__intro">
                    <p class="eyebrow">Anket Merkezi</p>
                    <h1><?php echo h(config('app.name', 'Anketor')); ?> anketleri</h1>
                    <p class="surveys-hero__subtitle"><?php echo $totalSurveys; ?> aktif kayıtlı anket ile ekiplerin nabzını tutun, güvenlik kültürünü güçlendirin.</p>
                    <div class="surveys-hero__actions">
                        <a class="button-primary" href="survey_edit.php">Yeni Anket Oluştur</a>
                        <a class="button-secondary" href="dashboard.php">Gösterge Paneline Dön</a>
                    </div>
                </div>
                <aside class="surveys-hero__ai" aria-label="Yapay zeka yapılandırması">
                    <div class="ai-card">
                        <header class="ai-card__header">
                            <span class="ai-card__title">Yapay Zeka Motoru</span>
                            <span class="status-badge <?php echo $aiStatusClass; ?>"><?php echo h($aiStatusLabel); ?></span>
                        </header>
                        <dl class="ai-card__meta">
                            <div>
                                <dt>Sağlayıcı</dt>
                                <dd><?php echo h($aiProviderLabel); ?></dd>
                            </div>
                            <div>
                                <dt>Model</dt>
                                <dd><?php echo h($aiModel); ?></dd>
                            </div>
                            <div>
                                <dt>Öneri Durumu</dt>
                                <dd><?php echo $aiActive ? 'Akıllı raporlar hazır.' : 'Anahtar girmeden öneriler sınırlı.'; ?></dd>
                            </div>
                        </dl>
                        <?php if (is_super_admin()): ?>
                            <a class="ai-card__link" href="system_settings.php">Ayarları yönet</a>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>

            <?php if (!empty($surveys)): ?>
                <?php
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
                        'hint' => 'Hazırlık aşamasında',
                    ],
                    [
                        'label' => 'Kapatıldı',
                        'value' => $statusCounts['closed'],
                        'hint' => 'Arşivlenen çalışmalar',
                    ],
                ];
                ?>
                <div class="surveys-hero__metrics" aria-label="Anket özetleri">
                    <?php foreach ($insights as $insight): ?>
                        <article class="metric-card">
                            <header>
                                <span class="metric-card__label"><?php echo h($insight['label']); ?></span>
                                <span class="metric-card__hint"><?php echo h($insight['hint']); ?></span>
                            </header>
                            <strong class="metric-card__value"><?php echo h((string)$insight['value']); ?></strong>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="container">
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
            <?php
            $filterTabs = [
                ['key' => 'all', 'label' => 'Tümü', 'count' => $totalSurveys],
            ];
            foreach ($statusLabels as $key => $label) {
                $filterTabs[] = [
                    'key' => $key,
                    'label' => $label,
                    'count' => $statusCounts[$key] ?? 0,
                ];
            }
            ?>
            <div class="surveys-toolbar" role="toolbar" aria-label="Anket filtreleri">
                <div class="surveys-toolbar__filters">
                    <?php foreach ($filterTabs as $tab): ?>
                        <a class="filter-chip <?php echo $filterKey === $tab['key'] ? 'is-active' : ''; ?>" href="?status=<?php echo h($tab['key']); ?>">
                            <span><?php echo h($tab['label']); ?></span>
                            <span class="filter-chip__count"><?php echo (int)$tab['count']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="surveys-toolbar__context">
                    <?php if ($filterKey === 'all'): ?>
                        <span class="toolbar-hint">Tüm anketler gösteriliyor.</span>
                    <?php else: ?>
                        <span class="toolbar-hint"><?php echo h($statusLabels[$filterKey] ?? 'Seçim'); ?> statüsünde <?php echo count($filteredSurveys); ?> sonuç.</span>
                    <?php endif; ?>
                </div>
            </div>

            <section class="survey-grid" aria-label="Anket listesi">
                <?php foreach ($filteredSurveys as $survey): ?>
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
                $progressMap = [
                    'draft' => 25,
                    'scheduled' => 55,
                    'active' => 85,
                    'closed' => 100,
                ];
                $progress = $progressMap[$statusKey] ?? 40;
                $scheduledHint = '';
                if ($statusKey === 'scheduled') {
                    $scheduledHint = !empty($survey['start_date']) ? 'Başlangıç ' . format_date($survey['start_date']) : 'Planlandı';
                }
                ?>
                <article class="survey-card">
                    <div class="survey-card__progress" style="--progress: <?php echo (int)$progress; ?>%"></div>
                    <div class="survey-card__body">
                        <header class="survey-card__header">
                            <span class="status status-<?php echo h($statusKey ?: 'unknown'); ?>"><?php echo h($statusLabel); ?></span>
                            <?php if (!empty($survey['created_at'])): ?>
                                <span class="survey-card__timestamp">Oluşturma: <?php echo h(format_date($survey['created_at'])); ?></span>
                            <?php endif; ?>
                        </header>
                        <h2 class="survey-card__title"><?php echo h($survey['title']); ?></h2>
                        <?php if ($description): ?>
                            <p class="survey-card__description"><?php echo h($description); ?></p>
                        <?php endif; ?>
                        <dl class="survey-card__meta">
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
                    </div>
                    <footer class="survey-card__footer">
                        <div class="survey-card__badges">
                            <?php if ($statusKey === 'active'): ?>
                                <span class="chip chip-success">Yanıt topluyor</span>
                            <?php elseif ($statusKey === 'scheduled'): ?>
                                <span class="chip chip-warning"><?php echo h($scheduledHint); ?></span>
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

            <?php if (empty($filteredSurveys)): ?>
                <p class="no-results">Seçilen statüde anket bulunamadı. Farklı bir filtre deneyin.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>

