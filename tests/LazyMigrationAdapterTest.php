<?php

declare(strict_types=1);

namespace Antonsacred\FlysystemLazyMigrationAdapter\Tests;

use Antonsacred\FlysystemLazyMigrationAdapter\LazyMigrationAdapter;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LazyMigrationAdapterTest extends TestCase
{
    /** @var FilesystemAdapter|MockObject */
    private $oldAdapter;

    /** @var FilesystemAdapter|MockObject */
    private $newAdapter;

    private LazyMigrationAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oldAdapter = $this->createMock(FilesystemAdapter::class);
        $this->newAdapter = $this->createMock(FilesystemAdapter::class);
        $this->adapter = new LazyMigrationAdapter($this->oldAdapter, $this->newAdapter);
    }

    public function testFileExistsWhenFileNotInOldAdapter(): void
    {
        $path = 'test.txt';
        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(false);
        $this->newAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(true);

        $this->assertTrue($this->adapter->fileExists($path));
    }

    public function testFileExistsWhenFileIsMigrated(): void
    {
        $path = 'test.txt';
        $stream = fopen('php://memory', 'r');

        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(true);
        $this->newAdapter
            ->expects($this->exactly(2))->method('fileExists')->with($path)
            ->willReturnOnConsecutiveCalls(false, true);
        $this->oldAdapter->expects($this->once())->method('readStream')->with($path)->willReturn($stream);
        $this->newAdapter->expects($this->once())->method('writeStream')->with($path, $stream, $this->isInstanceOf(Config::class));
        $this->oldAdapter->expects($this->once())->method('delete')->with($path);

        $this->assertTrue($this->adapter->fileExists($path));
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testFileExistsWhenFileInBothAdapters(): void
    {
        $path = 'test.txt';
        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(true);
        $this->newAdapter->expects($this->exactly(2))->method('fileExists')->with($path)->willReturn(true);
        $this->oldAdapter->expects($this->once())->method('delete')->with($path);
        $this->oldAdapter->expects($this->never())->method('readStream');

        $this->assertTrue($this->adapter->fileExists($path));
    }

    public function testWrite(): void
    {
        $path = 'test.txt';
        $contents = 'contents';
        $config = new Config();

        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(false);
        $this->newAdapter->expects($this->once())->method('write')->with($path, $contents, $config);

        $this->adapter->write($path, $contents, $config);
    }

    public function testWriteStream(): void
    {
        $path = 'test.txt';
        $stream = fopen('php://memory', 'r');
        $config = new Config();

        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(false);
        $this->newAdapter->expects($this->once())->method('writeStream')->with($path, $stream, $config);

        $this->adapter->writeStream($path, $stream, $config);
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testRead(): void
    {
        $path = 'test.txt';
        $content = 'content';
        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(false);
        $this->newAdapter->expects($this->once())->method('read')->with($path)->willReturn($content);

        $this->assertSame($content, $this->adapter->read($path));
    }

    public function testReadStream(): void
    {
        $path = 'test.txt';
        $stream = fopen('php://memory', 'r');
        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(false);
        $this->newAdapter->expects($this->once())->method('readStream')->with($path)->willReturn($stream);

        $this->assertSame($stream, $this->adapter->readStream($path));
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function testDeleteWhenFileInBoth(): void
    {
        $path = 'test.txt';
        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(true);
        $this->oldAdapter->expects($this->once())->method('delete')->with($path);
        $this->newAdapter->expects($this->once())->method('delete')->with($path);

        $this->adapter->delete($path);
    }

    public function testDeleteWhenFileOnlyInNew(): void
    {
        $path = 'test.txt';
        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(false);
        $this->oldAdapter->expects($this->never())->method('delete');
        $this->newAdapter->expects($this->once())->method('delete')->with($path);

        $this->adapter->delete($path);
    }

    public function testDeleteDirectory(): void
    {
        $path = 'test-dir';
        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(true);
        $this->oldAdapter->expects($this->once())->method('deleteDirectory')->with($path);
        $this->newAdapter->expects($this->once())->method('deleteDirectory')->with($path);

        $this->adapter->deleteDirectory($path);
    }

    public function testDirectoryExistsMigratesDirectory(): void
    {
        $path = 'dir';
        $this->oldAdapter->expects($this->once())->method('directoryExists')->with($path)->willReturn(true);
        $this->newAdapter
            ->expects($this->once())->method('directoryExists')->with($path)
            ->willReturn(false);
        $this->newAdapter->expects($this->once())->method('createDirectory')->with($path, $this->isInstanceOf(Config::class));
        $this->oldAdapter->expects($this->once())->method('deleteDirectory')->with($path);

        $this->assertTrue($this->adapter->directoryExists($path));
    }

    public function testDirectoryExistsWhenOnlyNewAdapterHasDirectory(): void
    {
        $path = 'dir';
        $this->oldAdapter->expects($this->once())->method('directoryExists')->with($path)->willReturn(false);
        $this->newAdapter->expects($this->once())->method('directoryExists')->with($path)->willReturn(true);
        $this->newAdapter->expects($this->never())->method('createDirectory');
        $this->oldAdapter->expects($this->never())->method('deleteDirectory');

        $this->assertTrue($this->adapter->directoryExists($path));
    }

    public function testCreateDirectory(): void
    {
        $path = 'test-dir';
        $config = new Config();
        $this->newAdapter->expects($this->once())->method('createDirectory')->with($path, $config);
        $this->oldAdapter->expects($this->never())->method('createDirectory');

        $this->adapter->createDirectory($path, $config);
    }

    public function testSetVisibility(): void
    {
        $path = 'test.txt';
        $visibility = 'public';
        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(false);
        $this->newAdapter->expects($this->once())->method('setVisibility')->with($path, $visibility);

        $this->adapter->setVisibility($path, $visibility);
    }

    public function testVisibility(): void
    {
        $path = 'test.txt';
        $attributes = new FileAttributes($path, 123, 'public');
        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(false);
        $this->newAdapter->expects($this->once())->method('visibility')->with($path)->willReturn($attributes);

        $this->assertEquals($attributes, $this->adapter->visibility($path));
    }

    public function testMimeType(): void
    {
        $path = 'test.txt';
        $attributes = new FileAttributes($path, null, null, null, 'text/plain');
        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(false);
        $this->newAdapter->expects($this->once())->method('mimeType')->with($path)->willReturn($attributes);

        $this->assertEquals($attributes, $this->adapter->mimeType($path));
    }

    public function testLastModified(): void
    {
        $path = 'test.txt';
        $attributes = new FileAttributes($path, null, null, time());
        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(false);
        $this->newAdapter->expects($this->once())->method('lastModified')->with($path)->willReturn($attributes);

        $this->assertEquals($attributes, $this->adapter->lastModified($path));
    }

    public function testFileSize(): void
    {
        $path = 'test.txt';
        $attributes = new FileAttributes($path, 12345);
        $this->oldAdapter->expects($this->once())->method('fileExists')->with($path)->willReturn(false);
        $this->newAdapter->expects($this->once())->method('fileSize')->with($path)->willReturn($attributes);

        $this->assertEquals($attributes, $this->adapter->fileSize($path));
    }

    public function testListContents(): void
    {
        $path = 'some/dir';

        $oldItems = [
            new FileAttributes($path . '/a.txt'),
            new FileAttributes($path . '/b.txt'),
        ];
        $newItems = [
            new FileAttributes($path . '/b.txt'),
            new FileAttributes($path . '/c.txt'),
        ];

        $this->oldAdapter->expects($this->once())->method('listContents')->with($path, false)->willReturn($oldItems);
        $this->newAdapter->expects($this->once())->method('listContents')->with($path, false)->willReturn($newItems);

        $expected = [
            new FileAttributes($path . '/a.txt'),
            new FileAttributes($path . '/b.txt'),
            new FileAttributes($path . '/c.txt'),
        ];
        $this->assertEquals($expected, iterator_to_array($this->adapter->listContents($path, false)));
    }

    public function testMove(): void
    {
        $source = 'a.txt';
        $destination = 'b.txt';
        $config = new Config();

        $this->oldAdapter
            ->expects($this->exactly(2))->method('fileExists')
            ->withConsecutive([$source], [$destination])
            ->willReturn(false);
        $this->newAdapter->expects($this->once())->method('move')->with($source, $destination, $config);

        $this->adapter->move($source, $destination, $config);
    }

    public function testCopy(): void
    {
        $source = 'a.txt';
        $destination = 'b.txt';
        $config = new Config();

        $this->oldAdapter
            ->expects($this->exactly(2))->method('fileExists')
            ->withConsecutive([$source], [$destination])
            ->willReturn(false);
        $this->newAdapter->expects($this->once())->method('copy')->with($source, $destination, $config);

        $this->adapter->copy($source, $destination, $config);
    }
}
