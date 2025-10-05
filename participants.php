<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$surveyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$survey = $surveyService->getSurvey($surveyId);
if (!$survey) {
    set_flash('danger', 'Anket bulunamadi.');
    redirect('surveys.php');
}

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
    set_flash('success', "$added katilimci eklendi.");
    redirect('participants.php?id=' . $surveyId);
}

$participants = $surveyService->getParticipants($surveyId);
$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <div class="panel-header">
        <h1>Katilimcilar &raquo; <?php echo h($survey['title']); ?></h1>
        <a class="button-secondary" href="survey_questions.php?id=<?php echo $surveyId; ?>">Sorular</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-header">
            <h2>Katilimci Ekle</h2>
        </div>
        <div class="panel-body">
            <form method="POST">
                <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
                <div class="form-group">
                    <label for="emails">E-posta adresleri</label>
                    <textarea id="emails" name="emails" rows="4" placeholder="her satira bir e-posta"></textarea>
                </div>
                <button type="submit" class="button-primary">Katilimcilari Kaydet</button>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Liste</h2>
        </div>
        <div class="panel-body">
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
                            <td><?php echo $participant['responded_at'] ? '<span class="status status-active">Tamamlandi</span>' : '<span class="status status-draft">Bekliyor</span>'; ?></td>
                            <td><input type="text" readonly value="<?php echo h($link); ?>" onclick="this.select();"></td>
                            <td>
                                <?php if ($participant['responded_at']): ?>
                                    <a class="button-link" href="personal_report.php?participant=<?php echo (int)$participant['id']; ?>">Raporu Gör</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="button-link" href="participants.php?id=<?php echo $surveyId; ?>&send=<?php echo (int)$participant['id']; ?>">E-posta Gonder</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>



