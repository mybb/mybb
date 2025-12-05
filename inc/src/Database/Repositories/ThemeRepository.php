<?php

declare(strict_types=1);

namespace MyBB\Database\Repositories;

use datacache;
use DB_Base;
use Exception;
use InvalidArgumentException;
use MyBB\Database\Models\Theme;
use MyBB\Extensions\Theme\Repository as ThemeExtensionRepository;

use const MyBB\View\DEFAULT_THEME_PACKAGE;

readonly class ThemeRepository
{
    public const TABLE = 'themes';

    public function __construct(
        private DB_Base $db,
        private datacache $datacache,
        private ThemeExtensionRepository $themeExtensionRepository,
    ) {}

    /**
     * Returns a model instance from the given stored representation.
     */
    public function fromArray(array $data): Theme
    {
        return new Theme(
            $data['tid'] ?? throw new InvalidArgumentException(),
            $this->themeExtensionRepository->getExisting(
                $data['package'] ?? throw new InvalidArgumentException(),
            ),
            $data['name'] ?? throw new InvalidArgumentException(),
            $data['properties'] ?? throw new InvalidArgumentException(),
            $data['stylesheets'] ?? throw new InvalidArgumentException(),
            $data['allowedgroups'] ?? throw new InvalidArgumentException(),
        );
    }

    public function find(int $id): ?Theme
    {
        $row = $this->db->simple_select(
            self::TABLE,
            'tid, package, name, properties, stylesheets, allowedgroups',
            'tid = ' . (int)$id,
        );

        if ($this->db->num_rows($row) === 0) {
            return null;
        } else {
            $data = $this->db->fetch_array($row);

            return $this->fromArray($data);
        }
    }

    public function findDefault(): ?Theme
    {
        $array = $this->datacache->read('default_theme');

        if (!$array || !is_array($array)) {
            $row = $this->db->simple_select(
                self::TABLE,
                'tid, package, name, properties, stylesheets, allowedgroups',
                'def = 1',
            );

            if ($this->db->num_rows($row) !== 1) {
                return null;
            } else {
                $array = $this->db->fetch_array($row);

                $this->datacache->update(
                    'default_theme',
                    $array,
                );
            }
        }

        return $this->fromArray($array);
    }

    /**
     * @return array<int, Theme>
     */
    public function all(): array
    {
        $instances = [];

        $rows = $this->db->simple_select(self::TABLE);

        while ($row = $this->db->fetch_array($rows)) {
            $instances[$row['tid']] = $this->fromArray($row);
        }

        return $instances;
    }

    public function insert(Theme $model): int
    {
        $this->db->insert_query(
            self::TABLE,
            array_map(
                $this->db->escape_string(...),
                $this->getArray($model),
            ),
        );

        $id = (int)$this->db->insert_id();

        $model->id = $id;

        return $id;
    }

    public function update(Theme $model): void
    {
        $this->db->update_query(
            self::TABLE,
            array_map(
                $this->db->escape_string(...),
                $this->getArray($model),
            ),
            'tid = ' . (int)$model->id,
        );


        $default = $this->findDefault();

        if ($default && $default->id === $model->id) {
            $this->datacache->update(
                'default_theme',
                $this->getArray($model),
            );
        }
    }

    public function delete(Theme $model): void
    {
        $default = $this->findDefault();

        if ($default && $default->id === $model->id) {
            $this->datacache->update(
                'default_theme',
                null,
            );
        }


        $this->db->delete_query(self::TABLE, 'tid = ' . (int)$model->id);
    }

    public function setDefault(Theme $model): void
    {
        $this->db->update_query(
            self::TABLE,
            [
                'def' => 0,
            ],
        );

        $this->db->update_query(
            self::TABLE,
            [
                'def' => 1,
            ],
            'tid = ' . (int)$model->id,
        );

        $this->datacache->update(
            'default_theme',
            $this->getArray($model),
        );
    }

    public function getFallback(): Theme
    {
        return new Theme(
            null,
            package: $this->themeExtensionRepository->get(DEFAULT_THEME_PACKAGE),
            name: DEFAULT_THEME_PACKAGE,
        );
    }

    /**
     * Returns a stored representation of the given model instance.
     */
    public function getArray(Theme $model): array
    {
        return $model->toArray();
    }
}
