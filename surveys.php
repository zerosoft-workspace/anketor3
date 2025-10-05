<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$surveys = $surveyService->getSurveys(200);
$flash = get_flash();
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>
<main class="container">
    <div class="panel-header">
        <h1>Anketler</h1>
        <a class="button-primary" href="survey_edit.php">Yeni Anket</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

    <div class="panel-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Baslik</th>
                    <th>Durum</th>
                    <th>Donem</th>
                    <th>Olusturan</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($surveys as $survey): ?>
                    <tr>
                        <td><?php echo h($survey['title']); ?></td>
                        <td><span class="status status-<?php echo h($survey['status']); ?>"><?php echo h($survey['status']); ?></span></td>
                        <td>
                            <?php echo $survey['start_date'] ? h(format_date($survey['start_date'])) : '-'; ?>
                            <?php if ($survey['end_date']): ?>
                                &ndash; <?php echo h(format_date($survey['end_date'])); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo h($survey['created_by']); ?></td>
                        <td class="table-actions">
                            <a class="button-link" href="survey_edit.php?id=<?php echo (int)$survey['id']; ?>">Duzenle</a>
                            <a class="button-link" href="survey_questions.php?id=<?php echo (int)$survey['id']; ?>">Sorular</a>
                            <a class="button-link" href="participants.php?id=<?php echo (int)$survey['id']; ?>">Katilimcilar</a>
                            <a class="button-link" href="survey_reports.php?id=<?php echo (int)$survey['id']; ?>">Rapor</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>

