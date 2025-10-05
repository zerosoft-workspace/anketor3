<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$surveyId = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;
$survey = $surveyService->getSurvey($surveyId);
if (!$survey) {
    exit('Anket bulunamadi.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="survey-' . $surveyId . '-responses.csv"');

$questions = $surveyService->getQuestions($surveyId);
$columns = ['Response ID', 'Submitted At'];
foreach ($questions as $question) {
    $columns[] = $question['question_text'];
}

echo implode(',', array_map(function ($col) {
    return '"' . str_replace('"', '""', $col) . '"';
}, $columns)) . "\n";

$responses = $db->fetchAll(
    'SELECT sr.id, sr.submitted_at FROM survey_responses sr WHERE sr.survey_id = ? ORDER BY sr.submitted_at ASC',
    [$surveyId]
);

if (!$responses) {
    exit;
}

$answers = $db->fetchAll(
    'SELECT ra.response_id, ra.question_id, ra.answer_text, ra.numeric_value, qo.option_text
     FROM response_answers ra
     LEFT JOIN question_options qo ON qo.id = ra.option_id
     WHERE ra.response_id IN (' . implode(',', array_column($responses, 'id')) . ')'
);

$grouped = [];
foreach ($answers as $answer) {
    $grouped[$answer['response_id']][$answer['question_id']] = $answer;
}

foreach ($responses as $response) {
    $row = [$response['id'], $response['submitted_at']];
    foreach ($questions as $question) {
        $value = '';
        if (isset($grouped[$response['id']][$question['id']])) {
            $answer = $grouped[$response['id']][$question['id']];
            if ($question['question_type'] === 'multiple_choice') {
                $value = $answer['option_text'] ?? '';
            } elseif ($question['question_type'] === 'rating') {
                $value = $answer['numeric_value'];
            } else {
                $value = $answer['answer_text'];
            }
        }
        $row[] = $value;
    }

    echo implode(',', array_map(function ($col) {
        return '"' . str_replace('"', '""', (string)$col) . '"';
    }, $row)) . "\n";
}
