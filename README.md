# Flysystem Lazy Migration Adapter

`antonsacred/flysystem-lazy-migration-adapter` provides a small Flysystem adapter wrapper that moves files from an old adapter to a new one the first time they are accessed. Reads, writes, deletes, and metadata calls automatically migrate the underlying file so you can cut over storage backends without a coordinated bulk move.

## Installation

```bash
composer require antonsacred/flysystem-lazy-migration-adapter
```

Supports PHP 7.4+ and both Flysystem v2 and v3 (`league/flysystem` `^2.0 || ^3.0`).

## Usage

Wrap your existing adapters and start using the wrapper everywhere you would normally use a `FilesystemAdapter`. Files encountered on the old adapter will be copied to the new one and removed from the old location on first access.

```php
use Antonsacred\FlysystemLazyMigrationAdapter\LazyMigrationAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

$oldAdapter = new LocalFilesystemAdapter('/mnt/old-storage');
$newAdapter = new LocalFilesystemAdapter('/mnt/new-storage');

$migratingAdapter = new LazyMigrationAdapter($oldAdapter, $newAdapter);
$filesystem = new Filesystem($migratingAdapter);

// The first read migrates the file to the new adapter transparently.
$contents = $filesystem->read('path/to/file.txt');
```

### How it works

- Each operation checks the old adapter first. If a file exists there and not on the new adapter, it is streamed over to the new adapter and deleted from the old one.
- If a file exists on both, the copy on the old adapter is removed and the new adapter is used for the operation.
- Directory creation always targets the new adapter; deletes target both when applicable.

## Testing

```bash
composer install
composer test
```

The GitHub Actions workflow runs the test suite against Flysystem v2 on PHP 7.4+ and Flysystem v3 on PHP 8.2+.
