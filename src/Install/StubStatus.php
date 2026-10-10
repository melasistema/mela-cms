<?php

namespace Melasistema\MelaCms\Install;

enum StubStatus
{
    case Missing;
    case Current;
    case Differs;
}
