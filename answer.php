<?php
require __DIR__ . '/includes/bootstrap.php';

$surveyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = $_GET['token'] ?? null;
$preview = isset($_GET['preview']);

$survey = $surveyService->getSurveyWithQuestions($surveyId);
if (!$preview && ($survey['status'] ?? '') !== 'active') {
    include __DIR__ . '/templates/header.php';
    echo '<main class="container answer-container"><section class="answer-card"><h1>Bu anket su an cevap kabul etmiyor.</h1></section></main>';
    include __DIR__ . '/templates/footer.php';
    exit;
}
if (empty($survey)) {
    http_response_code(404);
    exit('Anket bulunamadi.');
}

$participant = null;
if ($token) {
    $participant = $surveyService->getParticipantByToken($surveyId, $token);
    if (!$participant) {
        http_response_code(403);
        exit('Bu davet gecerli degil.');
    }
    if ($participant['responded_at'] && !$preview) {
        include __DIR__ . '/templates/header.php';
        echo '<main class="container"><div class="card"><h1>Bu anketi zaten tamamladiniz.</h1></div></main>';
        include __DIR__ . '/templates/footer.php';
        exit;
    }
}

$errors = [];
$success = false;

if (!$preview && is_post()) {
    guard_csrf();
    $answers = [];
    foreach ($survey['questions'] as $question) {
        $questionId = (int)$question['id'];
        $field = 'q_' . $questionId;
        $value = $_POST[$field] ?? null;

        if ($question['is_required'] && ($value === null || $value === '')) {
            $errors[$field] = 'Bu alan zorunludur.';
            continue;
        }

        if ($question['question_type'] === 'multiple_choice') {
            $optionId = (int)$value;
            if ($question['is_required'] && $optionId <= 0) {
                $errors[$field] = 'Lutfen bir secenek secin.';
                continue;
            }
            if ($optionId > 0) {
                $answers[] = [
                    'question_id' => $questionId,
                    'option_id' => $optionId,
                ];
            }
        } elseif ($question['question_type'] === 'rating') {
            $score = (int)$value;
            if ($score < 1 || $score > 5) {
                $errors[$field] = 'Lutfen 1 ile 5 arasinda bir deger secin.';
                continue;
            }
            $answers[] = [
                'question_id' => $questionId,
                'numeric_value' => $score,
            ];
        } else {
            $text = trim((string)$value);
            if ($question['max_length'] && strlen($text) > (int)$question['max_length']) {
                $errors[$field] = 'Metin limiti asildi.';
                continue;
            }
            if ($text !== '') {
                $answers[] = [
                    'question_id' => $questionId,
                    'answer_text' => $text,
                ];
            }
        }
    }

    if (empty($errors)) {
        $responseId = $surveyService->recordResponse($surveyId, $participant['id'] ?? null, $answers);
        $_SESSION['personal_report_response'] = $responseId;

        $redirectUrl = 'personal_report.php?response=' . $responseId;
        if (!empty($participant['token'])) {
            $redirectUrl .= '&token=' . urlencode($participant['token']);
        }

        redirect($redirectUrl);
    }
}

include __DIR__ . '/templates/header.php';
?>
<main class="container answer-container">
    <section class="answer-card">
        <header>
            <h1><?php echo h($survey['title']); ?></h1>
            <?php if (!empty($survey['description'])): ?>
                <p><?php echo nl2br(h($survey['description'])); ?></p>
            <?php endif; ?>
        </header>
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                Lutfen isaretlenen alanlari kontrol edin.
            </div>
        <?php endif; ?>
        <?php if ($preview): ?>
            <div class="alert alert-info">Bu ekran yalnizca ornek amaclidir. Gonderim yapilmaz.</div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
            <?php foreach ($survey['questions'] as $index => $question): ?>
                <div class="question-block">
                    <label for="q_<?php echo (int)$question['id']; ?>">
                        <span class="question-order"><?php echo $index + 1; ?>.</span>
                        <?php echo h($question['question_text']); ?>
                        <?php if ($question['is_required']): ?><span class="required">*</span><?php endif; ?>
                    </label>
                    <?php if ($question['question_type'] === 'multiple_choice'): ?>
                        <div class="option-group">
                            <?php foreach ($question['options'] as $option): ?>
                                <label class="option">
                                    <input type="radio" name="q_<?php echo (int)$question['id']; ?>" value="<?php echo (int)$option['id']; ?>">
                                    <span><?php echo h($option['option_text']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($question['question_type'] === 'rating'): ?>
                        <div class="rating-group">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="option">
                                    <input type="radio" name="q_<?php echo (int)$question['id']; ?>" value="<?php echo $i; ?>">
                                    <span><?php echo $i; ?></span>
                                </label>
                            <?php endfor; ?>
                        </div>
                    <?php else: ?>
                        <?php $maxLength = (int)($question['max_length'] ?? 0); ?>
                        <textarea id="q_<?php echo (int)$question['id']; ?>" name="q_<?php echo (int)$question['id']; ?>" rows="4" <?php echo $maxLength ? 'maxlength="' . $maxLength . '"' : ''; ?> placeholder="Yanidinizi yazin..."></textarea>
                    <?php endif; ?>
                    <?php if (!empty($errors['q_' . $question['id']])): ?>
                        <p class="error-text"><?php echo h($errors['q_' . $question['id']]); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="button-primary button-block">Gonder</button>
        </form>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>




