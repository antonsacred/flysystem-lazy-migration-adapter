<?php

declare(strict_types=1);

namespace Sacred\Flysystem\Tests;

use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Sacred\Flysystem\LazyMigrationAdapter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class LazyMigrationAdapterIntegrationTest extends FilesystemAdapterTestCase
{
    private static string $oldRoot = '';
    private static string $newRoot = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearStorage();
    }

    public function clearStorage(): void
    {
        // The base cleanup only uses the adapter view; explicitly wipe both roots to avoid residual directories.
        parent::clearStorage();
        $this->wipeRoot(self::$oldRoot);
        $this->wipeRoot(self::$newRoot);
    }

    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        self::$oldRoot = self::createTempDirectory('old');
        self::$newRoot = self::createTempDirectory('new');

        $oldAdapter = new LocalFilesystemAdapter(self::$oldRoot);
        $newAdapter = new LocalFilesystemAdapter(self::$newRoot);

        return new LazyMigrationAdapter($oldAdapter, $newAdapter);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::removeDirectory(self::$oldRoot);
        self::removeDirectory(self::$newRoot);
    }

    public function testReadMigratesFileFromOldAdapter(): void
    {
        $path = 'migrate/read.txt';
        $contents = 'contents-from-old';
        $this->createOldFile($path, $contents);

        $adapter = $this->adapter();
        $this->assertSame($contents, $adapter->read($path));

        $this->assertFileDoesNotExist(self::oldAbsolutePath($path));
        $this->assertFileExists(self::newAbsolutePath($path));
        $this->assertSame($contents, file_get_contents(self::newAbsolutePath($path)));
    }

    public function testFileExistsMigratesBeforeReturning(): void
    {
        $path = 'migrate/check.txt';
        $contents = 'existing-on-old';
        $this->createOldFile($path, $contents);

        $adapter = $this->adapter();
        $this->assertTrue($adapter->fileExists($path));

        $this->assertFileDoesNotExist(self::oldAbsolutePath($path));
        $this->assertFileExists(self::newAbsolutePath($path));
        $this->assertSame($contents, file_get_contents(self::newAbsolutePath($path)));
    }

    public function testReadStreamMigratesAndKeepsContents(): void
    {
        $path = 'migrate/stream.txt';
        $contents = 'streamed-from-old';
        $this->createOldFile($path, $contents);

        $stream = $this->adapter()->readStream($path);
        $this->assertIsResource($stream);
        $this->assertSame($contents, stream_get_contents($stream) ?: '');
        fclose($stream);

        $this->assertFileDoesNotExist(self::oldAbsolutePath($path));
        $this->assertSame($contents, file_get_contents(self::newAbsolutePath($path)));
    }

    public function testListContentsMergesOldAndNewWithoutDuplicates(): void
    {
        $this->createOldFile('merged/only-old.txt', 'old');
        $this->createOldFile('merged/both.txt', 'old-version');
        $this->createNewFile('merged/only-new.txt', 'new');
        $this->createNewFile('merged/both.txt', 'new-version');

        $items = iterator_to_array($this->adapter()->listContents('merged', false));
        $paths = array_map(static fn($item) => $item->path(), $items);
        sort($paths);

        $this->assertSame(
            ['merged/both.txt', 'merged/only-new.txt', 'merged/only-old.txt'],
            $paths
        );
    }

    public function testListContentsDeepIncludesFilesFromOldAndNew(): void
    {
        $this->createOldFile('old-only/file.txt', 'old');
        $this->createNewFile('new-only/file.txt', 'new');

        $items = iterator_to_array($this->adapter()->listContents('', true));
        $paths = array_map(static fn($item) => $item->path(), $items);
        sort($paths);

        $this->assertSame(
            [
                'new-only',
                'new-only/file.txt',
                'old-only',
                'old-only/file.txt',
            ],
            $paths
        );
    }

    private static function createTempDirectory(string $suffix): string
    {
        $path = sys_get_temp_dir() . '/lazy-migration-' . $suffix . '-' . uniqid('', true);
        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Unable to create temp directory at %s', $path));
        }

        return $path;
    }

    private function createOldFile(string $relativePath, string $contents): void
    {
        $absolutePath = self::oldAbsolutePath($relativePath);
        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create directory at %s', $directory));
        }

        file_put_contents($absolutePath, $contents);
    }

    private function createNewFile(string $relativePath, string $contents): void
    {
        $adapter = new LocalFilesystemAdapter(self::$newRoot);
        $adapter->write($relativePath, $contents, new Config());
    }

    private static function oldAbsolutePath(string $relativePath): string
    {
        return self::$oldRoot . DIRECTORY_SEPARATOR . $relativePath;
    }

    private static function newAbsolutePath(string $relativePath): string
    {
        return self::$newRoot . DIRECTORY_SEPARATOR . $relativePath;
    }

    private static function removeDirectory(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }

    private function wipeRoot(string $root): void
    {
        if ($root === '' || !is_dir($root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
    }
}
