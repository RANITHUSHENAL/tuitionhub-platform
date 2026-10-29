<?php

namespace App\Support;

/**
 * Search text for SQL LIKE, matched literally: the wildcards % and _ and the escape character are
 * escaped, so user input can never widen a search.
 */
final class LikePattern
{
    public static function contains(string $value): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%';
    }
}
