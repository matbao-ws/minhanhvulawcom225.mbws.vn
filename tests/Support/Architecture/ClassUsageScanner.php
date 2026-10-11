<?php

namespace Tests\Support\Architecture;

use App\Support\PhpTokens;

/**
 * Reads one PHP file and reports which classes it reaches and from where, for the
 * architecture tests. Names resolve through the file's namespace and imports, aliases
 * included, so `use App\Models\Order as Purchase; Purchase::create()` is a call on
 * App\Models\Order.
 *
 * Static only: a class reached through a variable ($class::create(), app($name)) is not
 * seen. The tests guard against mistakes, not against code written to dodge them.
 */
final class ClassUsageScanner
{
    private const NAME_TOKENS = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

    private const DECLARATIONS = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    /**
     * @return array{
     *     calls: list<array{class: string, kind: 'static'|'new', method: ?string, chain: list<string>, line: int, scope: string}>,
     *     references: list<array{class: string, line: int, scope: string}>
     * }
     */
    public function scan(string $code): array
    {
        $tokens = token_get_all($code);
        $count = count($tokens);

        $namespace = '';
        $imports = [];
        $calls = [];
        $references = [];

        $depth = 0;
        $classes = [];   // stack of [name, depth its body opened at]
        $functions = []; // stack of [name, depth its body opened at]
        $pendingClass = null;
        $pendingFunction = null;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token)) {
                if ($token === '{') {
                    $depth++;
                    if ($pendingClass !== null) {
                        $classes[] = [$pendingClass, $depth];
                        $pendingClass = null;
                    } elseif ($pendingFunction !== null) {
                        $functions[] = [$pendingFunction, $depth];
                        $pendingFunction = null;
                    }
                } elseif ($token === '}') {
                    if ($functions !== [] && end($functions)[1] === $depth) {
                        array_pop($functions);
                    } elseif ($classes !== [] && end($classes)[1] === $depth) {
                        array_pop($classes);
                    }
                    $depth--;
                } elseif ($token === ';') {
                    $pendingFunction = null; // abstract or interface method: no body
                }

                continue;
            }

            [$type] = $token;

            if ($type === T_CURLY_OPEN || $type === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++; // "{$x}" / "${x}" inside a string, closed by a plain '}'

                continue;
            }

            if ($type === T_NAMESPACE) {
                $j = PhpTokens::next($tokens, $i);
                if ($j !== null && in_array($tokens[$j][0] ?? null, [T_STRING, T_NAME_QUALIFIED], true)) {
                    $namespace = $tokens[$j][1];
                    $imports = [];
                    $i = $j;
                }

                continue;
            }

            $currentClass = $classes !== [] ? end($classes)[0] : null;
            $scope = $this->scope($currentClass, $functions);

            // File-level imports only; inside a class body `use` imports a trait.
            if ($type === T_USE && $classes === [] && $functions === []) {
                $use = PhpTokens::readUse($tokens, $i);
                if ($use !== null && ! $use['group']) {
                    $imports[strtolower($use['alias'])] = $use['name'];
                    $references[] = ['class' => $use['name'], 'line' => $token[2], 'scope' => $scope];
                    $i = $use['end'];
                }

                continue;
            }

            if (in_array($type, self::DECLARATIONS, true)) {
                $before = PhpTokens::previous($tokens, $i);
                $beforeType = $before !== null ? ($tokens[$before][0] ?? null) : null;
                $j = PhpTokens::next($tokens, $i);
                if ($beforeType !== T_DOUBLE_COLON && $beforeType !== T_NEW && $j !== null && ($tokens[$j][0] ?? null) === T_STRING) {
                    $pendingClass = $namespace === '' ? $tokens[$j][1] : $namespace.'\\'.$tokens[$j][1];
                    $i = $j;
                }

                continue;
            }

            if ($type === T_FUNCTION) {
                $j = PhpTokens::next($tokens, $i);
                if ($j !== null && $tokens[$j] === '&') {
                    $j = PhpTokens::next($tokens, $j);
                }
                if ($j !== null && ($tokens[$j][0] ?? null) === T_STRING) {
                    $pendingFunction = $tokens[$j][1];
                    $i = $j;
                }

                continue;
            }

            if ($type === T_NEW) {
                $j = PhpTokens::next($tokens, $i);
                if ($j !== null && in_array($tokens[$j][0] ?? null, self::NAME_TOKENS, true)) {
                    $class = PhpTokens::resolve($tokens[$j], $namespace, $imports, $currentClass);
                    if ($class !== null) {
                        $calls[] = ['class' => $class, 'kind' => 'new', 'method' => null, 'chain' => [], 'line' => $token[2], 'scope' => $scope];
                        $references[] = ['class' => $class, 'line' => $token[2], 'scope' => $scope];
                    }
                    $i = $j;
                }

                continue;
            }

            if ($type === T_CONSTANT_ENCAPSED_STRING) {
                $value = ltrim($this->unquote($token[1]), '\\');
                if (preg_match('/^App\\\\[A-Za-z0-9_\\\\]+$/', $value)) {
                    $references[] = ['class' => $value, 'line' => $token[2], 'scope' => $scope];
                }

                continue;
            }

            if (! in_array($type, self::NAME_TOKENS, true)) {
                continue;
            }

            $colon = PhpTokens::next($tokens, $i);
            $isStatic = $colon !== null && ($tokens[$colon][0] ?? null) === T_DOUBLE_COLON;

            if ($type === T_STRING && ! $isStatic) {
                continue; // a bare word: function call, constant, type hint of an imported class
            }

            $class = PhpTokens::resolve($token, $namespace, $imports, $currentClass);
            if ($class === null) {
                continue;
            }
            $references[] = ['class' => $class, 'line' => $token[2], 'scope' => $scope];

            if (! $isStatic) {
                continue;
            }
            $member = PhpTokens::next($tokens, $colon);
            $open = $member !== null ? PhpTokens::next($tokens, $member) : null;
            if ($member === null || ($tokens[$member][0] ?? null) !== T_STRING || $open === null || $tokens[$open] !== '(') {
                continue; // X::class, X::CONSTANT, X::$property
            }

            $calls[] = [
                'class' => $class,
                'kind' => 'static',
                'method' => $tokens[$member][1],
                'chain' => $this->chain($tokens, $open),
                'line' => $token[2],
                'scope' => $scope,
            ];
            $i = $member;
        }

        return ['calls' => $calls, 'references' => $references];
    }

    /** `Class::method` while inside a method, `Class` inside a class body, '' at file level. */
    private function scope(?string $class, array $functions): string
    {
        $function = $functions !== [] ? $functions[0][0] : null;

        return match (true) {
            $class !== null && $function !== null => $class.'::'.$function,
            $class !== null => $class,
            $function !== null => $function,
            default => '',
        };
    }

    /**
     * Method names called on the result, from the '(' that opens the static call's arguments:
     * `X::query()->lockForUpdate()->create()` gives ['lockForUpdate', 'create'].
     *
     * @return list<string>
     */
    private function chain(array $tokens, int $open): array
    {
        $chain = [];
        $close = $this->closing($tokens, $open);

        while ($close !== null) {
            $arrow = PhpTokens::next($tokens, $close);
            if ($arrow === null || ! in_array($tokens[$arrow][0] ?? null, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                break;
            }
            $name = PhpTokens::next($tokens, $arrow);
            $paren = $name !== null ? PhpTokens::next($tokens, $name) : null;
            if ($name === null || ($tokens[$name][0] ?? null) !== T_STRING || $paren === null || $tokens[$paren] !== '(') {
                break; // a property read ends the call chain
            }
            $chain[] = $tokens[$name][1];
            $close = $this->closing($tokens, $paren);
        }

        return $chain;
    }

    /** A PHP string literal's value: 'App\X' keeps its single backslashes, "App\\X" loses the doubling. */
    private function unquote(string $literal): string
    {
        $inner = substr($literal, 1, -1);

        return $literal[0] === "'"
            ? str_replace(['\\\\', "\\'"], ['\\', "'"], $inner)
            : str_replace(['\\\\', '\\"'], ['\\', '"'], $inner);
    }

    /** Index of the ')' that closes the '(' at $open. */
    private function closing(array $tokens, int $open): ?int
    {
        $level = 0;
        for ($i = $open, $n = count($tokens); $i < $n; $i++) {
            if ($tokens[$i] === '(') {
                $level++;
            } elseif ($tokens[$i] === ')' && --$level === 0) {
                return $i;
            }
        }

        return null;
    }
}
