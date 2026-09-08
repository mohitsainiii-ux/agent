<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Numpy extends BaseConfig
{
    public string $defaultOperation = 'sum';
    public int $decimalPrecision = 2;
    public bool $allowNegative = true;
    public bool $allowDecimal = true;
    public int $maxInputValues = 10000;
    public bool $autoArrayConversion = true;
    public int $reshapeRows = 1;
    public int $reshapeCols = 5;
}
