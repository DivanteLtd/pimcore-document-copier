<?php
/**
 * @category    pimcore-document-copier
 * @date        09/03/2020 11:54
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\Service;

use DirectoryIterator;
use Divante\DocumentCopierBundle\DTO\PortableDocument;
use Divante\DocumentCopierBundle\Exception\ValidationException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Filesystem\Exception\IOException;
use ZipArchive;

/**
 * Class ZipFileService
 * @package Divante\DocumentCopierBundle\Service
 */
class ZipFileService
{
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
            } elseif (is_file($file)) {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }

        $zip->close();

        $this->recursiveDelete($source);
    }

    /**
     * @param string $directoryToDelete
     */
    private function recursiveDelete(string $directoryToDelete): void
    {
        if (!is_dir($directoryToDelete)) {
            return;
        }

        $filesToDelete = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directoryToDelete, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($filesToDelete as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($directoryToDelete);
    }

    /**
     * @param string $zipFilePath
     * @param string $extractTo
     * @throws ValidationException
     */
    public function unzipRootDirectory(string $zipFilePath, string $extractTo): void
    {
        if (!is_readable($zipFilePath)) {
            throw new IOException('Cannot read uploaded file');
        }

        $zip = new ZipArchive();
        $success = $zip->open($zipFilePath);

        if (!$success) {
            $zip->close();
            throw new IOException('Cannot open ZIP file');
        }

        $success = $zip->extractTo($extractTo);
        $zip->close();

        if (!$success) {
            throw new IOException('Cannot unzip the archive');
        }

        $this->validateRootStructure($extractTo);
    }

    /**
     * @param string $rootPath
     * @throws ValidationException
     */
    private function validateRootStructure(string $rootPath): void
    {
        if (!is_dir($rootPath)) {
            throw new ValidationException('Root is not a directory');
        }

        $assetsDir = $rootPath . '/' . FileService::ASSETS_DIRECTORY;

        if (!is_dir($assetsDir)) {
            throw new ValidationException('Assets directory not found');
        }

        $documentsDir = $rootPath . '/' . FileService::DOCUMENTS_DIRECTORY;

        if (!is_dir($documentsDir)) {
            throw new ValidationException('Documents directory not found');
        }

        $documentFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($documentsDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $file */
        foreach ($documentFiles as $file) {
            $this->validateFile($file, $rootPath);
        }
    }

    /**
     * @param SplFileInfo $file
     * @param string $rootPath
     * @throws ValidationException
     */
    private function validateFile(SplFileInfo $file, string $rootPath): void
    {
        $fullPath = $file->getRealPath();
        $relativePath = substr($fullPath, strlen($rootPath) + 1);

        if (!$file->isFile()) {
            return;
        }

        if (!$file->isReadable()) {
            throw new ValidationException('Unreadable file ' . $relativePath);
        }

        if (!$file->getExtension() === 'json') {
            throw new ValidationException('Invalid file extension ' . $relativePath);
        }

        $content = file_get_contents($fullPath);

        if (!$content) {
            throw new ValidationException('Invalid file content ' . $relativePath);
        }

        $dto = PortableDocument::fromJson($content);

        if (!$dto) {
            throw new ValidationException('Unable to deserialize: ' . $relativePath);
        }
    }

    /**
     * @param string $dir
     * @param int $secondsOld
     */
    public function deleteOldFiles(string $dir, int $secondsOld = 60)
    {
        $files = new DirectoryIterator($dir);

        foreach ($files as $file) {
            if ($file->isDot()) {
                continue;
            }

            if ($file->getMTime() < time() - $secondsOld) {
                if ($file->isDir()) {
                    $this->recursiveDelete($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
        }
    }
}
