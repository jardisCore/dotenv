<?php

declare(strict_types=1);

namespace JardisCore\DotEnv\Tests\unit\Reader;

use JardisCore\DotEnv\Casting\CastTypeHandler;
use JardisCore\DotEnv\Exception\CircularEnvIncludeException;
use JardisCore\DotEnv\Exception\EnvFileNotFoundException;
use JardisCore\DotEnv\Reader\LoadValuesFromFiles;
use PHPUnit\Framework\TestCase;

class LoadValuesFromFilesIncludeTest extends TestCase
{
    private CastTypeHandler $castTypeHandler;
    private LoadValuesFromFiles $loader;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->castTypeHandler = new CastTypeHandler();
        $this->loader = new LoadValuesFromFiles($this->castTypeHandler);
        $this->fixturesPath = dirname(__DIR__, 2) . '/fixtures/include';
    }

    public function testBasicIncludeLoadsFile(): void
    {
        $files = [$this->fixturesPath . '/.env'];
        $result = ($this->loader)($files, false);

        // Variables from main file
        $this->assertArrayHasKey('APP_NAME', $result);
        $this->assertEquals('TestApp', $result['APP_NAME']);

        // Variables from included .env.database
        $this->assertArrayHasKey('DB_HOST', $result);
        $this->assertEquals('localhost', $result['DB_HOST']);

        // Variables from included .env.logger
        $this->assertArrayHasKey('LOG_LEVEL', $result);
        $this->assertEquals('debug', $result['LOG_LEVEL']);
    }

    public function testOptionalIncludeLoadsExistingFile(): void
    {
        $files = [$this->fixturesPath . '/.env'];
        $result = ($this->loader)($files, false);

        // Variable from optional .env.optional that exists
        $this->assertArrayHasKey('OPTIONAL_VAR', $result);
        $this->assertEquals('exists', $result['OPTIONAL_VAR']);
    }

    public function testOptionalIncludeSkipsMissingFile(): void
    {
        $files = [$this->fixturesPath . '/.env'];
        $result = ($this->loader)($files, false);

        // Should complete without error even though .env.nonexistent doesn't exist
        $this->assertArrayHasKey('APP_NAME', $result);
    }

    public function testRequiredIncludeThrowsExceptionForMissingFile(): void
    {
        // Create a temporary file with a required include for a non-existent file
        $tempFile = $this->fixturesPath . '/.env.temp-required';
        file_put_contents($tempFile, "TEST_VAR=test\nload(.env.does-not-exist)");

        try {
            $this->expectException(EnvFileNotFoundException::class);
            ($this->loader)([$tempFile], false);
        } finally {
            unlink($tempFile);
        }
    }

    public function testOverrideBehavior(): void
    {
        $files = [$this->fixturesPath . '/.env.override-test'];
        $result = ($this->loader)($files, false);

        // The included file should override the original value
        $this->assertEquals('overridden', $result['OVERRIDE_VAR']);

        // Variable defined after include should be present
        $this->assertEquals('after', $result['AFTER_INCLUDE']);

        // Variable from included file
        $this->assertArrayHasKey('INCLUDED_VAR', $result);
    }

    public function testNestedIncludes(): void
    {
        $files = [$this->fixturesPath . '/.env.chain-a'];
        $result = ($this->loader)($files, false);

        // Should have variables from A -> B -> C chain
        $this->assertEquals('valueA', $result['CHAIN_A']);
        $this->assertEquals('valueB', $result['CHAIN_B']);
        $this->assertEquals('valueC', $result['CHAIN_C']);
    }

    public function testCircularReferenceDetectionDirect(): void
    {
        $files = [$this->fixturesPath . '/.env.self-circular'];

        $this->expectException(CircularEnvIncludeException::class);
        ($this->loader)($files, false);
    }

    public function testCircularReferenceDetectionIndirect(): void
    {
        $files = [$this->fixturesPath . '/.env.circular-a'];

        $this->expectException(CircularEnvIncludeException::class);
        ($this->loader)($files, false);
    }

    public function testRelativePathResolution(): void
    {
        $files = [$this->fixturesPath . '/.env.nested-loader'];
        $result = ($this->loader)($files, false);

        // Variable from main file
        $this->assertEquals('loader', $result['LOADER_VAR']);

        // Variable from nested/.env.nested
        $this->assertEquals('nested_value', $result['NESTED_VAR']);
    }

    public function testMultipleIncludes(): void
    {
        $files = [$this->fixturesPath . '/.env'];
        $result = ($this->loader)($files, false);

        // Variables from multiple includes should all be present
        $this->assertArrayHasKey('DB_HOST', $result);
        $this->assertArrayHasKey('LOG_LEVEL', $result);
        $this->assertArrayHasKey('OPTIONAL_VAR', $result);
    }

    public function testMixedContentWithIncludes(): void
    {
        $files = [$this->fixturesPath . '/.env'];
        $result = ($this->loader)($files, false);

        // Variables before includes
        $this->assertEquals('TestApp', $result['APP_NAME']);

        // Variables after includes
        $this->assertEquals(true, $result['APP_DEBUG']);
    }

    public function testPublicModeWithIncludes(): void
    {
        // Clear environment first
        putenv('DB_HOST');
        unset($_ENV['DB_HOST'], $_SERVER['DB_HOST']);

        $files = [$this->fixturesPath . '/.env'];
        ($this->loader)($files, true);

        // Check that included values are in environment
        $this->assertEquals('localhost', getenv('DB_HOST'));
        $this->assertEquals('localhost', $_ENV['DB_HOST']);
    }

    public function testExceptionContainsIncludeStack(): void
    {
        $files = [$this->fixturesPath . '/.env.circular-a'];

        try {
            ($this->loader)($files, false);
            $this->fail('Expected CircularEnvIncludeException was not thrown');
        } catch (CircularEnvIncludeException $e) {
            $stack = $e->getIncludeStack();
            $this->assertNotEmpty($stack);
            $this->assertStringContainsString('Circular include detected', $e->getMessage());
        }
    }
}
