<?php

namespace App\Http\Traits;

/**
 * Collation-safe SQL expression helper for MySQL.
 *
 * Some FIMS legacy tables on `mysql_secondary` use `utf8mb3` while
 * newer ones use `utf8mb4` (MySQL 8 default). Cross-table string
 * comparisons (`ON`, `=`, `LIKE`, `CONCAT_WS`) blow up with either:
 *   - SQLSTATE[HY000] 1267 — "Illegal mix of collations" (utf8mb4
 *     vs utf8mb4_0900_ai_ci), OR
 *   - SQLSTATE[42000] 1253 — "COLLATION 'utf8mb4_unicode_ci' is not
 *     valid for CHARACTER SET 'utf8mb3'".
 *
 * Wrapping every text expression with
 *   CONVERT(<expr> USING utf8mb4) COLLATE utf8mb4_unicode_ci
 * converts utf8mb3 -> utf8mb4 up-front (and is a no-op for already
 * utf8mb4 columns), so a single consistent collation drives every
 * predicate.
 */
trait CollationSafeSql
{
    /**
     * Wrap a SQL expression so it can safely participate in
     * cross-charset comparisons on mysql_secondary.
     *
     * @param  string  $expr  Raw SQL expression (column ref or
     *                        computed value). NEVER pass user input
     *                        here — use bindings.
     */
    protected function cs(string $expr): string
    {
        return "CONVERT($expr USING utf8mb4) COLLATE utf8mb4_unicode_ci";
    }
}
