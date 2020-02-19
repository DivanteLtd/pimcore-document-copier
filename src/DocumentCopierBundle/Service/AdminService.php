<?php
/**
 * @category    pimcore-document-copier
 * @date        19/02/2020 08:21
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\Service;

use Divante\DocumentCopierBundle\Exception\ValidationException;
use Pimcore\Model\Document;
use Pimcore\Model\Element\Service;
use Pimcore\Model\User;
use Symfony\Component\HttpFoundation\Response;

class AdminService
{
    const MIN_DEPTH = 0;
    const MAX_DEPTH = 10;

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

    public function validateDownloadKey($key): string
    {
        return preg_replace("/[^a-zA-Z0-9]+/", "", strval($key));
    }

    /**
     * @param $path
     * @param $depth
     * @param $user
     * @return string
     */
    public function export(string $path, int $depth, User $user): string
    {
        // TODO
        $key = $this->generateDownloadKey($user);

        return $key;
    }

    /**
     * @param string $key
     * @return string
     */
    public function getDownloadPath(string $key): string
    {
        // TODO
        return '';
    }

    /**
     * @param string $path
     * @param int $depth
     * @param User $user
     * @param $file
     */
    public function import(string $path, int $depth, User $user, $file): void
    {
        // TODO
    }

    /**
     * @param User|null $user
     * @return string
     */
    private function generateDownloadKey(?User $user): string
    {
        return uniqid(strval($user ? $user->getId() : rand()));
    }
}
