<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$surveyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$survey = $surveyService->getSurvey($surveyId);
if (!$survey) {
    set_flash('danger', 'Anket bulunamadı.');
    redirect('surveys.php');
}

$pageTitle = 'Katılımcılar - ' . ($survey['title'] ?? config('app.name', 'Anketor'));

if (isset($_GET['send'])) {
    $participantId = (int)$_GET['send'];
    $participant = $db->fetch('SELECT * FROM survey_participants WHERE id = ? AND survey_id = ?', [$participantId, $surveyId]);
    if ($participant) {
        $link = base_url('answer.php?id=' . $surveyId . '&token=' . urlencode($participant['token']));
        $subject = 'Geri Bildiriminiz Bizim Icin Degerli';
        $html = '<p>Merhaba,</p><p><a href="' . $link . '">Anketimize</a> katilarak goruslerinizi paylasabilirsiniz. Cevaplariniz anonim olarak saklanacaktir.</p><p>Tesekkurler,<br>Kurumsal Iletisim Ekibi</p>';
        $text = "Merhaba,\n\nAnketimize katilarak goruslerinizi paylasabilirsiniz: $link\nCevaplariniz anonim olarak saklanacaktir.\n\nTesekkurler\nKurumsal Iletisim Ekibi";
        if (send_invitation_email($participant['email'], $subject, $html, $text)) {
            $surveyService->markInvitationSent($participantId);
            set_flash('success', 'Davet e-postasi gonderildi.');
        } else {
            set_flash('danger', 'E-posta gonderilemedi. Sunucu e-posta ayarlarinizi kontrol edin.');
        }
    }
    redirect('participants.php?id=' . $surveyId);
}

if (is_post()) {
    guard_csrf();
    $emails = explode("\n", trim($_POST['emails'] ?? ''));
    $added = 0;
    foreach ($emails as $email) {
        $participant = $surveyService->addParticipant($surveyId, $email);
        if ($participant) {
            $added++;
        }
    }
    set_flash('success', "$added katılımcı eklendi.");
    redirect('participants.php?id=' . $surveyId);
}

$participants = $surveyService->getParticipants($surveyId);
$totalParticipants = count($participants);
$completed = 0;
foreach ($participants as $participant) {
    if (!empty($participant['responded_at'])) {
        $completed++;
    }
}
$pendingCount = $totalParticipants - $completed;
$completionRate = $totalParticipants > 0 ? round(($completed / $totalParticipants) * 100) : 0;
$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <header class="page-header">
        <div>
            <p class="eyebrow">Katılımcılar</p>
            <h1><?php echo h($survey['title']); ?></h1>
            <p class="page-subtitle">Anket bağlantısını paylaşın, davetleri takip edin ve kişisel raporları tek noktadan yönetin.</p>
        </div>
        <div class="page-header__actions">
            <a class="button-secondary" href="survey_questions.php?id=<?php echo $surveyId; ?>">Sorular</a>
            <a class="button-secondary" href="survey_reports.php?id=<?php echo $surveyId; ?>">Raporlar</a>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <section class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">Toplam Davet</span>
            <span class="stat-value"><?php echo $totalParticipants; ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Tamamlanan</span>
            <span class="stat-value"><?php echo $completed; ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Bekleyen</span>
            <span class="stat-value"><?php echo $pendingCount; ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Tamamlanma Oranı</span>
            <span class="stat-value"><?php echo $totalParticipants ? $completionRate . '%': 'N/A'; ?></span>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Yeni Katılımcı Ekle</h2>
        </div>
        <div class="panel-body">
            <form method="POST">
                <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
                <div class="form-group">
                    <label for="emails">E-posta adresleri</label>
                    <textarea id="emails" name="emails" rows="4" placeholder="Her satıra bir e-posta yazın"></textarea>
                    <small class="help-text">Eklenen katılımcılar otomatik olarak benzersiz link alır. Dilerseniz bağlantıları aşağıdaki listeden kopyalayabilirsiniz.</small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button-primary">Katılımcıları Kaydet</button>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Kayıtlı Katılımcılar</h2>
        </div>
        <div class="panel-body">
            <?php if (empty($participants)): ?>
                <p>Henüz davet gönderilmedi. Listeniz burada görünecek.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>E-posta</th>
                            <th>Davet</th>
                            <th>Durum</th>
                            <th>Link</th>
                            <th>Rapor</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $participant): ?>
                            <?php $link = base_url('answer.php?id=' . $surveyId . '&token=' . urlencode($participant['token'])); ?>
                            <tr>
                                <td><?php echo h($participant['email']); ?></td>
                                <td><?php echo $participant['invited_at'] ? h(format_date($participant['invited_at'], 'd.m.Y H:i')) : '-'; ?></td>
                                <td><?php echo $participant['responded_at'] ? '<span class="status status-active">Tamamlandı</span>' : '<span class="status status-draft">Bekliyor</span>'; ?></td>
                                <td><input type="text" readonly value="<?php echo h($link); ?>" onclick="this.select();"></td>
                                <td>
                                    <?php if (!empty($participant['response_id'])): ?>
                                        <a class="button-link" href="personal_report.php?response=<?php echo (int)$participant['response_id']; ?>">Raporu Gör</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="button-link" href="participants.php?id=<?php echo $surveyId; ?>&send=<?php echo (int)$participant['id']; ?>">E-posta Gönder</a>
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



