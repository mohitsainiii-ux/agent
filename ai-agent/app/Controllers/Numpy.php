<?php

namespace App\Controllers;

use App\Libraries\NumpyInputParser;
use App\Libraries\PythonApiClient;
use App\Libraries\SettingsStore;

class Numpy extends BaseController
{
    private const OPERATIONS = ['create', 'sum', 'mean', 'min', 'max', 'std', 'sort', 'filter', 'reshape', 'info', 'abs', 'square'];

    public function health()
    {
        $settings = (new SettingsStore())->get();
        $result   = (new PythonApiClient())->health(
            $settings['python']['api_url'],
            (int) $settings['python']['timeout']
        );

        return $this->response->setJSON($result);
    }

    public function process()
    {
        $settings = (new SettingsStore())->get();

        if (empty($settings['python']['enabled'])) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'Python processing is disabled. Enable it in Settings.',
            ]);
        }

        $json      = $this->request->getJSON(true) ?? [];
        $operation = strtolower(trim((string) ($json['operation'] ?? '')));
        $rawInput  = (string) ($json['input'] ?? '');

        if (! in_array($operation, self::OPERATIONS, true)) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'Invalid operation. Choose a supported NumPy operation.',
            ]);
        }

        $parsed = (new NumpyInputParser())->parse($rawInput, $settings['numpy']);
        if (empty($parsed['success'])) {
            return $this->response->setJSON($parsed);
        }

        $numpy = $settings['numpy'];
        $filterValue = null;
        if (array_key_exists('filter_value', $json) && $json['filter_value'] !== null && $json['filter_value'] !== '') {
            if (! is_numeric($json['filter_value']) || ! is_finite((float) $json['filter_value'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'error'   => 'Filter value must be a finite number.',
                ]);
            }
            $filterValue = (float) $json['filter_value'];
        }

        $reshapeRows = $this->positiveInteger($json['reshape_rows'] ?? $numpy['reshape_rows']);
        $reshapeCols = $this->positiveInteger($json['reshape_cols'] ?? $numpy['reshape_cols']);
        if ($reshapeRows === null || $reshapeCols === null) {
            return $this->response->setJSON([
                'success' => false,
                'error'   => 'Reshape rows and columns must be positive whole numbers.',
            ]);
        }

        $options = [
            'precision'         => (int) $numpy['decimal_precision'],
            'allow_negative'    => (bool) $numpy['allow_negative'],
            'allow_decimal'     => (bool) $numpy['allow_decimal'],
            'max_values'        => (int) $numpy['max_input_values'],
            'reshape_rows'      => $reshapeRows,
            'reshape_cols'      => $reshapeCols,
            'filter_condition'  => $json['filter_condition'] ?? 'gt',
            'filter_value'      => $filterValue,
        ];

        $payload = [
            'operation' => $operation,
            'data'      => $parsed['data'],
            'options'   => $options,
        ];

        $client = new PythonApiClient();
        $result = match ($operation) {
            'create'  => $client->createArray($settings['python']['api_url'], (int) $settings['python']['timeout'], $payload),
            'reshape' => $client->reshape($settings['python']['api_url'], (int) $settings['python']['timeout'], [
                'data'    => $parsed['data'],
                'rows'    => $options['reshape_rows'],
                'cols'    => $options['reshape_cols'],
                'options' => $options,
            ]),
            default   => $client->process($settings['python']['api_url'], (int) $settings['python']['timeout'], $payload),
        };

        if (! empty($result['success'])) {
            $result['display'] = $this->formatDisplay($operation, $result['result'] ?? null);
        }

        return $this->response->setJSON($result);
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            $integer = (int) $value;
            return $integer > 0 ? $integer : null;
        }

        return null;
    }

    private function formatDisplay(string $operation, mixed $result): string
    {
        $labels = [
            'create'  => 'Array',
            'sum'     => 'Sum',
            'mean'    => 'Mean',
            'min'     => 'Minimum',
            'max'     => 'Maximum',
            'std'     => 'Standard deviation',
            'sort'    => 'Sorted',
            'filter'  => 'Filtered',
            'reshape' => 'Reshaped array',
            'info'    => 'Array information',
            'abs'     => 'Absolute values',
            'square'  => 'Squared values',
        ];

        $label = $labels[$operation] ?? ucfirst($operation);

        if (is_array($result)) {
            return $label . ': ' . json_encode($result);
        }

        if (is_scalar($result)) {
            return $label . ': ' . $result;
        }

        return $label . ': (no result)';
    }
}
