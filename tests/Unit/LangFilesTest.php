<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A key written twice at the same level of a lang array is silently overwritten by the second:
 * a whole group of texts can vanish (ai.draft once lost every "Viết bằng AI" string this way).
 */
class LangFilesTest extends TestCase
{
    public function test_no_lang_file_repeats_a_key_at_the_same_level(): void
    {
        $files = glob(dirname(__DIR__, 2).'/lang/*/*.php') ?: [];
        $this->assertNotEmpty($files);

        $duplicates = [];
        foreach ($files as $file) {
            foreach ($this->duplicateKeys((string) file_get_contents($file)) as $key) {
                $duplicates[] = basename(dirname($file)).'/'.basename($file).': '.$key;
            }
        }

        $this->assertSame([], $duplicates);
    }

    /** @return list<string> keys repeated within one array, as their nesting path (a.b.c) */
    private function duplicateKeys(string $php): array
    {
        $levels = [[]];     // keys seen per open array
        $path = [];         // the key each open array hangs on
        $pending = null;    // a string literal that may turn out to be a key
        $lastKey = null;    // the key whose value comes next
        $duplicates = [];

        foreach (token_get_all($php) as $token) {
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $pending = stripslashes(substr($token[1], 1, -1));

                continue;
            }
            if (is_array($token) && $token[0] === T_DOUBLE_ARROW && $pending !== null) {
                $depth = count($levels) - 1;
                if (isset($levels[$depth][$pending])) {
                    $duplicates[] = implode('.', array_filter([...$path, $pending], fn ($part) => $part !== ''));
                }
                $levels[$depth][$pending] = true;
                $lastKey = $pending;
                $pending = null;

                continue;
            }
            if ($token === '[') {
                $levels[] = [];
                $path[] = $lastKey ?? '';
                $lastKey = null;
            } elseif ($token === ']') {
                array_pop($levels);
                array_pop($path);
            }
            $pending = null;
        }

        return $duplicates;
    }
}
