<?php

declare(strict_types=1);

namespace MyBB\Utilities;

abstract class Arrays
{
    /**
     * @param list<array-key> $path
     */
    public static function getNested(array $array, array $path): mixed
    {
        $target = &$array;

        foreach ($path as $key) {
            if (is_array($target) && array_key_exists($key, $target)) {
                $target = &$target[$key];
            } else {
                return null;
            }
        }

        return $target;
    }

    /**
     * @param list<array-key> $path
     */
    public static function setNested(array &$array, array $path, mixed $value): void
    {
        $target = &$array;

        foreach ($path as $key) {
            if (!isset($target) || !is_array($target)) {
                $target = [];
            }

            $target = &$target[$key];
        }

        $target = $value;
    }

    /**
     * @param list<array-key> $path
     */
    public static function deleteNested(array &$array, array $path): bool
    {
        if ($path === []) {
            if ($array === []) {
                return false;
            } else {
                $array = [];

                return true;
            }
        } else {
            $targetKey = array_pop($path);

            $targetArray = &$array;

            foreach ($path as $pathKey) {
                if (
                    !array_key_exists($pathKey, $targetArray) ||
                    !is_array($targetArray[$pathKey])
                ) {
                    return false;
                }

                $targetArray = &$targetArray[$pathKey];
            }

            unset($targetArray[$targetKey]);

            return true;
        }
    }
}
