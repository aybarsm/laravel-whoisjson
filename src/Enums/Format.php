<?php

declare(strict_types=1);

namespace Aybarsm\Laravel\WhoisJson\Enums;

/**
 * Response formats accepted by the `format` query parameter.
 */
enum Format: string
{
    case Json = 'json';
    case Xml = 'xml';

    public function accepts(): string
    {
        return match ($this) {
            self::Json => 'application/json',
            self::Xml => 'application/xml',
        };
    }
}
