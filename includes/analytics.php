<?php
function get_survey_analytics(Database $db, int $surveyId): array
{
    $summary = [
        'totals' => [],
        'questions' => [],
        'keywords' => [],
    ];

    $summary['totals'] = $db->fetch(
        'SELECT COUNT(DISTINCT sr.id) AS responses,
                COUNT(DISTINCT sp.id) AS participants,
                MAX(sr.submitted_at) AS last_response
         FROM survey_responses sr
         LEFT JOIN survey_participants sp ON sp.id = sr.participant_id
         WHERE sr.survey_id = ?',
        [$surveyId]
    ) ?: ['responses' => 0, 'participants' => 0, 'last_response' => null];

    $questions = $db->fetchAll(
        'SELECT id, question_text, question_type, is_required
         FROM survey_questions
         WHERE survey_id = ?
         ORDER BY order_index ASC, id ASC',
        [$surveyId]
    );

    foreach ($questions as $question) {
        $questionId = (int)$question['id'];
        $questionData = [
            'text' => $question['question_text'],
            'type' => $question['question_type'],
            'is_required' => (bool)$question['is_required'],
            'answers' => [],
            'average' => null,
            'distribution' => [],
        ];

        if ($question['question_type'] === 'rating') {
            $row = $db->fetch(
                'SELECT AVG(numeric_value) AS avg_score, COUNT(*) AS answer_count
                 FROM response_answers
                 WHERE question_id = ? AND numeric_value IS NOT NULL',
                [$questionId]
            );
            $questionData['average'] = $row['avg_score'] ? round((float)$row['avg_score'], 2) : null;
            $questionData['answers'] = (int)($row['answer_count'] ?? 0);
        } elseif ($question['question_type'] === 'multiple_choice') {
            $options = $db->fetchAll(
                'SELECT qo.id, qo.option_text,
                        COUNT(ra.id) AS votes
                 FROM question_options qo
                 LEFT JOIN response_answers ra ON ra.option_id = qo.id
                 WHERE qo.question_id = ?
                 GROUP BY qo.id, qo.option_text
                 ORDER BY qo.order_index ASC, qo.id ASC',
                [$questionId]
            );
            $totalVotes = array_sum(array_column($options, 'votes')) ?: 1;
            foreach ($options as $option) {
                $questionData['distribution'][] = [
                    'label' => $option['option_text'],
                    'count' => (int)$option['votes'],
                    'percent' => round($option['votes'] * 100 / $totalVotes, 1),
                ];
            }
        } else {
            $answers = $db->fetchAll(
                'SELECT answer_text
                 FROM response_answers
                 WHERE question_id = ? AND answer_text IS NOT NULL
                 ORDER BY created_at DESC
                 LIMIT 200',
                [$questionId]
            );
            $questionData['answers'] = array_column($answers, 'answer_text');
        }

        $summary['questions'][$questionId] = $questionData;
    }

    $summary['keywords'] = extract_keyword_cloud($summary['questions']);

    return $summary;
}

function extract_keyword_cloud(array $questionData): array
{
    $textPool = [];
    foreach ($questionData as $data) {
        if ($data['type'] === 'text' && !empty($data['answers'])) {
            foreach ($data['answers'] as $answer) {
                $textPool[] = strtolower($answer);
            }
        }
    }

    $keywords = [];
    $stopWords = ['ve','ile','ama','fakat','cok','daha','bir','biraz','icin','olan','gibi','mi','de','da','ile'];

    foreach ($textPool as $text) {
        $tokens = preg_split('/[^a-z0-9çðýöþü]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($tokens as $token) {
            if (strlen($token) < 3) {
                continue;
            }
            if (in_array($token, $stopWords, true)) {
                continue;
            }
            $keywords[$token] = ($keywords[$token] ?? 0) + 1;
        }
    }

    arsort($keywords);
    return array_slice($keywords, 0, 30, true);
}
