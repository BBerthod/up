<?php

namespace App\Enums;

enum IncidentCause: string
{
    case TIMEOUT = 'timeout';
    case STATUS_CODE = 'status_code';
    case KEYWORD = 'keyword';
    case SSL = 'ssl';
    case ERROR = 'error';
}
