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
<main class="dashboard">
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <section class="dashboard-hero">
        <div class="dashboard-hero__inner">
            <div class="dashboard-hero__intro">
                <span class="dashboard-hero__eyebrow">Kontrol Merkezi</span>
                <h1>Hoş geldin <?php echo h(current_user_name()); ?> 👋</h1>
                <p class="dashboard-hero__lead">
                    <?php echo (int)$stats['active']; ?> aktif anket ve <?php echo (int)$stats['pending']; ?> bekleyen davetle ekibinin nabzını tut. Süreci hızlandırmak için öne çıkan aksiyonları değerlendirebilirsin.
                </p>

                <div class="dashboard-hero__stats">
                    <div class="hero-chip">
                        <span class="hero-chip__label">Yanıt Oranı</span>
                        <span class="hero-chip__value"><?php echo $totalInvites > 0 ? h($responseRate) . '%': 'N/A'; ?></span>
                        <span class="hero-chip__progress" aria-hidden="true">
                            <span style="width: <?php echo max(8, min(100, $responseRate)); ?>%"></span>
                        </span>
                    </div>
                    <div class="hero-chip">
                        <span class="hero-chip__label">Son Anket</span>
                        <span class="hero-chip__value"><?php echo $primarySurvey ? h($primarySurvey['title']) : 'Henüz oluşturulmadı'; ?></span>
                        <?php if (!empty($primarySurvey['start_date'])): ?>
                            <span class="hero-chip__meta">Başlangıç: <?php echo h(format_date($primarySurvey['start_date'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dashboard-hero__actions">
                    <a class="button-primary" href="survey_edit.php">Yeni Anket Oluştur</a>
                    <?php if (!empty($primarySurvey['id'])): ?>
                        <a class="button-secondary" href="survey_questions.php?id=<?php echo (int)$primarySurvey['id']; ?>">Soruları düzenle</a>
                    <?php endif; ?>
                    <a class="button-link" href="reports.php">Raporları incele</a>
                </div>
            </div>

            <aside class="dashboard-hero__aside">
                <div class="ai-summary">
                    <div class="ai-summary__badge">AI</div>
                    <div class="ai-summary__body">
                        <h3>Yapay Zekâ Yapılandırması</h3>
                        <dl class="ai-summary__list">
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
                            <a class="button-secondary ai-summary__action" href="system_settings.php">Genel ayarları yönet</a>
                        <?php else: ?>
                            <p class="ai-summary__hint">Genel ayarlar süper yönetici tarafından belirlenir.</p>
                        <?php endif; ?>
                    </div>
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
                <?php endif; ?>
            </aside>
        </div>
    </section>

    <section class="insight-grid">
        <article class="insight-card">
            <header>
                <span class="insight-card__label">Toplam Anket</span>
                <span class="insight-card__trend trend-neutral">Genel görünüm</span>
            </header>
            <div class="insight-card__value"><?php echo (int)$stats['surveys']; ?></div>
            <p class="insight-card__hint">Tüm zamanlarda oluşturulan anket sayısı.</p>
        </article>
        <article class="insight-card">
            <header>
                <span class="insight-card__label">Aktif Anket</span>
                <span class="insight-card__trend trend-positive">Canlı</span>
            </header>
            <div class="insight-card__value"><?php echo (int)$stats['active']; ?></div>
            <p class="insight-card__hint">Şu anda katılımcılara açık anketler.</p>
        </article>
        <article class="insight-card">
            <header>
                <span class="insight-card__label">Toplanan Cevap</span>
                <span class="insight-card__trend trend-positive">+<?php echo (int)$stats['responses']; ?></span>
            </header>
            <div class="insight-card__value"><?php echo (int)$stats['responses']; ?></div>
            <p class="insight-card__hint">Tamamlanan katılımcı yanıtları.</p>
        </article>
        <article class="insight-card">
            <header>
                <span class="insight-card__label">Bekleyen Davet</span>
                <span class="insight-card__trend trend-warning">Takip et</span>
            </header>
            <div class="insight-card__value"><?php echo (int)$stats['pending']; ?></div>
            <p class="insight-card__hint">Yanıt bekleyen davetliler.</p>
        </article>
    </section>

    <section class="dashboard-panels">
        <div class="panel recent-surveys">
            <div class="panel-header">
                <h2>Son Anketler</h2>
                <a class="button-link" href="surveys.php">Tümünü Gör</a>
            </div>
            <div class="panel-body">
                <?php if (empty($recentSurveys)): ?>
                    <div class="empty-state">
                        <h3>Henüz anket oluşturulmadı</h3>
                        <p>İlk anketini oluşturduğunda burada özetini göreceksin.</p>
                        <a class="button-primary" href="survey_edit.php">Anket oluştur</a>
                    </div>
                <?php else: ?>
                    <div class="recent-surveys__list">
                        <?php foreach ($recentSurveys as $survey): ?>
                            <article class="survey-card">
                                <div class="survey-card__header">
                                    <div>
                                        <h3><?php echo h($survey['title']); ?></h3>
                                        <span class="survey-card__category"><?php echo h($survey['category_name'] ?? 'Genel'); ?></span>
                                    </div>
                                    <span class="status-pill status-<?php echo h($survey['status']); ?>"><?php echo h($survey['status']); ?></span>
                                </div>
                                <ul class="survey-card__meta">
                                    <li>
                                        <span>Başlangıç</span>
                                        <strong><?php echo $survey['start_date'] ? h(format_date($survey['start_date'])) : '-'; ?></strong>
                                    </li>
                                    <li>
                                        <span>Bitiş</span>
                                        <strong><?php echo $survey['end_date'] ? h(format_date($survey['end_date'])) : '-'; ?></strong>
                                    </li>
                                </ul>
                                <div class="survey-card__actions">
                                    <a class="button-secondary" href="survey_questions.php?id=<?php echo (int)$survey['id']; ?>">Soruları yönet</a>
                                    <a class="button-link" href="survey_edit.php?id=<?php echo (int)$survey['id']; ?>">Detayları düzenle</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel panel--actions">
            <div class="panel-header">
                <h2>Hızlı İşlemler</h2>
            </div>
            <div class="panel-body">
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
        </div>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
