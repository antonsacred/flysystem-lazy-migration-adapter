<?php

declare(strict_types=1);

namespace Sacred\Flysystem;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;

/**
 * Flysystem adapter that migrates files from an old adapter to a new adapter on first access.
 */
final class LazyMigrationAdapter implements FilesystemAdapter
{
    private FilesystemAdapter $oldAdapter;
    private FilesystemAdapter $newAdapter;

    public function __construct(FilesystemAdapter $oldAdapter, FilesystemAdapter $newAdapter)
    {
        $this->oldAdapter = $oldAdapter;
        $this->newAdapter = $newAdapter;
    }

    private function migrateFileFromOldIfPresent(string $path): void
    {
        if (!$this->oldAdapter->fileExists($path)) {
            return;
        }

        if ($this->newAdapter->fileExists($path)) {
            $this->oldAdapter->delete($path);
            return;
        }

        $stream = $this->oldAdapter->readStream($path);
        try {
            $this->newAdapter->writeStream($path, $stream, new Config());
        } finally {
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->oldAdapter->delete($path);
    }

    public function fileExists(string $path): bool
    {
        $this->migrateFileFromOldIfPresent($path);

        return $this->newAdapter->fileExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->migrateFileFromOldIfPresent($path);
        $this->newAdapter->write($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->migrateFileFromOldIfPresent($path);
        $this->newAdapter->writeStream($path, $contents, $config);
    }

    public function directoryExists(string $path): bool
    {
        if ($this->oldAdapter->directoryExists($path)) {
            if (!$this->newAdapter->directoryExists($path)) {
                $this->newAdapter->createDirectory($path, new Config());
            }
            $this->oldAdapter->deleteDirectory($path);

            return true;
        }

        return $this->newAdapter->directoryExists($path);
    }

    public function read(string $path): string
    {
        $this->migrateFileFromOldIfPresent($path);

        return $this->newAdapter->read($path);
    }

    public function readStream(string $path)
    {
        $this->migrateFileFromOldIfPresent($path);

        return $this->newAdapter->readStream($path);
    }

    public function delete(string $path): void
    {
        if ($this->oldAdapter->fileExists($path)) {
            $this->oldAdapter->delete($path);
        }

        $this->newAdapter->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        if ($this->oldAdapter->fileExists($path)) {
            $this->oldAdapter->deleteDirectory($path);
        }

        $this->newAdapter->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->newAdapter->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->migrateFileFromOldIfPresent($path);
        $this->newAdapter->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        $this->migrateFileFromOldIfPresent($path);

        return $this->newAdapter->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        $this->migrateFileFromOldIfPresent($path);

        return $this->newAdapter->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        $this->migrateFileFromOldIfPresent($path);

        return $this->newAdapter->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        $this->migrateFileFromOldIfPresent($path);

        return $this->newAdapter->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $newPaths = [];

        foreach ($this->oldAdapter->listContents($path, $deep) as $item) {
            $newPaths[$item->path()] = true;
            yield $item;
        }

        foreach ($this->newAdapter->listContents($path, $deep) as $item) {
            if (!isset($newPaths[$item->path()])) {
                yield $item;
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->migrateFileFromOldIfPresent($source);
        $this->migrateFileFromOldIfPresent($destination);
        $this->newAdapter->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->migrateFileFromOldIfPresent($source);
        $this->migrateFileFromOldIfPresent($destination);
        $this->newAdapter->copy($source, $destination, $config);
    }
}
