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
        'icon' => '🧠',
        'title' => 'AI önerilerini güncelleyin',
        'description' => 'Sistem ayarlarından sağlayıcıyı güncelleyerek raporlardaki içgörüleri uyarlayın.',
        'url' => current_user_role() === 'super_admin' ? 'system_settings.php' : null,
        'label' => 'Ayarları aç',
    ],
    [
        'icon' => '🗂️',
        'title' => 'Soru bankasını tazeleyin',
        'description' => 'Sık kullanılan soruları düzenleyip taslaklarınıza ekleyin.',
        'url' => 'survey_questions.php',
        'label' => 'Soru havuzu',
    ],
    [
        'icon' => '📨',
        'title' => 'Hatırlatma gönderin',
        'description' => 'Katılım oranını artırmak için yanıt vermeyenlere toplu e-posta gönderin.',
        'url' => $primarySurvey ? 'participants.php?id=' . (int)$primarySurvey['id'] : null,
        'label' => $primarySurvey ? 'Katılımcıları yönet' : null,
    ],
];

$pageTitle = 'Gösterge Paneli - ' . config('app.name', 'Anketor');
$flash = get_flash();

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="dashboard-shell">
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <section class="dashboard-hero">
        <div class="dashboard-hero__grid">
            <div class="dashboard-hero__content">
                <span class="dashboard-hero__badge">Kontrol Merkezi</span>
                <h1>Hoş geldin <?php echo h(current_user_name()); ?> 👋</h1>
                <p class="dashboard-hero__lead">
                    <?php echo (int)$stats['active']; ?> aktif anket ve <?php echo (int)$stats['pending']; ?> bekleyen davetle <strong><?php echo h(config('app.name', 'Anketor')); ?></strong> topluluk nabzını tutuyor. Hızlı aksiyonlar için öne çıkan kartları değerlendirebilirsin.
                </p>

                <div class="dashboard-hero__highlights">
                    <article class="highlight-card">
                        <span class="highlight-card__label">Yanıt oranı</span>
                        <span class="highlight-card__value"><?php echo $totalInvites > 0 ? h($responseRate) . '%' : 'N/A'; ?></span>
                        <span class="highlight-card__meta"><?php echo $totalInvites > 0 ? h($totalInvites) . ' toplam davet' : 'Henüz davet gönderilmedi'; ?></span>
                        <span class="highlight-card__progress" aria-hidden="true">
                            <span style="width: <?php echo max(8, min(100, $responseRate)); ?>%"></span>
                        </span>
                    </article>
                    <article class="highlight-card">
                        <span class="highlight-card__label">Aktif anket</span>
                        <span class="highlight-card__value"><?php echo (int)$stats['active']; ?></span>
                        <span class="highlight-card__meta"><?php echo (int)$stats['surveys']; ?> toplam anket içinde yayında.</span>
                    </article>
                    <article class="highlight-card">
                        <span class="highlight-card__label">Bekleyen davet</span>
                        <span class="highlight-card__value"><?php echo (int)$stats['pending']; ?></span>
                        <span class="highlight-card__meta"><?php echo (int)$stats['responses']; ?> yanıt tamamlandı.</span>
                    </article>
                </div>

                <div class="dashboard-hero__actions">
                    <a class="button-primary" href="survey_edit.php">Yeni Anket Oluştur</a>
                    <?php if (!empty($primarySurvey['id'])): ?>
                        <a class="button-secondary" href="survey_questions.php?id=<?php echo (int)$primarySurvey['id']; ?>">Soruları düzenle</a>
                    <?php endif; ?>
                    <a class="button-link" href="reports.php">Raporları incele</a>
                </div>
            </div>

            <div class="dashboard-hero__panel">
                <div class="glass-card">
                    <span class="glass-card__badge">Yapay zekâ</span>
                    <h3>Yapılandırma Özeti</h3>
                    <dl class="ai-config__list">
                        <div>
                            <dt>Sağlayıcı</dt>
                            <dd><?php echo h($aiProviderLabel); ?></dd>
                        </div>
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
                        <a class="button-secondary ai-config__action" href="system_settings.php">Genel ayarları yönet</a>
                    <?php else: ?>
                        <p class="ai-config__hint">Genel ayarlar süper yönetici tarafından yönetilir.</p>
                    <?php endif; ?>
                </div>

                <?php if ($primarySurvey): ?>
                    <div class="spotlight-card">
                        <span class="spotlight-card__label">Odak Anket</span>
                        <h3><?php echo h($primarySurvey['title']); ?></h3>
                        <ul class="spotlight-card__meta">
                            <li><strong>Durum:</strong> <span class="status-pill status-<?php echo h($primarySurvey['status']); ?>"><?php echo h($primarySurvey['status']); ?></span></li>
                            <li><strong>Dönem:</strong> <?php echo $primarySurvey['start_date'] ? h(format_date($primarySurvey['start_date'])) : '-'; ?> — <?php echo $primarySurvey['end_date'] ? h(format_date($primarySurvey['end_date'])) : '-'; ?></li>
                        </ul>
                        <div class="spotlight-card__actions">
                            <a class="button-secondary" href="participants.php?id=<?php echo (int)$primarySurvey['id']; ?>">Katılımcıları yönet</a>
                            <a class="button-link" href="survey_edit.php?id=<?php echo (int)$primarySurvey['id']; ?>">Detayları düzenle</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="spotlight-card">
                        <span class="spotlight-card__label">Odak alanı</span>
                        <h3>Henüz odaklanılmış bir anket yok</h3>
                        <p>Yeni bir anket oluşturarak raporlar ve hızlı aksiyonlar için temel veriyi oluşturabilirsin.</p>
                        <div class="spotlight-card__actions">
                            <a class="button-secondary" href="survey_edit.php">İlk anketini başlat</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="dashboard-overview">
        <div class="overview-grid">
            <article class="overview-card">
                <span class="overview-card__icon">📋</span>
                <span class="overview-card__label">Toplam anket</span>
                <p class="overview-card__value"><?php echo (int)$stats['surveys']; ?></p>
                <p class="overview-card__hint">Tüm ekipler tarafından bugüne kadar oluşturulan projeler.</p>
            </article>
            <article class="overview-card overview-card--success">
                <span class="overview-card__icon">🚀</span>
                <span class="overview-card__label">Aktif anket</span>
                <p class="overview-card__value"><?php echo (int)$stats['active']; ?></p>
                <p class="overview-card__hint">Katılımcıların şu anda erişebildiği çalışmaları temsil eder.</p>
            </article>
            <article class="overview-card overview-card--success">
                <span class="overview-card__icon">✅</span>
                <span class="overview-card__label">Toplanan cevap</span>
                <p class="overview-card__value"><?php echo (int)$stats['responses']; ?></p>
                <p class="overview-card__hint">İçgörüleri güçlendiren tamamlanmış geri bildirimler.</p>
            </article>
            <article class="overview-card overview-card--warning">
                <span class="overview-card__icon">⏳</span>
                <span class="overview-card__label">Bekleyen davet</span>
                <p class="overview-card__value"><?php echo (int)$stats['pending']; ?></p>
                <p class="overview-card__hint">Takip edilmesi gereken ve dönüş bekleyen davetliler.</p>
            </article>
        </div>
    </section>

    <section class="dashboard-layout">
        <div class="surface-card">
            <div class="surface-card__header">
                <div>
                    <h2>Son anketler</h2>
                    <p class="surface-card__description">Ekibinin en güncel çalışmalarını buradan takip et.</p>
                </div>
                <a class="button-link" href="surveys.php">Tümünü gör</a>
            </div>

            <?php if (empty($recentSurveys)): ?>
                <div class="empty-state">
                    <h3>Henüz anket oluşturulmadı</h3>
                    <p>İlk anketini oluşturduğunda burada özetini göreceksin.</p>
                    <a class="button-primary" href="survey_edit.php">Anket oluştur</a>
                </div>
            <?php else: ?>
                <div class="survey-list">
                    <?php foreach ($recentSurveys as $survey): ?>
                        <article class="survey-item">
                            <div class="survey-item__header">
                                <div>
                                    <h3 class="survey-item__title"><?php echo h($survey['title']); ?></h3>
                                    <span class="survey-item__category"><?php echo h($survey['category_name'] ?? 'Genel'); ?></span>
                                </div>
                                <span class="status-pill status-<?php echo h($survey['status']); ?>"><?php echo h($survey['status']); ?></span>
                            </div>
                            <ul class="survey-item__meta">
                                <li>
                                    <span>Başlangıç</span>
                                    <strong><?php echo $survey['start_date'] ? h(format_date($survey['start_date'])) : '-'; ?></strong>
                                </li>
                                <li>
                                    <span>Bitiş</span>
                                    <strong><?php echo $survey['end_date'] ? h(format_date($survey['end_date'])) : '-'; ?></strong>
                                </li>
                            </ul>
                            <div class="survey-item__actions">
                                <a class="button-secondary" href="survey_questions.php?id=<?php echo (int)$survey['id']; ?>">Soruları yönet</a>
                                <a class="button-link" href="survey_edit.php?id=<?php echo (int)$survey['id']; ?>">Detayları düzenle</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <aside class="dashboard-layout__aside">
            <div class="surface-card">
                <div class="surface-card__header">
                    <h2>Hızlı işlemler</h2>
                </div>
                <ul class="quick-actions-list">
                    <?php foreach ($quickActions as $action): ?>
                        <li class="quick-actions-list__item">
                            <span class="quick-actions-list__icon"><?php echo h($action['icon']); ?></span>
                            <div class="quick-actions-list__content">
                                <strong><?php echo h($action['title']); ?></strong>
                                <p><?php echo h($action['description']); ?></p>
                                <?php if (!empty($action['url']) && !empty($action['label'])): ?>
                                    <a class="button-link" href="<?php echo h($action['url']); ?>"><?php echo h($action['label']); ?></a>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="support-card">
                <strong>Desteğe mi ihtiyacın var?</strong>
                <p>Süreçleri yapılandırırken takıldığında ekibimize <a href="mailto:support@anketor.com">support@anketor.com</a> adresinden ulaşabilirsin.</p>
                <p>Ayrıca yardım merkezindeki hızlı başlangıç rehberleriyle yeni özellikleri keşfet.</p>
            </div>
        </aside>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
