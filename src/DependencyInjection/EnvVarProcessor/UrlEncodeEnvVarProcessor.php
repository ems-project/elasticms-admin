<?php

declare(strict_types=1);

namespace App\DependencyInjection\EnvVarProcessor;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

final class UrlEncodeEnvVarProcessor implements EnvVarProcessorInterface
{
    /**
     * @param string $prefix
     * @param string $name
     *
     * @return mixed|string
     */
    public function getEnv($prefix, $name, \Closure $getEnv)
    {
        $env = $getEnv($name);

        return \urlencode($env);
    }

    public static function getProvidedTypes()
    {
        return [
            'urlencode' => 'string',
        ];
    }
}
