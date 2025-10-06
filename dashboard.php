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

    <section class="welcome-hero">
        <span class="welcome-hero__glow" aria-hidden="true"></span>
        <span class="welcome-hero__orb welcome-hero__orb--one" aria-hidden="true"></span>
        <span class="welcome-hero__orb welcome-hero__orb--two" aria-hidden="true"></span>

        <div class="welcome-hero__inner">
            <div class="welcome-hero__copy">
                <span class="welcome-hero__badge">Kontrol Merkezi</span>
                <h1>Hoş geldin <?php echo h(current_user_name()); ?> 👋</h1>
                <p class="welcome-hero__lead">
                    <?php echo (int)$stats['active']; ?> aktif anket, <?php echo (int)$stats['responses']; ?> yanıt ve <?php echo (int)$stats['pending']; ?> bekleyen davet ile <strong><?php echo h(config('app.name', 'Anketor')); ?></strong> topluluğunun nabzını tutuyoruz.
                </p>

                <div class="welcome-hero__stats">
                    <article class="stat-bubble stat-bubble--accent">
                        <span class="stat-bubble__label">Yanıt oranı</span>
                        <span class="stat-bubble__value"><?php echo $totalInvites > 0 ? h($responseRate) . '%' : 'N/A'; ?></span>
                        <span class="stat-bubble__meta"><?php echo $totalInvites > 0 ? h($totalInvites) . ' toplam davet' : 'Henüz davet yok'; ?></span>
                        <span class="stat-bubble__progress" aria-hidden="true">
                            <span style="width: <?php echo max(8, min(100, $responseRate)); ?>%"></span>
                        </span>
                    </article>
                    <article class="stat-bubble">
                        <span class="stat-bubble__label">Aktif anket</span>
                        <span class="stat-bubble__value"><?php echo (int)$stats['active']; ?></span>
                        <span class="stat-bubble__meta"><?php echo (int)$stats['surveys']; ?> toplam anket içerisinde.</span>
                    </article>
                    <article class="stat-bubble">
                        <span class="stat-bubble__label">Toplanan yanıt</span>
                        <span class="stat-bubble__value"><?php echo (int)$stats['responses']; ?></span>
                        <span class="stat-bubble__meta">Son dönem raporlarında kullanıldı.</span>
                    </article>
                    <article class="stat-bubble">
                        <span class="stat-bubble__label">Bekleyen davet</span>
                        <span class="stat-bubble__value"><?php echo (int)$stats['pending']; ?></span>
                        <span class="stat-bubble__meta">Takip edilmeyi bekliyor.</span>
                    </article>
                </div>

                <div class="welcome-hero__actions">
                    <a class="button-primary" href="survey_edit.php">Yeni Anket Oluştur</a>
                    <?php if (!empty($primarySurvey['id'])): ?>
                        <a class="button-secondary" href="survey_questions.php?id=<?php echo (int)$primarySurvey['id']; ?>">Soruları düzenle</a>
                    <?php endif; ?>
                    <a class="button-link" href="reports.php">Raporları incele</a>
                </div>
            </div>

            <aside class="welcome-hero__panels">
                <div class="welcome-panel welcome-panel--ai">
                    <span class="welcome-panel__badge">Yapay zekâ</span>
                    <h2>Yapılandırma Özeti</h2>
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
                        <a class="button-secondary welcome-panel__cta" href="system_settings.php">Genel ayarları yönet</a>
                    <?php else: ?>
                        <p class="welcome-panel__hint">Genel ayarlar süper yönetici tarafından yönetilir.</p>
                    <?php endif; ?>
                </div>

                <div class="welcome-panel welcome-panel--spotlight">
                    <?php if ($primarySurvey): ?>
                        <span class="welcome-panel__badge">Odak anket</span>
                        <h2><?php echo h($primarySurvey['title']); ?></h2>
                        <ul class="spotlight-meta">
                            <li>
                                <span>Durum</span>
                                <strong class="status-pill status-<?php echo h($primarySurvey['status']); ?>"><?php echo h($primarySurvey['status']); ?></strong>
                            </li>
                            <li>
                                <span>Dönem</span>
                                <strong><?php echo $primarySurvey['start_date'] ? h(format_date($primarySurvey['start_date'])) : '-'; ?> — <?php echo $primarySurvey['end_date'] ? h(format_date($primarySurvey['end_date'])) : '-'; ?></strong>
                            </li>
                        </ul>
                        <div class="welcome-panel__actions">
                            <a class="button-secondary" href="participants.php?id=<?php echo (int)$primarySurvey['id']; ?>">Katılımcıları yönet</a>
                            <a class="button-link" href="survey_edit.php?id=<?php echo (int)$primarySurvey['id']; ?>">Detayları düzenle</a>
                        </div>
                    <?php else: ?>
                        <span class="welcome-panel__badge">Başlangıç</span>
                        <h2>İlk anketini başlat</h2>
                        <p>Katılımcı deneyimini izlemek için yeni bir anket oluştur, davetleri planla ve raporları takip et.</p>
                        <div class="welcome-panel__actions">
                            <a class="button-secondary" href="survey_edit.php">Anket oluştur</a>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </section>

    <section class="dashboard-panels">
        <div class="panels-grid">
            <div class="panel panel--surveys">
                <header class="panel__header">
                    <div>
                        <h2>Son anketler</h2>
                        <p class="panel__description">Ekibinin güncel çalışmalarını takip ederek hızlıca aksiyon al.</p>
                    </div>
                    <a class="button-link" href="surveys.php">Tümünü gör</a>
                </header>

                <?php if (empty($recentSurveys)): ?>
                    <div class="empty-block">
                        <h3>Henüz anket oluşturulmadı</h3>
                        <p>İlk anketini oluşturduğunda burada özetini göreceksin.</p>
                        <a class="button-primary" href="survey_edit.php">Anket oluştur</a>
                    </div>
                <?php else: ?>
                    <div class="survey-cards">
                        <?php foreach ($recentSurveys as $survey): ?>
                            <article class="survey-card">
                                <div class="survey-card__top">
                                    <div>
                                        <h3 class="survey-card__title"><?php echo h($survey['title']); ?></h3>
                                        <span class="survey-card__category"><?php echo h($survey['category_name'] ?? 'Genel'); ?></span>
                                    </div>
                                    <span class="status-pill status-<?php echo h($survey['status']); ?>"><?php echo h($survey['status']); ?></span>
                                </div>
                                <dl class="survey-card__meta">
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
                                    <a class="button-secondary" href="survey_questions.php?id=<?php echo (int)$survey['id']; ?>">Soruları yönet</a>
                                    <a class="button-link" href="survey_edit.php?id=<?php echo (int)$survey['id']; ?>">Detayları düzenle</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="panel panel--sidebar">
                <div class="quick-actions">
                    <h2>Hızlı işlemler</h2>
                    <ul>
                        <?php foreach ($quickActions as $action): ?>
                            <li>
                                <span class="quick-actions__icon"><?php echo h($action['icon']); ?></span>
                                <div>
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

                <div class="support-callout">
                    <strong>Desteğe mi ihtiyacın var?</strong>
                    <p>Sürece dair sorularında ekibimize <a href="mailto:support@anketor.com">support@anketor.com</a> adresinden ulaşabilirsin.</p>
                    <p>Yardım merkezindeki rehberlerle yeni özellikleri de keşfedebilirsin.</p>
                </div>
            </aside>
        </div>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
