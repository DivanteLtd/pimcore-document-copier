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
use Divante\DocumentCopierBundle\Exception\ValidationException;
use Pimcore\Model\Document;
use Pimcore\Model\Element\Service;
use Pimcore\Model\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\HttpKernel\KernelInterface;

class AdminService
{
    const MIN_DEPTH = 0;
    const MAX_DEPTH = 10;
    private const TEMPORARY_ROOT_DIRECTORY = 'var/tmp/document-copier/roots';
    private const TEMPORARY_EXPORT_DIRECTORY = 'var/tmp/document-copier/exports';
    private const TEMPORARY_IMPORT_DIRECTORY = 'var/tmp/document-copier/imports';
    private const FILE_EXTENSION = '.zip';

    /** @var string */
    private $kernelProjectDir;

    /** @var KernelInterface */
    private $kernel;

    /** @var FileService */
    private $fileService;

    /**
     * AdminService constructor.
     * @param string $kernelProjectDir
     * @param KernelInterface $kernel
     * @param FileService $fileService
     */
    public function __construct(string $kernelProjectDir, KernelInterface $kernel, FileService $fileService)
    {
        $this->kernelProjectDir = $kernelProjectDir;
        $this->kernel = $kernel;
        $this->fileService = $fileService;
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

        if (!$path || !$document) {
            throw new ValidationException('Invalid document path ' . htmlspecialchars($path));
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
     */
    public function validateDownloadKey($key): string
    {
        return preg_replace("/[^a-zA-Z0-9_-]+/", "", strval($key));
    }

    /**
     * @param $path
     * @param $depth
     * @param $user
     * @return string
     * @throws \Exception
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
        $this->fileService->zipRootDirectory($root, $zipDestination);

        return $key;
    }

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
     * @param $file
     */
    public function import(string $path, int $depth, User $user, $file): void
    {
        // TODO: validate file
        // TODO: unzip to temp root
        // TODO: run import command
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
        $documentKey = $document ? $document->getKey() : '';
        $documentKey = preg_replace("/[^a-zA-Z0-9]+/", "-", $documentKey);

        return uniqid($documentKey . '_' . strval($depth) . '_' . $user->getId());
    }
}
