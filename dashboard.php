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

$aiProviderKey = config('ai.provider', config('openai.provider', 'openai'));
$aiProviderLabels = [
    'openai' => 'OpenAI',
    'azure_openai' => 'Azure OpenAI',
    'anthropic' => 'Anthropic Claude',
    'google_vertex' => 'Google Vertex AI',
];
$aiProviderLabel = $aiProviderLabels[$aiProviderKey] ?? ucwords(str_replace('_', ' ', (string)$aiProviderKey));
$aiModel = config('ai.model', config($aiProviderKey . '.model', 'gpt-4o'));
$aiDeployment = config('ai.deployment', '');
$aiBaseUrl = config('ai.base_url', '');

$quickActions = [
    [
        'icon' => '📝',
        'title' => 'Yeni anket planlayın',
        'description' => 'Hedef kitleniz için özelleştirilmiş sorularla yeni bir çalışma başlatın.',
        'url' => 'survey_edit.php',
        'label' => 'Anket oluştur',
        'hint' => null,
    ],
    [
        'icon' => '📨',
        'title' => 'Katılımcıları harekete geçirin',
        'description' => 'Yanıt vermeyen kişileri belirleyip hatırlatma gönderileri planlayın.',
        'url' => $primarySurvey ? 'participants.php?id=' . (int)$primarySurvey['id'] : null,
        'label' => $primarySurvey ? 'Katılımcıları yönet' : null,
        'hint' => $primarySurvey ? null : 'Önce aktif bir anket seçin.',
    ],
    [
        'icon' => '🧠',
        'title' => 'AI yapılandırmasını gözden geçirin',
        'description' => 'Raporlarınızın bağlamını iyileştirmek için sağlayıcı tercihlerini güncelleyin.',
        'url' => current_user_role() === 'super_admin' ? 'system_settings.php' : null,
        'label' => current_user_role() === 'super_admin' ? 'Genel ayarları aç' : null,
        'hint' => current_user_role() === 'super_admin' ? null : 'Bu alan süper admin tarafından yönetilir.',
    ],
];

$insights = [];
if ($totalInvites === 0) {
    $insights[] = 'Henüz davet gönderilmedi. İlk anketinizi planlayarak geri bildirim toplamaya başlayın.';
} elseif ($responseRate < 45) {
    $insights[] = 'Yanıt oranı %' . $responseRate . ' seviyesinde. Katılımı artırmak için hatırlatma göndermeyi değerlendirin.';
} else {
    $insights[] = 'Yanıt oranınız %' . $responseRate . ' ile güçlü görünüyor. Raporlarınızı düzenli olarak güncelleyin.';
}

if ((int)$stats['active'] === 0) {
    $insights[] = 'Şu anda aktif anket bulunmuyor. Yeni bir çalışma oluşturarak panelinizi hareketlendirebilirsiniz.';
}

if ($aiProviderLabel) {
    $insights[] = 'Rapor özetleri ' . $aiProviderLabel . ' tarafından destekleniyor. Model tercihlerini dilediğiniz zaman güncelleyebilirsiniz.';
}

$statCards = [
    [
        'icon' => '📋',
        'label' => 'Toplam anket',
        'value' => (int)$stats['surveys'],
        'meta' => 'Yayınladığınız tüm çalışmalar',
    ],
    [
        'icon' => '✨',
        'label' => 'Aktif anket',
        'value' => (int)$stats['active'],
        'meta' => 'Katılımcılara açık olanlar',
    ],
    [
        'icon' => '✅',
        'label' => 'Toplanan yanıt',
        'value' => (int)$stats['responses'],
        'meta' => 'Raporlara işlenen kayıtlar',
    ],
    [
        'icon' => '⏳',
        'label' => 'Bekleyen davet',
        'value' => (int)$stats['pending'],
        'meta' => 'Yanıt bekleyen katılımcılar',
    ],
];

$pageTitle = 'Gösterge Paneli - ' . config('app.name', 'Anketor');
$flash = get_flash();

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="dashboard">
    <?php if ($flash): ?>
        <div class="dashboard__flash alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <section class="dashboard-welcome" aria-labelledby="dashboard-title">
        <div class="dashboard-welcome__content">
            <span class="dashboard-welcome__eyebrow">Anket yönetim merkezi</span>
            <h1 id="dashboard-title" class="dashboard-welcome__title">Merhaba <?php echo h(current_user_name()); ?> 👋</h1>
            <p class="dashboard-welcome__lead">
                <?php echo (int)$stats['surveys']; ?> anket ve <?php echo (int)$stats['responses']; ?> yanıtla topluluğunuz hakkında derin içgörüler elde ediyorsunuz.
                Kontrol paneli üzerinden süreçlerinizi hızla yönetebilirsiniz.
            </p>
            <div class="dashboard-welcome__actions">
                <a class="button-primary" href="survey_edit.php">Yeni anket oluştur</a>
                <a class="button-secondary" href="surveys.php">Anketleri yönet</a>
                <a class="button-link" href="reports.php">Raporları incele</a>
            </div>
            <dl class="dashboard-welcome__meta">
                <div>
                    <dt>Aktif anket</dt>
                    <dd><?php echo (int)$stats['active']; ?></dd>
                    <small><?php echo (int)$stats['surveys']; ?> toplam çalışmadan</small>
                </div>
                <div>
                    <dt>Yanıt oranı</dt>
                    <dd><?php echo $totalInvites > 0 ? h($responseRate) . '%' : 'N/A'; ?></dd>
                    <small><?php echo $totalInvites > 0 ? h($totalInvites) . ' davet' : 'Henüz davet yok'; ?></small>
                </div>
                <div>
                    <dt>Toplanan yanıt</dt>
                    <dd><?php echo (int)$stats['responses']; ?></dd>
                    <small>Raporlara işlendi</small>
                </div>
            </dl>
        </div>
        <div class="dashboard-welcome__panel">
            <article class="welcome-card welcome-card--ai" aria-labelledby="ai-summary-title">
                <span class="welcome-card__eyebrow">Yapay zekâ</span>
                <h2 id="ai-summary-title" class="welcome-card__title"><?php echo h($aiProviderLabel); ?></h2>
                <p class="welcome-card__lead">Seçilen sağlayıcı rapor yorumlarını ve içgörü önerilerini kişiselleştirir.</p>
                <dl class="data-list">
                    <?php if (!empty($aiModel)): ?>
                        <div>
                            <dt>Model</dt>
                            <dd><?php echo h($aiModel); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($aiDeployment)): ?>
                        <div>
                            <dt>Deployment</dt>
                            <dd><?php echo h($aiDeployment); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($aiBaseUrl)): ?>
                        <div>
                            <dt>API Adresi</dt>
                            <dd title="<?php echo h($aiBaseUrl); ?>"><?php echo h($aiBaseUrl); ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
                <?php if (current_user_role() === 'super_admin'): ?>
                    <a class="button-secondary" href="system_settings.php">Genel ayarları güncelle</a>
                <?php else: ?>
                    <p class="welcome-card__hint">Bu ayarlar süper admin tarafından düzenlenir.</p>
                <?php endif; ?>
            </article>
            <article class="welcome-card welcome-card--support">
                <h3>Destek ekibi yanınızda</h3>
                <p>Sorularınız için bize yazın, kurulum ve rapor süreçlerini birlikte planlayalım.</p>
                <a class="button-secondary" href="mailto:support@anketor.com">support@anketor.com</a>
            </article>
        </div>
    </section>

    <section class="dashboard-stats" aria-label="Kontrol paneli istatistikleri">
        <?php foreach ($statCards as $card): ?>
            <article class="stat-card">
                <div class="stat-card__icon" aria-hidden="true"><?php echo $card['icon']; ?></div>
                <div>
                    <h3 class="stat-card__label"><?php echo h($card['label']); ?></h3>
                    <p class="stat-card__value"><?php echo h($card['value']); ?></p>
                    <p class="stat-card__meta"><?php echo h($card['meta']); ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <div class="dashboard-layout">
        <section class="panel">
            <header class="panel__header">
                <h2>Son anketler</h2>
                <p>En güncel çalışmalarınızı burada görebilir ve hızlıca aksiyona geçebilirsiniz.</p>
            </header>
            <?php if ($recentSurveys): ?>
                <ul class="survey-list">
                    <?php foreach ($recentSurveys as $survey): ?>
                        <li class="survey-card">
                            <div class="survey-card__header">
                                <h3><?php echo h($survey['title']); ?></h3>
                                <span class="status-pill status-<?php echo h($survey['status']); ?>"><?php echo h($survey['status']); ?></span>
                            </div>
                            <p class="survey-card__meta">Oluşturan: <?php echo h($survey['owner_name'] ?? 'Bilinmiyor'); ?></p>
                            <dl class="survey-card__dates">
                                <div>
                                    <dt>Başlangıç</dt>
                                    <dd><?php echo $survey['start_date'] ? h(format_date($survey['start_date'])) : '-'; ?></dd>
                                </div>
                                <div>
                                    <dt>Bitiş</dt>
                                    <dd><?php echo $survey['end_date'] ? h(format_date($survey['end_date'])) : '-'; ?></dd>
                                </div>
                            </dl>
                            <div class="survey-card__actions">
                                <a class="button-secondary" href="survey_edit.php?id=<?php echo (int)$survey['id']; ?>">Ayrıntıları düzenle</a>
                                <a class="button-link" href="participants.php?id=<?php echo (int)$survey['id']; ?>">Katılımcılar</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state">
                    <h3>Henüz anket oluşturmadınız</h3>
                    <p>Yeni bir anket planlayarak katılımcılardan geri bildirim toplamaya başlayın.</p>
                    <a class="button-primary" href="survey_edit.php">İlk anketini oluştur</a>
                </div>
            <?php endif; ?>
        </section>

        <aside class="panel-group">
            <section class="panel panel--accent">
                <header class="panel__header">
                    <h2>Odak anket</h2>
                    <p>Aktif çalışmalarınızdan birini yakından takip edin.</p>
                </header>
                <?php if ($primarySurvey): ?>
                    <div class="focus-card">
                        <h3><?php echo h($primarySurvey['title']); ?></h3>
                        <dl>
                            <div>
                                <dt>Durum</dt>
                                <dd><span class="status-pill status-<?php echo h($primarySurvey['status']); ?>"><?php echo h($primarySurvey['status']); ?></span></dd>
                            </div>
                            <div>
                                <dt>Dönem</dt>
                                <dd><?php echo $primarySurvey['start_date'] ? h(format_date($primarySurvey['start_date'])) : '-'; ?> — <?php echo $primarySurvey['end_date'] ? h(format_date($primarySurvey['end_date'])) : '-'; ?></dd>
                            </div>
                        </dl>
                        <div class="focus-card__actions">
                            <a class="button-primary" href="participants.php?id=<?php echo (int)$primarySurvey['id']; ?>">Katılımcıları yönet</a>
                            <a class="button-link" href="survey_reports.php?id=<?php echo (int)$primarySurvey['id']; ?>">Raporu aç</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-hint">
                        <p>Odaklanacak bir anket seçmek için yeni bir çalışma başlatın.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel">
                <header class="panel__header">
                    <h2>Hızlı işlemler</h2>
                    <p>İş akışınızı hızlandırmak için önerilen adımlar.</p>
                </header>
                <ul class="quick-actions">
                    <?php foreach ($quickActions as $action): ?>
                        <li class="quick-actions__item<?php echo $action['url'] ? '' : ' is-disabled'; ?>">
                            <div class="quick-actions__icon" aria-hidden="true"><?php echo $action['icon']; ?></div>
                            <div class="quick-actions__body">
                                <strong><?php echo h($action['title']); ?></strong>
                                <p><?php echo h($action['description']); ?></p>
                                <?php if (!empty($action['url']) && !empty($action['label'])): ?>
                                    <a class="button-link" href="<?php echo h($action['url']); ?>"><?php echo h($action['label']); ?></a>
                                <?php endif; ?>
                                <?php if (!empty($action['hint'])): ?>
                                    <span class="quick-actions__hint"><?php echo h($action['hint']); ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="panel panel--secondary">
                <header class="panel__header">
                    <h2>Panel rehberi</h2>
                    <p>Güncel metriklere göre önerilen sonraki adımlar.</p>
                </header>
                <ul class="insight-list">
                    <?php foreach ($insights as $insight): ?>
                        <li><?php echo h($insight); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </aside>
    </div>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
