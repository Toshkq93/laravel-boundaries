<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Support;

enum AutoloadStatus
{
    /** The PSR-4 entry was added to composer.json. */
    case Added;

    /** An existing PSR-4 entry already loads the namespace from the directory. */
    case AlreadyLoaded;

    /** composer.json maps the namespace to another directory. */
    case Conflict;

    /** The directory is outside the project of composer.json. */
    case OutsideProject;

    /** There is no composer.json. */
    case NoComposerJson;
}
