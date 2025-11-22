<?php

declare(strict_types=1);

namespace MyBB\Extensions\Traits;

trait IntegrityTrait
{
    /**
     * The path to the checksums file, relative to the Extension's package directory.
     */
    final public const CHECKSUMS_FILE_PATH = 'checksums';

    /**
     * The algorithm used for checksum verification.
     */
    final public const CHECKSUMS_ALGORITHM = 'sha512';

    /**
     * Acceptable checksums of files in the package, by package-relative path.
     *
     * @var array<string, string[]>
     */
    protected array $declaredFileChecksums;

    /**
     * Returns the declared acceptable checksums of files in the package,
     *   by package-relative path.
     *
     * @return null|array<string, string[]>
     *
     * @see \MyBB\Maintenance\getDeclaredFileChecksums()
     */
    public function getDeclaredFileChecksums(): ?array
    {
        if (!isset($this->declaredFileChecksums)) {
            $declaredFiles = [];

            $path = $this->getChecksumsFilePath();

            if (!$this->filesystem->isFile($path)) {
                return null;
            }

            $lines = $this->filesystem->lines($path);

            foreach ($lines as $line) {
                $parts = explode(' ', $line, 2);

                if (count($parts) !== 2) {
                    continue;
                }

                $declaredChecksum = trim($parts[0]);
                $relativePath = trim($parts[1]);

                if (!isset($declaredFiles[$relativePath])) {
                    $declaredFiles[$relativePath] = [];
                }

                $declaredFiles[$relativePath][] = $declaredChecksum;
            }

            $this->declaredFileChecksums = $declaredFiles;
        }

        return $this->declaredFileChecksums;
    }

    /**
     * Returns the path to the Extension's checksums file.
     */
    public function getChecksumsFilePath(): string
    {
        return $this->getAbsolutePath() . '/' . static::CHECKSUMS_FILE_PATH;
    }

    /**
     * Returns a list of file checksum mismatches by type.
     * Files not declared with checksums are ignored.
     *
     * @return null|array{
     *   changed: string[],
     *   missing: string[],
     * }
     *
     * @see \MyBB\Maintenance\getFileVerificationErrors()
     */
    public function getFileVerificationErrors(): ?array
    {
        $extensionAbsolutePath = $this->getAbsolutePath();

        $fileChecksums = $this->getDeclaredFileChecksums();

        if ($fileChecksums !== null) {
            $results = [
                'changed' => [],
                'missing' => [],
            ];

            // calculate & compare checksums
            foreach ($fileChecksums as $relativePath => $declaredChecksums) {
                $absolutePath = $extensionAbsolutePath . $relativePath;

                if ($this->filesystem->isFile($absolutePath)) {
                    $localChecksum = $this->filesystem->hash($absolutePath, self::CHECKSUMS_ALGORITHM);

                    if (!in_array($localChecksum, $declaredChecksums, true)) {
                        $results['changed'][] = $relativePath;
                    }
                } else {
                    $results['missing'][] = $relativePath;
                }
            }

            return $results;
        } else {
            return null;
        }
    }
}
