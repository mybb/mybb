# Upgrades

New MyBB versions use different source code. For some updates, overwriting the modified files is sufficient. Others include an **upgrade file** to apply database changes and convert existing data.

Upgrade files are identified by a number incremented with each released upgrade script, and correspond to released stable versions of MyBB.

The numbers of all existing upgrade files known during the installation of MyBB, and subsequently applied upgrades, are saved in the `version_history` datacache.

Upgrade numbers are generally integers (`[0-9]+`).

Legacy MyBB series maintained in parallel may alternatively use the `[0-9]+p[0-9]+` _patch_ pattern, allowing upgrades within the old series without upgrading to newer series. These will be skipped when a suitable integer-only candidate upgrade file is also present.

## Upgrade Files

Individual upgrades are stored in:
- in MyBB ≤ 1.8: `install/resources/`
- in MyBB ≥ 1.9: `inc/upgrades/`

as `upgrade*.php` files, where `*` is the upgrade number.

The files contain:
- #### Source Version(s)
  MyBB versions to which the upgrade applies (i.e. the last version with its own upgrade file, and overwrite-only packages that followed).

  For example:
  - upgrade file included with MyBB 1.8.23:
    ```php
    /**
     * Upgrade Script: 1.8.22
     */
    ```

  - upgrade file included with MyBB 1.8.37:
    ```php
    /**
     * Upgrade Script: 1.8.34, 1.8.35 or 1.8.36
     */
    ```

- #### Data Array
  A global array `$upgrade_detail` with one or more of the following options:

  ```php
  $upgrade_detail = array(
      "revert_all_templates" => 0,
      "revert_all_themes" => 0,
      "revert_all_settings" => 0
  );
  ```

  - `revert_all_settings`

    If set to `1`, setting values stored in the database will be overwritten with those stored in the cached settings file.

    Used in old upgrade files that modified setting tables.

    In MyBB ≤ 1.8, a value `2` was also supported to additionally re-create all tables before restoring values from the cached settings file.

- #### Upgrade Functions
  Custom code is divided into functions named in the `upgrade*_*` format, identifying the upgrade number, and an arbitrary name.
  ```php
  function upgrade50_dbchanges()
  {
      // ...
  }
  ```

  In MyBB 1.9 and newer, the functions should not produce any output.

## Detection
When a new MyBB package is uploaded, and:
- the codebase version (`MyBB::$version_code`) is higher than the version stored in the `version` datacache, and
- the next applicable upgrade file (identified by the last upgrade number in the `version_history` datacache, incremented) is detected,

the application terminates, and directs the user to the upgrade script.
