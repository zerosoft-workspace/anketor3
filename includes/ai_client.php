<?php
class AIClient
{
    private $apiKey;
    private $model;
    private $provider;
    private $baseUrl;
    private $deployment;
    private $azureApiVersion;
    private $organization;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'gpt-4o-mini';
        $this->provider = $config['provider'] ?? 'openai';
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $this->deployment = $config['deployment'] ?? null;
        $this->azureApiVersion = $config['azure_api_version'] ?? '2024-02-15-preview';
        $this->organization = $config['organization'] ?? null;
    }

    public function isEnabled(): bool
    {
        return !empty($this->apiKey);
    }

    public function suggestQuestions(string $topic, int $count = 5): array
    {
        if (!$this->isEnabled()) {
            return $this->fallbackQuestions($topic, $count);
        }

        $prompt = sprintf(
            'Turkish: %d adet anket sorusu oner. Tema: %s. Yaniti yalnizca JSON array formatinda don.',
            $count,
            $topic
        );

        $response = $this->request($prompt, 400);
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return $this->fallbackQuestions($topic, $count);
        }

        return array_slice($data, 0, $count);
    }

    public function generateSmartReport(array $payload): string
    {
        if (!$this->isEnabled()) {
            return $this->fallbackReport($payload);
        }

        $prompt = 'Asagidaki anket sonuclarini analiz et. Guclu yanlar, gelistirilmesi gereken alanlar ve aksiyon onerileri sun. Profesyonel bir dille yaz.';

        $content = $prompt . "\n\nVeriler:\n" . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $response = $this->request($content, 800);
        return $response ?: $this->fallbackReport($payload);
    }

    private function request(string $prompt, int $maxTokens)
    {
        [$url, $headers, $payload] = $this->buildRequest($prompt, $maxTokens);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            curl_close($ch);
            return null;
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($result, true);
        if ($status >= 200 && $status < 300) {
            if (isset($decoded['output_text'])) {
                return trim($decoded['output_text']);
            }

            if ($this->provider === 'google_gemini') {
                $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text) {
                    return trim($text);
                }
            }
        }

        return null;
    }

    private function buildRequest(string $prompt, int $maxTokens): array
    {
        $headers = ['Content-Type: application/json'];
        $url = $this->baseUrl . '/responses';
        $payload = [
            'model' => $this->model,
            'input' => $prompt,
            'max_output_tokens' => $maxTokens,
        ];

        switch ($this->provider) {
            case 'azure_openai':
                $deployment = $this->deployment ?: $this->model;
                $url = rtrim($this->baseUrl ?: '', '/') . '/openai/deployments/' . $deployment . '/responses?api-version=' . urlencode($this->azureApiVersion ?: '2024-02-15-preview');
                $headers[] = 'api-key: ' . $this->apiKey;
                break;
            case 'google_gemini':
                $url = rtrim($this->baseUrl ?: 'https://generativelanguage.googleapis.com/v1beta', '/') . '/models/' . $this->model . ':generateContent?key=' . urlencode($this->apiKey);
                $payload = [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => $maxTokens,
                    ],
                ];
                break;
            default:
                $headers[] = 'Authorization: Bearer ' . $this->apiKey;
                if (!empty($this->organization)) {
                    $headers[] = 'OpenAI-Organization: ' . $this->organization;
                }
                break;
        }

        return [$url, $headers, $payload];
    }

    private function fallbackQuestions(string $topic, int $count): array
    {
        $seeds = [
            sprintf('%s konusunda en buyuk guclerimiz nelerdir?', $topic),
            sprintf('%s alaninda hangi zorluklarla karsilasiyorsunuz?', $topic),
            sprintf('%s surecini nasil gelistirebiliriz?', $topic),
            sprintf('%s ile ilgili memnuniyetinizi 1-5 arasinda degerlendirin.', $topic),
            sprintf('%s icin hangi desteklere ihtiyaciniz var?', $topic),
        ];

        return array_slice($seeds, 0, $count);
    }

    private function fallbackReport(array $payload): string
    {
        $responses = $payload['responses'] ?? 0;
        $highlights = $payload['highlights'] ?? [];
        $lowlights = $payload['lowlights'] ?? [];

        $report = "Girdi sayisi: {$responses}.\n";
        if ($highlights) {
            $report .= "Guclu Alanlar:\n- " . implode("\n- ", $highlights) . "\n";
        }
        if ($lowlights) {
            $report .= "Gelisim Alanlari:\n- " . implode("\n- ", $lowlights) . "\n";
        }
        $report .= "Onerilen Aksiyonlar:\n- Hedefli gelisim planlari olusturun.\n- Yonetici ve calisan iletisimi icin duzenli geri bildirim oturumlari planlayin.\n";
        return $report;
    }

    public function generatePersonalAdvice(array $payload): string
    {
        if (!$this->isEnabled()) {
            return $this->fallbackPersonalAdvice($payload);
        }

        $prompt = 'Asagidaki bireysel anket yanitlarini analiz et. Her kategori icin kisisel geri bildirim ve uygulanabilir aksiyonlar sun. Kisinin seviyesine gore Turkce oneriler yaz.';
        $content = $prompt . "\n\nVeriler:\n" . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $response = $this->request($content, 600);

        return $response ?: $this->fallbackPersonalAdvice($payload);
    }

    private function fallbackPersonalAdvice(array $payload): string
    {
        $lines = [];
        if (!empty($payload['strengths'])) {
            $lines[] = 'Guclu alanlar: ' . implode(', ', $payload['strengths']);
        }
        if (!empty($payload['gaps'])) {
            $lines[] = 'Gelisim alanlari: ' . implode(', ', $payload['gaps']);
        }
        if (!empty($payload['categories'])) {
            foreach ($payload['categories'] as $category) {
                $label = $category['label'] ?? 'Kategori';
                $avg = $category['average'] !== null ? number_format((float)$category['average'], 2) : 'veri yok';
                $lines[] = $label . ' ortalama puan: ' . $avg;
            }
        }
        $lines[] = 'Aksiyon: Zayif alanlar icin bir gelisim hedefi belirleyin ve bir sonraki doneme kadar ilerleyisinizi takip edin.';

        return implode("\n", array_filter($lines));
    }
}
