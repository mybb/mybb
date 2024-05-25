<?php

declare(strict_types=1);

namespace MyBB\Maintenance\Process\Model;

use MyBB;
use MyLanguage;

/**
 * @psalm-import-type OperationCallbackResult from \MyBB\Maintenance\Process\Model
 */
trait CommonModelTrait
{
    /**
     * @return OperationCallbackResult
     */
    private static function fileVerificationOperation(MyLanguage $lang): array
    {
        $errors = \MyBB\Maintenance\getFileVerificationErrors();

        if ($errors === null) {
            $result = [
                'warning' => [
                    'message' => $lang->sprintf(
                        $lang->file_verification_checksums_missing,
                        'inc/checksums',
                    ),
                ],
                'retry' => true,
            ];
        } elseif (array_merge_recursive($errors) === []) {
            $result = [];
        } else {
            $message = $lang->sprintf(
                $lang->file_verification_failed,
                count($errors['missing']) + count($errors['changed']),
            );

            $list = [];

            foreach ($errors['missing'] as $relativePath) {
                $list[] = $lang->sprintf(
                    $lang->file_verification_missing,
                    '<code>' . htmlspecialchars_uni($relativePath) . '</code>',
                );
            }

            foreach ($errors['changed'] as $relativePath) {
                $list[] = $lang->sprintf(
                    $lang->file_verification_changed,
                    '<code>' . htmlspecialchars_uni($relativePath) . '</code>',
                );
            }

            $result = [
                'warning' => [
                    'message' => $message,
                    'list' => $list,
                ],
                'retry' => true,
            ];
        }

        return $result;
    }

    /**
     * @return OperationCallbackResult
     */
    private static function lockOperation(MyLanguage $lang): array
    {
        $types = [null];

        $dedicatedLockProcessNames = [
             'install',
        ];

        if (in_array(self::NAME, $dedicatedLockProcessNames)) {
            $types[] = self::NAME;
        }

        foreach ($types as $type) {
            try {
                \MyBB\Maintenance\createLockFile($type);
            } catch (\Exception) {
                return [
                    'warning' => [
                        'message' => $lang->sprintf(
                            $lang->lock_file_not_writable,
                            'install/lock' . ($type === null ?: '_' . $type),
                        ),
                    ],
                    'retry' => true,
                ];
            }
        }

        return [];
    }
}
