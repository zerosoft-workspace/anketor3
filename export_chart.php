<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$surveyId = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;
$survey = $surveyService->getSurvey($surveyId);
if (!$survey) {
    exit('Anket bulunamadi.');
}

$analytics = get_survey_analytics($db, $surveyId);
$ratingQuestions = array_filter($analytics['questions'], function ($item) {
    return ($item['type'] ?? '') === 'rating' && !empty($item['average']);
});

if (!$ratingQuestions) {
    exit('Grafik icin rating sorusu bulunmuyor.');
}

$width = 800;
$barHeight = 30;
$gap = 20;
$margin = 60;
$height = $margin + count($ratingQuestions) * ($barHeight + $gap);

$svg = [];
$svg[] = '<?xml version="1.0" encoding="UTF-8"?>';
$svg[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">';
$svg[] = '<rect width="100%" height="100%" fill="#f5f6fb" />';
$svg[] = '<text x="40" y="40" font-family="Segoe UI" font-size="20" fill="#1f2933">' . htmlspecialchars($survey['title'], ENT_QUOTES) . '</text>';

$index = 0;
foreach ($ratingQuestions as $question) {
    $y = $margin + $index * ($barHeight + $gap);
    $value = min(5, max(0, (float)$question['average']));
    $barWidth = ($width - 2 * $margin) * ($value / 5);
    $svg[] = '<rect x="' . $margin . '" y="' . $y . '" width="' . $barWidth . '" height="' . $barHeight . '" rx="6" fill="#3c6df0" opacity="0.85" />';
    $svg[] = '<text x="' . ($margin + $barWidth + 10) . '" y="' . ($y + $barHeight - 8) . '" font-family="Segoe UI" font-size="14" fill="#1f2933">' . number_format($value, 2) . '</text>';
    $svg[] = '<text x="' . $margin . '" y="' . ($y - 8) . '" font-family="Segoe UI" font-size="13" fill="#6b7280">' . htmlspecialchars($question['text'], ENT_QUOTES) . '</text>';
    $index++;
}

$svg[] = '</svg>';

header('Content-Type: image/svg+xml');
echo implode('', $svg);
