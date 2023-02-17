<?php

declare(strict_types=1);

namespace MyBB\Maintenance\Process;

/**
 * @psalm-import-type OperationResult from Runtime
 */
abstract class Helpers
{
    /**
     * @param OperationResult $operationResult
     *
     * @psalm-pure
     */
    public static function getLocalizedOperationResult(string $name, array $operationResult, \MyLanguage $lang): array
    {
        if (isset($operationResult['error'])) {
            if (!isset($operationResult['error']['title'])) {
                $operationResult['error']['title'] = $lang->{'operation_' . $name . '_error_title'} ?? $lang->sprintf(
                    $lang->operation_error_title,
                    $lang->{'operation_' . $name . '_title'} ?? $name
                );
            }
            if (!isset($operationResult['error']['message'])) {
                $operationResult['error']['message'] = $lang->{'operation_' . $name . '_error_message'} ?? $lang->sprintf(
                    $lang->operation_error_message,
                    $lang->{'operation_' . $name . '_title'} ?? $name
                );
            }
        }
        if (isset($operationResult['warning'])) {
            if (!isset($operationResult['warning']['title'])) {
                $operationResult['warning']['title'] = $lang->{'operation_' . $name . '_warning_title'} ?? $lang->sprintf(
                    $lang->operation_warning_title,
                    $lang->{'operation_' . $name . '_title'} ?? $name
                );
            }
        }

        return $operationResult;
    }

    public static function getNonDefaultFlagValueDescriptions(Runtime $process, \MyLanguage $lang): array
    {
        $values = [];

        $flags = $process->model->getFlags();

        foreach (array_filter($process->getNonDefaultFlagValues()) as $name => $value) {
            $text = $lang->{'flag_' . $name};

            if ($flags[$name]['type'] !== 'boolean') {
                $text = $lang->sprintf(
                    $lang->flag_value,
                    $text,
                    $value,
                );
            }

            $values[] = $text;
        }

        return $values;
    }
}
