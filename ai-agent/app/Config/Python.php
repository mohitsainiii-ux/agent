<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Python extends BaseConfig
{
    public string $apiURL = 'http://127.0.0.1:9000';
    public int $timeout = 30;
    public bool $enabled = true;
}
