<?php
class SurveyService
{
    private $db;
    private $config;
    private $aiClient;

    public function __construct(Database $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
        $this->aiClient = new AIClient($config['openai'] ?? []);
    }

    public function aiClient(): AIClient
    {
        return $this->aiClient;
    }

    public function getCategories(): array
    {
        return $this->db->fetchAll('SELECT * FROM survey_categories ORDER BY name ASC');
    }

    public function createCategory(string $name, ?string $description = null): int
    {
        return $this->db->insert(
            'INSERT INTO survey_categories (name, description) VALUES (?, ?)',
            [$name, $description]
        );
    }

    public function getSurveys(int $limit = 50): array
    {
        return $this->db->fetchAll(
            'SELECT s.*, c.name AS category_name, u.name AS owner_name
             FROM surveys s
             LEFT JOIN survey_categories c ON c.id = s.category_id
             LEFT JOIN users u ON u.id = s.created_by
             ORDER BY s.created_at DESC
             LIMIT ?',
            [$limit]
        );
    }

    public function getSurvey(int $surveyId)
    {
        return $this->db->fetch(
            'SELECT s.*, c.name AS category_name, u.name AS owner_name
             FROM surveys s
             LEFT JOIN survey_categories c ON c.id = s.category_id
             LEFT JOIN users u ON u.id = s.created_by
             WHERE s.id = ?',
            [$surveyId]
        );
    }

    public function createSurvey(array $data, int $userId): int
    {
        return $this->db->insert(
            'INSERT INTO surveys (category_id, title, description, status, start_date, end_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['category_id'] ?: null,
                $data['title'],
                $data['description'] ?? null,
                $data['status'] ?? 'draft',
                $data['start_date'] ?: null,
                $data['end_date'] ?: null,
                $userId,
            ]
        );
    }

    public function updateSurvey(int $surveyId, array $data): bool
    {
        return $this->db->execute(
            'UPDATE surveys
             SET category_id = ?, title = ?, description = ?, status = ?, start_date = ?, end_date = ?
             WHERE id = ?',
            [
                $data['category_id'] ?: null,
                $data['title'],
                $data['description'] ?? null,
                $data['status'] ?? 'draft',
                $data['start_date'] ?: null,
                $data['end_date'] ?: null,
                $surveyId,
            ]
        );
    }

    public function deleteSurvey(int $surveyId): bool
    {
        return $this->db->execute('DELETE FROM surveys WHERE id = ?', [$surveyId]);
    }

    public function getQuestion(int $questionId)
    {
        $question = $this->db->fetch('SELECT * FROM survey_questions WHERE id = ?', [$questionId]);
        if ($question && $question['question_type'] === 'multiple_choice') {
            $question['options'] = $this->db->fetchAll('SELECT * FROM question_options WHERE question_id = ? ORDER BY order_index ASC, id ASC', [$questionId]);
        }
        return $question ?: [];
    }

    public function getQuestions(int $surveyId): array
    {
        $questions = $this->db->fetchAll(
            'SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY order_index ASC, id ASC',
            [$surveyId]
        );
        foreach ($questions as &$question) {
            if ($question['question_type'] === 'multiple_choice') {
                $question['options'] = $this->db->fetchAll(
                    'SELECT * FROM question_options WHERE question_id = ? ORDER BY order_index ASC, id ASC',
                    [$question['id']]
                );
            }
        }
        return $questions;
    }

    public function addQuestion(int $surveyId, array $data): int
    {
        $questionId = $this->db->insert(
            'INSERT INTO survey_questions (survey_id, question_text, question_type, category_key, is_required, max_length, order_index)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $surveyId,
                $data['question_text'],
                $data['question_type'],
                $data['category_key'] ?? null,
                !empty($data['is_required']) ? 1 : 0,
                $data['max_length'] ?? null,
                $data['order_index'] ?? 0,
            ]
        );

        if (!empty($data['options']) && is_array($data['options'])) {
            $this->saveOptions($questionId, $data['options']);
        }

        return $questionId;
    }

    public function updateQuestion(int $questionId, array $data): bool
    {
        $this->db->execute(
            'UPDATE survey_questions SET question_text = ?, question_type = ?, category_key = ?, is_required = ?, max_length = ?, order_index = ?
             WHERE id = ?',
            [
                $data['question_text'],
                $data['question_type'],
                $data['category_key'] ?? null,
                !empty($data['is_required']) ? 1 : 0,
                $data['max_length'] ?? null,
                $data['order_index'] ?? 0,
                $questionId,
            ]
        );

        if (isset($data['options']) && is_array($data['options'])) {
            $this->db->execute('DELETE FROM question_options WHERE question_id = ?', [$questionId]);
            $this->saveOptions($questionId, $data['options']);
        }

        return true;
    }

    public function deleteQuestion(int $questionId): bool
    {
        return $this->db->execute('DELETE FROM survey_questions WHERE id = ?', [$questionId]);
    }

    private function saveOptions(int $questionId, array $options): void
    {
        $index = 0;
        foreach ($options as $option) {
            $text = is_array($option) ? ($option['text'] ?? '') : $option;
            $value = is_array($option) ? ($option['value'] ?? null) : null;
            if (trim($text) === '') {
                continue;
            }
            $this->db->insert(
                'INSERT INTO question_options (question_id, option_text, option_value, order_index)
                 VALUES (?, ?, ?, ?)',
                [$questionId, $text, $value, $index++]
            );
        }
    }

    public function getParticipants(int $surveyId): array
    {
        return $this->db->fetchAll(
            'SELECT sp.*, (
                SELECT sr.id
                FROM survey_responses sr
                WHERE sr.participant_id = sp.id
                ORDER BY sr.submitted_at DESC, sr.id DESC
                LIMIT 1
            ) AS last_response_id
             FROM survey_participants sp
             WHERE sp.survey_id = ?
             ORDER BY sp.created_at DESC',
            [$surveyId]
        );
    }

    public function addParticipant(int $surveyId, string $email): ?array
    {
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $existing = $this->db->fetch(
            'SELECT * FROM survey_participants WHERE survey_id = ? AND email = ?',
            [$surveyId, $email]
        );
        if ($existing) {
            return $existing;
        }

        $token = substr(generate_token(16), 0, 40);
        $tokenHash = signed_token($token);

        $id = $this->db->insert(
            'INSERT INTO survey_participants (survey_id, email, token, token_hash, invited_at)
             VALUES (?, ?, ?, ?, NOW())',
            [$surveyId, $email, $token, $tokenHash]
        );

        return $this->db->fetch('SELECT * FROM survey_participants WHERE id = ?', [$id]);
    }

    public function markInvitationSent(int $participantId): void
    {
        $this->db->execute('UPDATE survey_participants SET invited_at = NOW() WHERE id = ?', [$participantId]);
    }

    public function recordResponse(int $surveyId, ?int $participantId, array $answers): int
    {
        $responseId = $this->db->insert(
            'INSERT INTO survey_responses (survey_id, participant_id, submitted_at) VALUES (?, ?, NOW())',
            [$surveyId, $participantId]
        );

        foreach ($answers as $answer) {
            $questionId = (int)($answer['question_id'] ?? 0);
            if (!$questionId) {
                continue;
            }
            $optionId = $answer['option_id'] ?: null;
            $text = $answer['answer_text'] ?? null;
            $numeric = $answer['numeric_value'] ?? null;

            $this->db->insert(
                'INSERT INTO response_answers (response_id, question_id, option_id, answer_text, numeric_value)
                 VALUES (?, ?, ?, ?, ?)',
                [$responseId, $questionId, $optionId, $text, $numeric]
            );
        }

        if ($participantId) {
            $this->db->execute('UPDATE survey_participants SET responded_at = NOW() WHERE id = ?', [$participantId]);
        }

        return $responseId;
    }

    public function getParticipantByToken(int $surveyId, string $token)
    {
        return $this->db->fetch(
            'SELECT * FROM survey_participants WHERE survey_id = ? AND token = ?',
            [$surveyId, $token]
        );
    }

    public function getQuestionsForAnswering(int $surveyId): array
    {
        $questions = $this->db->fetchAll(
            'SELECT id, question_text, question_type, is_required, max_length
             FROM survey_questions
             WHERE survey_id = ?
             ORDER BY order_index ASC, id ASC',
            [$surveyId]
        );

        foreach ($questions as &$question) {
            if ($question['question_type'] === 'multiple_choice') {
                $question['options'] = $this->db->fetchAll(
                    'SELECT id, option_text FROM question_options WHERE question_id = ? ORDER BY order_index ASC, id ASC',
                    [$question['id']]
                );
            }
        }

        return $questions;
    }

    public function getSurveyWithQuestions(int $surveyId): array
    {
        $survey = $this->getSurvey($surveyId);
        if (!$survey) {
            return [];
        }
        $survey['questions'] = $this->getQuestions($surveyId);
        return $survey;
    }

    public function duplicateSurvey(int $surveyId, int $userId): ?int
    {
        $survey = $this->getSurveyWithQuestions($surveyId);
        if (!$survey) {
            return null;
        }

        $newSurveyId = $this->createSurvey([
            'category_id' => $survey['category_id'],
            'title' => $survey['title'] . ' (Kopya)',
            'description' => $survey['description'],
            'status' => 'draft',
            'start_date' => $survey['start_date'],
            'end_date' => $survey['end_date'],
        ], $userId);

        foreach ($survey['questions'] as $question) {
            $this->addQuestion($newSurveyId, [
                'question_text' => $question['question_text'],
                'question_type' => $question['question_type'],
                'category_key' => $question['category_key'] ?? null,
                'is_required' => $question['is_required'],
                'max_length' => $question['max_length'],
                'order_index' => $question['order_index'],
                'options' => $question['options'] ?? [],
            ]);
        }

        return $newSurveyId;
    }

    public function recordReport(int $surveyId, string $type, array $payload): int
    {
        return $this->db->insert(
            'INSERT INTO survey_reports (survey_id, report_type, payload) VALUES (?, ?, ?)',
            [$surveyId, $type, json_encode($payload, JSON_UNESCAPED_UNICODE)]
        );
    }

    public function getReports(int $surveyId, ?string $type = null): array
    {
        if ($type) {
            $reports = $this->db->fetchAll(
                'SELECT * FROM survey_reports WHERE survey_id = ? AND report_type = ? ORDER BY created_at DESC',
                [$surveyId, $type]
            );
        } else {
            $reports = $this->db->fetchAll(
                'SELECT * FROM survey_reports WHERE survey_id = ? ORDER BY created_at DESC',
                [$surveyId]
            );
        }

        foreach ($reports as &$report) {
            $report['payload'] = json_decode($report['payload'] ?? '', true) ?: [];
        }

        return $reports;
    }

    public function getParticipantResponses(int $participantId): array
    {
        $participant = $this->db->fetch(
            'SELECT sp.*, s.id AS survey_id
             FROM survey_participants sp
             INNER JOIN surveys s ON s.id = sp.survey_id
             WHERE sp.id = ?',
            [$participantId]
        );

        if (!$participant) {
            return [];
        }

        $response = $this->db->fetch(
            'SELECT * FROM survey_responses WHERE participant_id = ? ORDER BY submitted_at DESC, id DESC LIMIT 1',
            [$participantId]
        );

        if (!$response) {
            $survey = $this->getSurvey((int)$participant['survey_id']);

            return [
                'survey' => $survey ?: [],
                'response' => null,
                'participant' => [
                    'id' => (int)$participant['id'],
                    'email' => $participant['email'],
                    'token' => $participant['token'],
                ],
                'categories' => [],
                'overview' => [
                    'average_score' => null,
                    'category_count' => 0,
                    'strengths' => [],
                    'gaps' => [],
                ],
            ];
        }

        return $this->getResponseReport((int)$response['id']);
    }

    public function getResponseReport(int $responseId): array
    {
        $response = $this->db->fetch(
            'SELECT sr.*, sp.email AS participant_email, sp.token, sp.id AS participant_id
             FROM survey_responses sr
             LEFT JOIN survey_participants sp ON sp.id = sr.participant_id
             WHERE sr.id = ?'
            , [$responseId]
        );

        if (!$response) {
            return [];
        }

        $survey = $this->getSurvey($response['survey_id']);
        if (!$survey) {
            return [];
        }

        $answerRows = $this->db->fetchAll(
            'SELECT ra.*, sq.question_text, sq.question_type, sq.category_key, sq.order_index,
                    qo.option_text
             FROM response_answers ra
             INNER JOIN survey_questions sq ON sq.id = ra.question_id
             LEFT JOIN question_options qo ON qo.id = ra.option_id
             WHERE ra.response_id = ?
             ORDER BY sq.order_index ASC, sq.id ASC'
            , [$responseId]
        );

        $categories = $this->groupAnswersByCategory($answerRows);
        $overview = $this->summarizeCategories($categories);

        return [
            'survey' => $survey,
            'response' => $response,
            'participant' => [
                'id' => $response['participant_id'] ?? null,
                'email' => $response['participant_email'] ?? null,
                'token' => $response['token'] ?? null,
            ],
            'categories' => $categories,
            'overview' => $overview,
        ];
    }

    public function generatePersonalReport(array $bundle): array
    {
        if (empty($bundle)) {
            return [];
        }

        if (!isset($bundle['categories']) && isset($bundle['answers']) && is_array($bundle['answers'])) {
            $bundle['categories'] = $this->groupAnswersByCategory($bundle['answers']);
        }

        $categories = $bundle['categories'] ?? [];

        if (!isset($bundle['overview'])) {
            $bundle['overview'] = $this->summarizeCategories($categories);
        }

        $payload = [
            'survey' => [
                'title' => $bundle['survey']['title'] ?? ''
            ],
            'categories' => array_map(function (array $category) {
                return [
                    'key' => $category['key'],
                    'label' => $category['label'],
                    'average' => $category['average_score'],
                    'highlights' => array_slice($category['text_answers'], 0, 3),
                    'answers' => array_map(function (array $question) {
                        return [
                            'question' => $question['question'],
                            'type' => $question['type'],
                            'answer' => $question['answer'],
                        ];
                    }, $category['questions']),
                ];
            }, $categories),
            'strengths' => $bundle['overview']['strengths'] ?? [],
            'gaps' => $bundle['overview']['gaps'] ?? [],
        ];

        $bundle['advice'] = $this->aiClient()->generatePersonalAdvice($payload);

        return $bundle;
    }

    private function groupAnswersByCategory(array $rows): array
    {
        $categories = [];
        foreach ($rows as $row) {
            $key = $row['category_key'] ?: 'genel';
            if (!isset($categories[$key])) {
                $categories[$key] = [
                    'key' => $key,
                    'label' => $this->formatCategoryLabel($key),
                    'questions' => [],
                    'scores' => [],
                    'text_answers' => [],
                ];
            }

            $question = [
                'id' => (int)$row['question_id'],
                'question' => $row['question_text'],
                'type' => $row['question_type'],
                'answer' => null,
            ];

            if ($row['question_type'] === 'rating') {
                $score = $row['numeric_value'] !== null ? (float)$row['numeric_value'] : null;
                if ($score !== null) {
                    $categories[$key]['scores'][] = $score;
                }
                $question['score'] = $score;
                $question['answer'] = $score;
            } elseif ($row['question_type'] === 'multiple_choice') {
                $choice = $row['option_text'] ?? '';
                $question['choice'] = $choice;
                $question['answer'] = $choice;
            } else {
                $text = $row['answer_text'] ?? '';
                if ($text !== '') {
                    $categories[$key]['text_answers'][] = $text;
                }
                $question['text'] = $text;
                $question['answer'] = $text;
            }

            $categories[$key]['questions'][] = $question;
        }

        foreach ($categories as &$category) {
            $count = count($category['scores']);
            $category['average_score'] = $count ? round(array_sum($category['scores']) / $count, 2) : null;
            $category['score_count'] = $count;
        }

        return array_values($categories);
    }

    private function summarizeCategories(array $categories): array
    {
        $averages = [];
        $strengths = [];
        $gaps = [];

        foreach ($categories as $category) {
            if (!empty($category['average_score'])) {
                $averages[] = $category['average_score'];
                if ($category['average_score'] >= 4) {
                    $strengths[] = $category['label'];
                } elseif ($category['average_score'] < 3) {
                    $gaps[] = $category['label'];
                }
            }
        }

        return [
            'average_score' => $averages ? round(array_sum($averages) / count($averages), 2) : null,
            'category_count' => count($categories),
            'strengths' => $strengths,
            'gaps' => $gaps,
        ];
    }

    private function formatCategoryLabel(?string $key): string
    {
        if (!$key || $key === 'genel') {
            return 'Genel';
        }

        $label = str_replace(['_', '-'], ' ', $key);
        return ucwords($label);
    }
}







