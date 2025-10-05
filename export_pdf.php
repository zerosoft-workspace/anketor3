<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$surveyId = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;
$survey = $surveyService->getSurvey($surveyId);
if (!$survey) {
    exit('Anket bulunamadi.');
}

$analytics = get_survey_analytics($db, $surveyId);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="survey-' . $surveyId . '-summary.pdf"');

function pdf_escape(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

$lines = [];
$lines[] = $survey['title'];
$lines[] = '';
$lines[] = 'Toplam cevap: ' . ($analytics['totals']['responses'] ?? 0);
$lines[] = 'Katilimci: ' . ($analytics['totals']['participants'] ?? 0);
$lines[] = 'Son yanit: ' . (!empty($analytics['totals']['last_response']) ? $analytics['totals']['last_response'] : '-');
$lines[] = '';
$lines[] = 'Degerlendirme sorulari:';
foreach ($analytics['questions'] as $question) {
    if ($question['type'] === 'rating' && $question['average']) {
        $lines[] = ' - ' . $question['text'] . ' => ' . $question['average'];
    }
}
$lines[] = '';
$lines[] = 'On plana cikan kelimeler:';
$i = 0;
foreach ($analytics['keywords'] as $keyword => $count) {
    $lines[] = ' #' . $keyword . ' (' . $count . ')';
    if (++$i >= 10) {
        break;
    }
}

$y = 800;
$contentLines = [];
foreach ($lines as $line) {
    $escaped = pdf_escape($line);
    $contentLines[] = 'BT /F1 12 Tf 50 ' . $y . ' Td (' . $escaped . ') Tj ET';
    $y -= 16;
}

$contentStream = implode("\n", $contentLines);
$length = strlen($contentStream);

$pdf = "%PDF-1.4\n";
$pdf .= "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n";
$pdf .= "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj\n";
$pdf .= "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>endobj\n";
$pdf .= "4 0 obj<< /Length $length >>stream\n" . $contentStream . "\nendstream endobj\n";
$pdf .= "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n";
$pdf .= "xref\n0 6\n0000000000 65535 f \n";
$offsets = [];
$cursor = strlen("%PDF-1.4\n");
foreach (["1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n", "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj\n", "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>endobj\n", "4 0 obj<< /Length $length >>stream\n" . $contentStream . "\nendstream endobj\n", "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n"] as $chunk) {
    $offsets[] = $cursor;
    $cursor += strlen($chunk);
}
foreach ($offsets as $offset) {
    $pdf .= str_pad((string)$offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
}
$pdf .= "trailer<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $cursor . "\n%%EOF";

echo $pdf;
