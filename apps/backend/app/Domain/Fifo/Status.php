<?php

declare(strict_types=1);

namespace App\Domain\Fifo;

enum Status: string
{
    case OPEN = 'open';
    case APPROACHING_CUTOFF = 'approaching_cutoff';
    case CUTOFF_DECLARED = 'cutoff_declared';
    case CLOSED = 'closed';
}
