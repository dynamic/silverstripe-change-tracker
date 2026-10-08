<?php

namespace Symbiote\DataChange\Tests\Characterization;

use PHPUnit\Framework\Assert;

/**
 * Compares a value with a golden file recorded from the module as it was before it was changed.
 *
 * Golden files are written only when CHANGETRACKER_UPDATE_GOLDEN=1 is set in the environment. The switch is refused
 * on a continuous integration run, so a pipeline can never rewrite the expected output.
 */
class GoldenFile
{
    public const UPDATE_VARIABLE = 'CHANGETRACKER_UPDATE_GOLDEN';

    /**
     * @param string $name file name without the extension, within the golden directory
     * @param mixed $actual anything json_encode() can represent
     */
    public static function assertMatches(string $name, $actual): void
    {
        $path = self::path($name);
        $encoded = self::encode($actual);

        if (self::updating()) {
            if (self::onCI()) {
                Assert::fail(self::UPDATE_VARIABLE . ' must not be set on a CI run.');
            }
            file_put_contents($path, $encoded);
            Assert::assertFileExists($path);
            return;
        }

        Assert::assertFileExists(
            $path,
            "Missing golden file $name. Record it from the unmodified code with " . self::UPDATE_VARIABLE . '=1.'
        );
        Assert::assertSame(
            file_get_contents($path),
            $encoded,
            "Output differs from the golden file $name"
        );
    }

    public static function path(string $name): string
    {
        return __DIR__ . '/golden/' . $name . '.json';
    }

    /**
     * Stable text for a value: associative keys are sorted, lists keep their order
     *
     * @param mixed $value
     * @return string
     */
    public static function encode($value): string
    {
        return json_encode(
            self::canonical($value),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . "\n";
    }

    private static function canonical($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        $isList = array_keys($value) === range(0, count($value) - 1);
        $value = array_map([self::class, 'canonical'], $value);
        if (!$isList) {
            ksort($value);
        }
        return $value;
    }

    private static function updating(): bool
    {
        return (string)getenv(self::UPDATE_VARIABLE) === '1';
    }

    private static function onCI(): bool
    {
        return (bool)getenv('CI') || (bool)getenv('GITHUB_ACTIONS');
    }
}
