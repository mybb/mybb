<?php

declare(strict_types=1);

namespace MyBB\Maintenance;

enum InstallationState: int
{
    case NONE = 0;
    case CONFIGURATION_FILE = 1;
    case DATABASE_CONNECTION = 2;
    case INSTALLED = 3;

    public static function get(bool $correctOldConfigFormat = false): self
    {
        $config = getConfigurationFileData($correctOldConfigFormat);

        if ($config !== null) {
            if (isset($GLOBALS['mybb'])) {
                // used in datacache class
                $GLOBALS['mybb']->config = $config;
            }

            $db = getDatabaseHandle(true);

            if (databaseHandleActive($db)) {
                if (getDatacacheVersion() !== null) {
                    $state = self::INSTALLED;
                } else {
                    $state = self::DATABASE_CONNECTION;
                }
            } else {
                $state = self::CONFIGURATION_FILE;
            }
        } else {
            $state = self::NONE;
        }

        return $state;
    }

    public static function getDescription(\MyLanguage $lang, bool $correctOldConfigFormat = false): ?string
    {
        return match (self::get($correctOldConfigFormat)) {
            self::CONFIGURATION_FILE => $lang->installation_state_configuration_file,
            self::DATABASE_CONNECTION => $lang->installation_state_database_connection,
            self::INSTALLED => $lang->sprintf(
                $lang->installation_state_installed,
                getDatacacheVersion(),
            ),
            default => null,
        };
    }
}
