<?php

namespace App\Libraries;

class SettingsStore
{
    private string $path;

    public function __construct()
    {
        $this->path = WRITEPATH . 'settings.json';
    }

    public function defaults(): array
    {
        $python = config('Python');
        $numpy  = config('Numpy');

        return [
            'python' => [
                'api_url'  => $python->apiURL,
                'timeout'  => (int) $python->timeout,
                'enabled'  => (bool) $python->enabled,
            ],
            'numpy' => [
                'default_operation'      => $numpy->defaultOperation,
                'decimal_precision'      => (int) $numpy->decimalPrecision,
                'allow_negative'         => (bool) $numpy->allowNegative,
                'allow_decimal'          => (bool) $numpy->allowDecimal,
                'max_input_values'       => (int) $numpy->maxInputValues,
                'auto_array_conversion'  => (bool) $numpy->autoArrayConversion,
                'reshape_rows'           => (int) $numpy->reshapeRows,
                'reshape_cols'           => (int) $numpy->reshapeCols,
            ],
        ];
    }

    public function get(): array
    {
        $defaults = $this->defaults();

        if (! is_file($this->path)) {
            return $defaults;
        }

        $raw  = file_get_contents($this->path);
        $data = json_decode($raw ?: '', true);

        if (! is_array($data)) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $data);
    }

    public function save(array $incoming): array
    {
        $current = $this->get();
        $clean   = $this->sanitize($incoming);
        $merged  = array_replace_recursive($current, $clean);

        file_put_contents(
            $this->path,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $merged;
    }

    private function sanitize(array $incoming): array
    {
        $out = [];

        if (isset($incoming['python']) && is_array($incoming['python'])) {
            $python = $incoming['python'];

            if (isset($python['api_url'])) {
                $url = trim((string) $python['api_url']);
                if ($this->isSafeUrl($url)) {
                    $out['python']['api_url'] = rtrim($url, '/');
                }
            }

            if (isset($python['timeout'])) {
                $timeout = (int) $python['timeout'];
                $out['python']['timeout'] = max(1, min(300, $timeout));
            }

            if (array_key_exists('enabled', $python)) {
                $out['python']['enabled'] = $this->toBool($python['enabled']);
            }
        }

        if (isset($incoming['numpy']) && is_array($incoming['numpy'])) {
            $numpy = $incoming['numpy'];
            $allowedOps = ['create', 'sum', 'mean', 'min', 'max', 'std', 'sort', 'filter', 'reshape', 'info', 'abs', 'square'];

            if (isset($numpy['default_operation'])) {
                $op = strtolower(trim((string) $numpy['default_operation']));
                if (in_array($op, $allowedOps, true)) {
                    $out['numpy']['default_operation'] = $op;
                }
            }

            if (isset($numpy['decimal_precision'])) {
                $out['numpy']['decimal_precision'] = max(0, min(10, (int) $numpy['decimal_precision']));
            }

            if (array_key_exists('allow_negative', $numpy)) {
                $out['numpy']['allow_negative'] = $this->toBool($numpy['allow_negative']);
            }

            if (array_key_exists('allow_decimal', $numpy)) {
                $out['numpy']['allow_decimal'] = $this->toBool($numpy['allow_decimal']);
            }

            if (isset($numpy['max_input_values'])) {
                $out['numpy']['max_input_values'] = max(1, min(100000, (int) $numpy['max_input_values']));
            }

            if (array_key_exists('auto_array_conversion', $numpy)) {
                $out['numpy']['auto_array_conversion'] = $this->toBool($numpy['auto_array_conversion']);
            }

            if (isset($numpy['reshape_rows'])) {
                $out['numpy']['reshape_rows'] = max(1, min(10000, (int) $numpy['reshape_rows']));
            }

            if (isset($numpy['reshape_cols'])) {
                $out['numpy']['reshape_cols'] = max(1, min(10000, (int) $numpy['reshape_cols']));
            }
        }

        return $out;
    }

    private function isSafeUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > 2048) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        return in_array(strtolower($parts['scheme']), ['http', 'https'], true);
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }
}
