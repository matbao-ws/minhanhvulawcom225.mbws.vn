<?php

namespace App\Support;

/**
 * Token helpers shared by the static checks of PHP source: `extension:check`
 * (Extensions\ExtensionChecker) and the architecture tests. Works on the output
 * of token_get_all(); whitespace and comments are skipped.
 */
final class PhpTokens
{
    private const NAME_TOKENS = [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING];

    /** Index of the next meaningful token after $i. */
    public static function next(array $tokens, int $i): ?int
    {
        for ($j = $i + 1, $n = count($tokens); $j < $n; $j++) {
            if (! self::ignorable($tokens[$j])) {
                return $j;
            }
        }

        return null;
    }

    /** Index of the previous meaningful token before $i. */
    public static function previous(array $tokens, int $i): ?int
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if (! self::ignorable($tokens[$j])) {
                return $j;
            }
        }

        return null;
    }

    /**
     * Reads `use A\B [as C]` starting at the T_USE token $i. Group uses (`use A\{B, C}`)
     * report the prefix only, with group = true. Returns null for anything else that
     * starts with `use`: a closure's `use (...)`, `use function`, `use const`.
     *
     * @return array{name: string, alias: string, group: bool, end: int}|null
     */
    public static function readUse(array $tokens, int $i): ?array
    {
        $j = self::next($tokens, $i);
        if ($j === null || ! in_array($tokens[$j][0] ?? null, self::NAME_TOKENS, true)) {
            return null;
        }
        $name = ltrim($tokens[$j][1], '\\');
        $k = self::next($tokens, $j);
        if ($k !== null && ($tokens[$k] === '{' || ($tokens[$k][0] ?? null) === T_NS_SEPARATOR)) {
            return ['name' => $name, 'alias' => '', 'group' => true, 'end' => $j];
        }
        $alias = substr($name, (int) strrpos('\\'.$name, '\\'));
        if ($k !== null && ($tokens[$k][0] ?? null) === T_AS) {
            $a = self::next($tokens, $k);
            $alias = $a !== null && is_array($tokens[$a]) ? $tokens[$a][1] : $alias;
        }

        return ['name' => $name, 'alias' => $alias, 'group' => false, 'end' => $j];
    }

    /**
     * The fully qualified class a name token refers to, given the file's namespace and its
     * imports (lowercased alias => class). `self`/`static` resolve to $currentClass.
     *
     * @param  array<string, string>  $imports
     */
    public static function resolve(array $token, string $namespace, array $imports, ?string $currentClass = null): ?string
    {
        [$type, $text] = $token;

        if ($type === T_NAME_FULLY_QUALIFIED) {
            return ltrim($text, '\\');
        }
        if ($type === T_NAME_QUALIFIED) {
            $head = strtolower(explode('\\', $text)[0]);

            return isset($imports[$head])
                ? $imports[$head].substr($text, strlen($head))
                : self::qualify($namespace, $text);
        }
        if ($type === T_STRING) {
            $lower = strtolower($text);
            if ($lower === 'self' || $lower === 'static') {
                return $currentClass;
            }
            if ($lower === 'parent') {
                return null;
            }

            return $imports[$lower] ?? self::qualify($namespace, $text);
        }

        return null;
    }

    private static function qualify(string $namespace, string $name): string
    {
        return $namespace === '' ? $name : $namespace.'\\'.$name;
    }

    private static function ignorable(mixed $token): bool
    {
        return is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }
}
