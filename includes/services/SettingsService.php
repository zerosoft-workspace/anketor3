<?php
class SettingsService
{
    private $db;
    private $cache;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->cache = null;
    }

    public function all(): array
    {
        if ($this->cache === null) {
            $rows = $this->db->fetchAll('SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key ASC');
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $this->decodeValue($row['setting_value']);
            }
            $this->cache = $settings;
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
}
