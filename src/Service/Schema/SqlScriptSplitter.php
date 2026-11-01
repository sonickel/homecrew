<?php

declare(strict_types=1);

namespace App\Service\Schema;

/**
 * Splits a plain SQL script (like docs/datenbank/schema.sql) into single statements.
 *
 * Knows quoted strings and identifiers ('…', "…", `…`) and comments (-- …, # …, /* … *\/),
 * so a ";" or "#" inside a string does not end a statement. Not supported (rejected or not recognised):
 * DELIMITER blocks and executable comments (/*! … *\/, /*M! … *\/) as written by mysqldump.
 */
final class SqlScriptSplitter
{
    /**
     * @return list<string>
     */
    public function split(string $script): array
    {
        $statements = [];
        $current = '';
        $length = \strlen($script);

        for ($i = 0; $i < $length; ++$i) {
            $char = $script[$i];
            $next = $script[$i + 1] ?? '';

            if ("'" === $char || '"' === $char || '`' === $char) {
                $end = $this->findClosingQuote($script, $i, $char);
                $current .= substr($script, $i, $end - $i + 1);
                $i = $end;
            } elseif (('-' === $char && '-' === $next && \in_array($script[$i + 2] ?? "\n", [' ', "\t", "\n", "\r"], true)) || '#' === $char) {
                $lineEnd = strpos($script, "\n", $i);
                $i = false === $lineEnd ? $length : $lineEnd - 1;
            } elseif ('/' === $char && '*' === $next) {
                if ('!' === ($script[$i + 2] ?? '') || 'M!' === substr($script, $i + 2, 2)) {
                    throw new \InvalidArgumentException('Executable comments (/*! … */) are not supported in SQL scripts.');
                }
                $commentEnd = strpos($script, '*/', $i + 2);
                if (false === $commentEnd) {
                    throw new \InvalidArgumentException('Unclosed block comment in SQL script.');
                }
                $current .= ' '; // keeps "SELECT/*x*/1" from becoming "SELECT1"
                $i = $commentEnd + 1;
            } elseif (';' === $char) {
                $statements[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        $statements[] = $current;

        return array_values(array_filter(array_map(trim(...), $statements), static fn (string $sql): bool => '' !== $sql));
    }

    private function findClosingQuote(string $script, int $start, string $quote): int
    {
        $length = \strlen($script);
        for ($i = $start + 1; $i < $length; ++$i) {
            if ('\\' === $script[$i] && '`' !== $quote) {
                ++$i;
            } elseif ($script[$i] === $quote) {
                if (($script[$i + 1] ?? '') !== $quote) {
                    return $i;
                }
                ++$i; // doubled quote inside the string
            }
        }

        throw new \InvalidArgumentException(\sprintf('Unclosed %s in SQL script.', $quote));
    }
}
