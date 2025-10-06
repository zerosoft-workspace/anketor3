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
        'hint' => current_user_role() === 'super_admin' ? null : 'Bu ayarı sadece süper admin güncelleyebilir.',
    ],
    [
        'icon' => '🗂️',
        'title' => 'Soru bankasını tazeleyin',
        'description' => 'Sık kullanılan soruları düzenleyip taslaklarınıza ekleyin.',
        'url' => 'survey_questions.php',
        'label' => 'Soru havuzu',
        'hint' => null,
    ],
    [
        'icon' => '📨',
        'title' => 'Hatırlatma gönderin',
        'description' => 'Katılım oranını artırmak için yanıt vermeyenlere toplu e-posta gönderin.',
        'url' => $primarySurvey ? 'participants.php?id=' . (int)$primarySurvey['id'] : null,
        'label' => $primarySurvey ? 'Katılımcıları yönet' : null,
        'hint' => $primarySurvey ? null : 'Odaklanacak bir anket seçildiğinde etkinleşir.',
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
        <span class="dashboard-hero__halo" aria-hidden="true"></span>
        <span class="dashboard-hero__halo dashboard-hero__halo--two" aria-hidden="true"></span>

        <div class="dashboard-hero__grid">
            <div class="dashboard-hero__primary">
                <span class="dashboard-hero__eyebrow">Kontrol Merkezi</span>
                <h1 class="dashboard-hero__title">Merhaba <?php echo h(current_user_name()); ?> 👋</h1>
                <p class="dashboard-hero__lead">
                    <?php echo (int)$stats['active']; ?> aktif anket, <?php echo (int)$stats['responses']; ?> yanıt ve <?php echo (int)$stats['pending']; ?> bekleyen davet ile <strong><?php echo h(config('app.name', 'Anketor')); ?></strong> topluluğunun nabzını tutuyoruz.
                </p>

                <ul class="dashboard-hero__metrics">
                    <li class="metric-card metric-card--accent">
                        <div class="metric-card__top">
                            <span class="metric-card__label">Yanıt oranı</span>
                            <span class="metric-card__value"><?php echo $totalInvites > 0 ? h($responseRate) . '%' : 'N/A'; ?></span>
                        </div>
                        <div class="metric-card__progress" aria-hidden="true">
                            <span style="width: <?php echo max(8, min(100, $responseRate)); ?>%"></span>
                        </div>
                        <span class="metric-card__meta"><?php echo $totalInvites > 0 ? h($totalInvites) . ' toplam davet' : 'Henüz davet yok'; ?></span>
                    </li>
                    <li class="metric-card">
                        <span class="metric-card__label">Aktif anket</span>
                        <span class="metric-card__value"><?php echo (int)$stats['active']; ?></span>
                        <span class="metric-card__meta"><?php echo (int)$stats['surveys']; ?> toplam anket içerisinde</span>
                    </li>
                    <li class="metric-card">
                        <span class="metric-card__label">Toplanan yanıt</span>
                        <span class="metric-card__value"><?php echo (int)$stats['responses']; ?></span>
                        <span class="metric-card__meta">Raporlarda değerlendirildi</span>
                    </li>
                    <li class="metric-card">
                        <span class="metric-card__label">Bekleyen davet</span>
                        <span class="metric-card__value"><?php echo (int)$stats['pending']; ?></span>
                        <span class="metric-card__meta">Takip edilmeyi bekliyor</span>
                    </li>
                </ul>

                <div class="dashboard-hero__actions">
                    <a class="button-primary" href="survey_edit.php">Yeni Anket Oluştur</a>
                    <?php if (!empty($primarySurvey['id'])): ?>
                        <a class="button-secondary" href="survey_questions.php?id=<?php echo (int)$primarySurvey['id']; ?>">Soruları düzenle</a>
                    <?php endif; ?>
                    <a class="button-link" href="reports.php">Raporları incele</a>
                </div>
            </div>

            <aside class="dashboard-hero__secondary">
                <div class="dashboard-card dashboard-card--ai">
                    <span class="dashboard-card__eyebrow">Yapay zekâ</span>
                    <h2 class="dashboard-card__title">Yapılandırma Özeti</h2>
                    <dl class="dashboard-card__list">
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
                        <a class="button-secondary" href="system_settings.php">Genel ayarları yönet</a>
                    <?php else: ?>
                        <p class="dashboard-card__hint">Genel ayarlar süper yönetici tarafından yönetilir.</p>
                    <?php endif; ?>
                </div>

                <div class="dashboard-card dashboard-card--spotlight">
                    <?php if ($primarySurvey): ?>
                        <span class="dashboard-card__eyebrow">Odak anket</span>
                        <h2 class="dashboard-card__title"><?php echo h($primarySurvey['title']); ?></h2>
                        <ul class="spotlight-list">
                            <li>
                                <span>Durum</span>
                                <strong class="status-pill status-<?php echo h($primarySurvey['status']); ?>"><?php echo h($primarySurvey['status']); ?></strong>
                            </li>
                            <li>
                                <span>Dönem</span>
                                <strong><?php echo $primarySurvey['start_date'] ? h(format_date($primarySurvey['start_date'])) : '-'; ?> — <?php echo $primarySurvey['end_date'] ? h(format_date($primarySurvey['end_date'])) : '-'; ?></strong>
                            </li>
                        </ul>
                        <div class="dashboard-card__actions">
                            <a class="button-secondary" href="participants.php?id=<?php echo (int)$primarySurvey['id']; ?>">Katılımcıları yönet</a>
                            <a class="button-link" href="survey_edit.php?id=<?php echo (int)$primarySurvey['id']; ?>">Detayları düzenle</a>
                        </div>
                    <?php else: ?>
                        <span class="dashboard-card__eyebrow">Başlangıç</span>
                        <h2 class="dashboard-card__title">İlk anketini başlat</h2>
                        <p>Katılımcı deneyimini izlemek için yeni bir anket oluştur, davetleri planla ve raporları takip et.</p>
                        <div class="dashboard-card__actions">
                            <a class="button-secondary" href="survey_edit.php">Anket oluştur</a>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </section>

    <section class="dashboard-overview">
        <header class="dashboard-section__header">
            <h2>İhtiyaçlarınıza odaklanan araçlar</h2>
            <p>Anketlerin yaşam döngüsünü uçtan uca yönetirken modern bir deneyim sunuyoruz.</p>
        </header>

        <div class="overview-grid">
            <article class="overview-card">
                <span class="overview-card__icon">🎯</span>
                <h3>Akıllı planlama</h3>
                <p>Kitle segmentlerini seçip davetleri zamanlayarak etkileşimi doğru anda yakalayın.</p>
            </article>
            <article class="overview-card">
                <span class="overview-card__icon">📊</span>
                <h3>Derin raporlar</h3>
                <p>Yapay zekâ destekli özetlerle yanıtları okuyup karar süreçlerini hızlandırın.</p>
            </article>
            <article class="overview-card">
                <span class="overview-card__icon">🤝</span>
                <h3>Takım uyumu</h3>
                <p>Rollere göre kısıtlanan erişimle ekibini aynı anda güvenle çalıştırın.</p>
            </article>
        </div>
    </section>

    <section class="dashboard-main">
        <div class="module-card module-card--surveys">
            <header class="module-card__header">
                <div>
                    <h2>Son anketler</h2>
                    <p class="module-card__description">Ekibinin güncel çalışmalarını takip ederek hızlıca aksiyon al.</p>
                </div>
                <a class="button-link" href="surveys.php">Tümünü gör</a>
            </header>

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
                            <header class="survey-item__header">
                                <div>
                                    <h3><?php echo h($survey['title']); ?></h3>
                                    <span class="survey-item__meta"><?php echo h($survey['category_name'] ?? 'Genel'); ?></span>
                                </div>
                                <span class="status-pill status-<?php echo h($survey['status']); ?>"><?php echo h($survey['status']); ?></span>
                            </header>
                            <dl class="survey-item__dates">
                                <div>
                                    <dt>Başlangıç</dt>
                                    <dd><?php echo $survey['start_date'] ? h(format_date($survey['start_date'])) : '-'; ?></dd>
                                </div>
                                <div>
                                    <dt>Bitiş</dt>
                                    <dd><?php echo $survey['end_date'] ? h(format_date($survey['end_date'])) : '-'; ?></dd>
                                </div>
                            </dl>
                            <div class="survey-item__actions">
                                <a class="button-secondary" href="survey_questions.php?id=<?php echo (int)$survey['id']; ?>">Soruları yönet</a>
                                <a class="button-link" href="survey_edit.php?id=<?php echo (int)$survey['id']; ?>">Detayları düzenle</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <aside class="dashboard-sidebar">
            <div class="module-card module-card--actions">
                <header class="module-card__header">
                    <div>
                        <h2>Hızlı işlemler</h2>
                        <p class="module-card__description">Planınıza hız kazandırın ve sık yapılan işleri dakikalara indirin.</p>
                    </div>
                </header>
                <ul class="action-list">
                    <?php foreach ($quickActions as $action): ?>
                        <li class="action-list__item<?php echo empty($action['url']) ? ' is-disabled' : ''; ?>">
                            <span class="action-list__icon"><?php echo h($action['icon']); ?></span>
                            <div class="action-list__body">
                                <strong><?php echo h($action['title']); ?></strong>
                                <p><?php echo h($action['description']); ?></p>
                                <?php if (!empty($action['url']) && !empty($action['label'])): ?>
                                    <a class="button-link" href="<?php echo h($action['url']); ?>"><?php echo h($action['label']); ?></a>
                                <?php elseif (!empty($action['hint'])): ?>
                                    <span class="action-list__hint"><?php echo h($action['hint']); ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="module-card support-card">
                <h2>Destek ekibimiz yanında</h2>
                <p>Soruların için dilediğin zaman <a href="mailto:support@anketor.com">support@anketor.com</a> adresine yazabilirsin.</p>
                <a class="button-secondary" href="mailto:support@anketor.com">support@anketor.com</a>
                <p class="support-card__meta">Yardım merkezindeki rehberlerle yeni özellikleri keşfetmeyi unutma.</p>
            </div>
        </aside>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
