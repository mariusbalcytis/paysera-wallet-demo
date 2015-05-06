<?php

namespace Maba\Bundle\PayseraWalletDemoBundle\Services;

class ConfigurationProvider
{

    public function __construct($cacheDir)
    {
        $this->cacheDir = $cacheDir;
    }

    public function get()
    {
        if (is_file($this->cacheDir . '/config.json')) {
            return json_decode(file_get_contents($this->cacheDir . '/config.json'), true);
        }
        return null;
    }

    public function set($data)
    {
        file_put_contents(
            $this->cacheDir . '/config.json',
            json_encode($data)
        );
    }
}
