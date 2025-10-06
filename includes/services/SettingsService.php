<?php
class SettingsService
{
    private $db;
    private $cache;
    private $tableEnsured;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->cache = null;
        $this->tableEnsured = false;
    }

    public function all(): array
    {
        if ($this->cache === null) {
            try {
                $this->ensureTableExists();
                $rows = $this->db->fetchAll('SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key ASC');
                $settings = [];
                foreach ($rows as $row) {
                    $settings[$row['setting_key']] = $this->decodeValue($row['setting_value']);
                }
                $this->cache = $settings;
            } catch (PDOException $exception) {
                if ($this->isMissingTableError($exception)) {
                    $this->cache = [];
                } else {
                    throw $exception;
                }
            }
        }

        return $this->cache;
    }

    public function get(string $key, $default = null)
    {
        $settings = $this->all();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public function setMany(array $settings): void
    {
        $this->ensureTableExists();

        foreach ($settings as $key => $value) {
            $encoded = $this->encodeValue($value);
            $exists = $this->db->fetch('SELECT id FROM system_settings WHERE setting_key = ?', [$key]);
            if ($exists) {
                $this->db->execute('UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?', [$encoded, $key]);
            } else {
                $this->db->insert('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)', [$key, $encoded]);
            }
        }

        $this->cache = null;
    }

    public function asConfig(): array
    {
        $config = [];
        foreach ($this->all() as $key => $value) {
            $segments = explode('.', $key);
            $cursor = &$config;
            foreach ($segments as $segment) {
                if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                    $cursor[$segment] = [];
                }
                $cursor = &$cursor[$segment];
            }
            $cursor = $value;
            unset($cursor);
        }

        return $config;
    }

    public function reload(): void
    {
        $this->cache = null;
    }

    private function encodeValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function decodeValue($value)
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '1' || $trimmed === '0') {
            return $trimmed === '1';
        }

        $json = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($json) || is_object($json))) {
            return $json;
        }

        return $value;
    }

    private function ensureTableExists(): void
    {
        if ($this->tableEnsured) {
            return;
        }

        try {
            $this->db->execute("CREATE TABLE IF NOT EXISTS system_settings (\n                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n                setting_key VARCHAR(120) NOT NULL UNIQUE,\n                setting_value TEXT NULL,\n                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (PDOException $exception) {
            if (!$this->isMissingTableError($exception) && $exception->getCode() !== '42S01') {
                throw $exception;
            }
        }

        $this->tableEnsured = true;
    }

    private function isMissingTableError(PDOException $exception): bool
    {
        if ($exception->getCode() === '42S02') {
            return true;
        }

        return stripos($exception->getMessage(), 'Base table or view not found') !== false;
    }
}
