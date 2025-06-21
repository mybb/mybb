<?php

declare(strict_types=1);

namespace MyBB\Cargo;

use MyBB\Utilities\FileStamp;

/**
 * @method string getHierarchicalIdentifier()
 * @method array getSharedProperties()
 * @method mixed getSharedProperty(string $key)
 * @method void setSharedProperty(string $key, mixed $value)
 * @method array getEntityProperties(?string $key = null)
 * @method void setEntityProperties(string $key, array $data)
 * @method bool declaredInherited()
 * @method bool entityDeclaredInherited(string $key)
 * @method array getEntitiesDeclaredDisinherited()
 * @method array getStamp()
 * @method bool stampValid(FileStamp $stamp)
 */
interface RepositoryInterface {}
