<?php

namespace Melasistema\MelaCms\Install;

enum StubStatus
{
    /** The file is the core template. */
    case Current;

    /** The file differs or is missing, and the site chose that for this version of the template. */
    case Kept;

    /** No file, and no choice about this version of the template. */
    case Missing;

    /** The file differs, and the template changed since the site took or kept it (or nothing is recorded). */
    case Changed;
}
