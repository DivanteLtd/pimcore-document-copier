<?php
/**
 * @category    pimcore-document-copier
 * @date        19/02/2020 08:21
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\Service;

use Divante\DocumentCopierBundle\Command\DocumentExportCommand;
use Divante\DocumentCopierBundle\Command\DocumentImportCommand;
use Divante\DocumentCopierBundle\Exception\ValidationException;
use Exception;
use Pimcore\Model\Document;
use Pimcore\Model\Element\Service;
use Pimcore\Model\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class AdminService
 * @package Divante\DocumentCopierBundle\Service
 */
class AdminService
{
    const MIN_DEPTH = 0;
    const MAX_DEPTH = 10;
    private const TEMPORARY_ROOT_DIRECTORY = 'var/tmp/document-copier/roots';
    private const TEMPORARY_EXPORT_DIRECTORY = 'var/tmp/document-copier/exports';
    private const TEMPORARY_IMPORT_DIRECTORY = 'var/tmp/document-copier/imports';
    private const FILE_EXTENSION = '.zip';
    private const FILE_MIME_TYPE = 'application/zip';

    /** @var string */
    private $kernelProjectDir;

    /** @var KernelInterface */
    private $kernel;

    /** @var FileService */
    private $fileService;

    /** @var ZipFileService */
    private $zipFileService;

    /**
     * AdminService constructor.
     * @param string $kernelProjectDir
     * @param KernelInterface $kernel
     * @param FileService $fileService
     * @param ZipFileService $zipFileService
     */
    public function __construct(
        string $kernelProjectDir,
        KernelInterface $kernel,
        FileService $fileService,
        ZipFileService $zipFileService
    ) {
        $this->kernelProjectDir = $kernelProjectDir;
        $this->kernel = $kernel;
        $this->fileService = $fileService;
        $this->zipFileService = $zipFileService;
    }

    /**
     * @param $path
     * @return string
     * @throws ValidationException
     */
    public function validatePath($path): string
    {
        $path = is_string($path) ? Service::correctPath($path) : '';
        $document = Document::getByPath($path);

        if (!$path) {
            throw new ValidationException('Invalid document path ' . htmlspecialchars($path));
        } elseif (!$document) {
            throw new ValidationException('Document ' . htmlspecialchars($path) . ' does not exist');
        }

        return $path;
    }

    /**
     * @param $depth
     * @return int
     * @throws ValidationException
     */
    public function validateDepth($depth): int
    {
        $depth = is_numeric($depth) ? intval($depth) : 0;

        if ($depth < self::MIN_DEPTH || $depth > self::MAX_DEPTH) {
            throw new ValidationException('Depth must be between ' . self::MIN_DEPTH . ' and ' . self::MAX_DEPTH);
        }

        return $depth;
    }

    /**
     * @param $user
     * @return User
     * @throws ValidationException
     */
    public function validateUser($user): User
    {
        if (!$user instanceof User || !$user->isAdmin()) {
            throw new ValidationException('You need to be an admin to do this');
        }

        return $user;
    }

    /**
     * @param $key
     * @return string
     * @throws ValidationException
     */
    public function validateDownloadKey($key): string
    {
        $key = preg_replace("/[^a-zA-Z0-9_-]+/", "", strval($key));

        if (empty($key)) {
            throw new ValidationException('Empty key');
        }

        return $key;
    }

    /**
     * @param $file
     * @return UploadedFile
     * @throws ValidationException
     */
    public function validateUploadedFile($file): UploadedFile
    {
        if (!$file instanceof UploadedFile) {
            throw new ValidationException('Invalid file type, expected: ' . UploadedFile::class);
        } elseif (!$file->isValid()) {
            throw new ValidationException('Failed file upload: ' . $file->getErrorMessage());
        } elseif ($file->getMimeType() !== self::FILE_MIME_TYPE) {
            throw new ValidationException(
                'Invalid file type, received: ' . $file->getMimeType() . ', expected: ' . self::FILE_MIME_TYPE
            );
        }

        return $file;
    }

    /**
     * @param $path
     * @param $depth
     * @param $user
     * @return string
     * @throws Exception
     */
    public function export(string $path, int $depth, User $user): string
    {
        $key = $this->generateDownloadKey($path, $depth, $user);
        $root = $this->getTemporaryRoot($key);
        mkdir($root, 0777, true);

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $input = new ArrayInput([
            'command' => DocumentExportCommand::NAME,
            '--path' => $path,
            '--root' => $root,
            '--recursiveDepth' => $depth,
        ]);
        $application->run($input, null);

        $zipDestination = $this->getDownloadPath($key);
        $this->zipFileService->zipRootDirectory($root, $zipDestination);

        return $key;
    }

    /**
     * @param $key
     * @return string
     */
    private function getTemporaryRoot($key): string
    {
        return $this->kernelProjectDir . '/' . self::TEMPORARY_ROOT_DIRECTORY . '/' . $key;
    }

    /**
     * @param string $key
     * @return string
     */
    public function getDownloadPath(string $key): string
    {
        return $this->kernelProjectDir . '/' . self::TEMPORARY_EXPORT_DIRECTORY . '/' . $key . self::FILE_EXTENSION;
    }

    /**
     * @param string $path
     * @param int $depth
     * @param User $user
     * @param UploadedFile $file
     * @throws ValidationException
     * @throws Exception
     */
    public function import(string $path, int $depth, User $user, UploadedFile $file): void
    {
        $temporaryRoot = $this->getTemporaryImportDirectory($user);
        $this->zipFileService->unzipRootDirectory($file->getRealPath(), $temporaryRoot);

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $input = new ArrayInput([
            'command' => DocumentImportCommand::NAME,
            '--path' => $path,
            '--root' => $temporaryRoot,
            '--recursiveDepth' => $depth,
        ]);
        $application->run($input, null);

        $this->zipFileService->deleteOldFiles($this->kernelProjectDir . '/' . self::TEMPORARY_IMPORT_DIRECTORY);
    }

    /**
     * @param User $user
     * @return string
     */
    private function getTemporaryImportDirectory(User $user): string
    {
        $dir = $this->kernelProjectDir . '/' . self::TEMPORARY_IMPORT_DIRECTORY . '/' . uniqid($user->getId() . '_');
        mkdir($dir, 0777, true);

        return $dir;
    }

    /**
     * @param string $path
     * @param int $depth
     * @param User $user
     * @return string
     */
    private function generateDownloadKey(string $path, int $depth, User $user): string
    {
        $document = Document::getByPath($path);

        if (!$document instanceof Document) {
            $documentKey = '';
        } elseif ($document->getRealFullPath() === '/') {
            $documentKey = 'home';
        } else {
            $documentKey = $document->getKey();
        }

        $documentKey = preg_replace("/[^a-zA-Z0-9]+/", "-", $documentKey);

        return uniqid($documentKey . '_' . strval($depth) . '_' . $user->getId());
    }
}
