<?php

declare(strict_types=1);

final class Config
{
    public static function get(): array
    {
        return array_merge(self::getServiceConfig(), self::getBoardConfig(), self::override());
    }

    public static function getServiceConfig()
    {
        static $serviceConfig = null;

        if ($serviceConfig) {
            return $serviceConfig;
        }

        require JAXBOARDS_ROOT . '/config.php';

        $serviceConfig = $CFG;

        return $serviceConfig;
    }

    public static function getBoardConfig($write = null)
    {
        static $boardConfig = null;

        if ($write) {
            $boardConfig = array_merge($boardConfig, $write);
        }

        if ($boardConfig) {
            return $boardConfig;
        }

        if (!defined('BOARDPATH')) {
            $boardConfig = ['noboard' => 1];

            return $boardConfig;
        }

        require BOARDPATH . '/config.php';

        $boardConfig = $CFG;

        return $boardConfig;
    }

    public static function getSetting(string $key)
    {
        $config = self::get();

        if (array_key_exists($key, $config)) {
            return $config[$key];
        }

        return null;
    }

    public static function override($override = null)
    {
        static $overrideConfig = [];

        if ($override) {
            $overrideConfig = $override;
        }

        return $overrideConfig;
    }

    public static function write($data): void
    {
        $boardConfig = self::getBoardConfig($data);

        if (!defined('BOARDPATH')) {
            throw new Exception('Board config file not determinable');
        }

        file_put_contents(BOARDPATH . 'config.php', self::configFileContents($boardConfig));
    }

    // Only used during installation
    public static function writeServiceConfig($data): void
    {
        file_put_contents(JAXBOARDS_ROOT . '/config.php', self::configFileContents($data));
    }

    private static function configFileContents($data): string
    {
        $dataString = json_encode($data, JSON_PRETTY_PRINT);

        return <<<EOT
            <?php
            /**
             * JaxBoards config file. It's just JSON embedded in PHP- wow!
             *
             * PHP Version 5.3.0
             *
             * @category Jaxboards
             * @package  Jaxboards
             *
             * @author  Sean Johnson <seanjohnson08@gmail.com>
             * @author  World's Tallest Ladder <wtl420@users.noreply.github.com>
             * @license MIT <https://opensource.org/licenses/MIT>
             *
             * @link https://github.com/Jaxboards/Jaxboards Jaxboards on Github
             */
            \$CFG = json_decode(
            <<<'EOD'
            {$dataString}
            EOD
                ,
                true
            );
            EOT;
    }
}
