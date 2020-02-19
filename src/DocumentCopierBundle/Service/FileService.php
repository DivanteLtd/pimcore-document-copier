<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 09:51
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\Service;

use Divante\DocumentCopierBundle\DTO\PortableDocument;
use Exception;
use FilesystemIterator;
use InvalidArgumentException;
use Pimcore\Model\Asset;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Filesystem\Exception\IOException;
use ZipArchive;

/**
 * Class FileService
 * @package Divante\DocumentCopierBundle\Service
 */
class FileService
{
    const DOCUMENTS_DIRECTORY = 'documents';
    const ASSETS_DIRECTORY = 'assets';
    private const DEFAULT_ROOT_DIRECTORY = 'app/Resources';

    /** @var string */
    protected $kernelProjectDir;

    /**
     * FileService constructor.
     * @param string $kernelProjectDir
     */
    public function __construct(string $kernelProjectDir)
    {
        $this->kernelProjectDir = $kernelProjectDir;
    }

    /**
     * @return string
     */
    public function getDefaultRootDirectory(): string
    {
        return $this->kernelProjectDir . '/' . self::DEFAULT_ROOT_DIRECTORY;
    }

    /**
     * Save DTO in file system
     * @param PortableDocument $dto
     * @param string|null $rootDirectory
     * @return string
     */
    public function saveDto(PortableDocument $dto, ?string $rootDirectory): string
    {
        if (!$rootDirectory) {
            $rootDirectory = $this->getDefaultRootDirectory();
        }

        $treePath = $dto->getRealFullPath();
        $filePath =  $rootDirectory . '/' . self::DOCUMENTS_DIRECTORY . $treePath . '.json';

        $dir = implode('/', array_slice(explode('/', $filePath), 0, -1));

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $success = file_put_contents($filePath, json_encode($dto, JSON_PRETTY_PRINT));

        if ($success && is_readable($filePath)) {
            return $filePath;
        } else {
            throw new IOException('[DocumentCopier] Could not save JSON to ' . $filePath);
        }
    }

    /**
     * Load DTO from file system to PHP object
     * @param string $realFullPath
     * @param string|null $rootDirectory
     * @return PortableDocument|null
     */
    public function loadDto(string $realFullPath, ?string $rootDirectory): ?PortableDocument
    {
        if (!$rootDirectory) {
            $rootDirectory = $this->getDefaultRootDirectory();
        }

        $filePath =  $rootDirectory . '/' . self::DOCUMENTS_DIRECTORY . $realFullPath . '.json';

        if (!is_readable($filePath)) {
            throw new IOException('[DocumentCopier] DTO file ' . $filePath . ' does not exist');
        }

        $dto = PortableDocument::fromJson(file_get_contents($filePath));

        if (!$dto) {
            throw new InvalidArgumentException('[DocumentCopier] Could not decode DTO from file ' . $filePath);
        }

        return $dto;
    }

    /**
     * Save Pimcore asset to file system
     * @param Asset $asset
     * @param string|null $rootDirectory
     * @return string
     */
    public function saveAsset(Asset $asset, ?string $rootDirectory): string
    {
        if (!$rootDirectory) {
            $rootDirectory = $this->getDefaultRootDirectory();
        }

        $treePath = $asset->getRealFullPath();
        $filePath =  $rootDirectory . '/' . self::ASSETS_DIRECTORY . $treePath;

        $dir = implode('/', array_slice(explode('/', $filePath), 0, -1));

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $success = file_put_contents($filePath, $asset->getData());

        if ($success && is_readable($filePath)) {
            return $filePath;
        } else {
            throw new IOException('[DocumentCopier] Could not save asset to ' . $filePath);
        }
    }


    /**
     * Load asset from file system to Pimcore
     * @param string $fullPath
     * @param string|null $rootDirectory
     * @return Asset
     * @throws Exception
     */
    public function loadAsset(string $fullPath, ?string $rootDirectory): Asset
    {
        if (!$rootDirectory) {
            $rootDirectory = $this->getDefaultRootDirectory();
        }

        $filePath = $filePath =  $rootDirectory . '/' . self::ASSETS_DIRECTORY . $fullPath;

        if (!is_readable($filePath)) {
            throw new IOException('[DocumentCopier] Asset file ' . $filePath . ' does not exist');
        }

        $asset = Asset::getByPath($fullPath);

        if (!$asset) {
            $fileName = end(explode('/', $fullPath));
            $folderPath = implode('/', array_slice(explode('/', $fullPath), 0, -1));
            $parent = $this->createAssetFolder($folderPath);

            $asset = new Asset();
            $asset->setFilename($fileName);
            $asset->setParent($parent);
        }

        $asset->setData(file_get_contents($filePath));
        $asset->save();

        return $asset;
    }

    /**
     * @param string $rootDirectory
     * @param string $zipDestination
     */
    public function zipRootDirectory(string $rootDirectory, string $zipDestination): void
    {
        $destinationDir = implode('/', array_slice(explode('/', $zipDestination), 0, -1));

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0777, true);
        }

        $zip = new ZipArchive();
        $zip->open($zipDestination, ZipArchive::CREATE);

        $source = str_replace('\\', '/', realpath($rootDirectory));

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $source,
                FilesystemIterator::KEY_AS_PATHNAME |
                FilesystemIterator::CURRENT_AS_PATHNAME |
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file = str_replace('\\', '/', realpath($file));

            if (is_dir($file)) {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            } else if (is_file($file)) {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }

        $zip->close();

        $filesToDelete = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($filesToDelete as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($source);
    }

    /**
     * @param string $path
     * @return Asset
     */
    private function createAssetFolder(string $path): Asset
    {
        $folderKeys = explode('/', $path);

        $path = array_reduce(
            $folderKeys,
            function (string $path, string $key) {
                $folder = Asset::getByPath($path . '/' . $key);

                if (!$folder) {
                    $asset = new Asset\Folder();
                    $asset->setFilename($key);
                    $parent = Asset::getByPath($path);
                    $asset->setParent($parent ?? Asset::getByPath('/'));
                    $asset->save();
                }

                return $path . '/' . $key;
            },
            ''
        );

        return Asset::getByPath($path);
    }
}
