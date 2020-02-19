<?php
/**
 * @category    pimcore-document-copier
 * @date        17/02/2020 09:40
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\Controller;

use Divante\DocumentCopierBundle\Exception\ValidationException;
use Divante\DocumentCopierBundle\Service\AdminService;
use Exception;
use Pimcore\Bundle\AdminBundle\Controller\AdminController as PimcoreAdminController;
use Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AdminController
 * @package Divante\DocumentCopierBundle\Controller
 */
class AdminController extends PimcoreAdminController
{
    /**
     * @Route("/export-document", methods={"POST"})
     * @param Request $request
     * @param AdminService $adminService
     * @param LoggerInterface $logger
     * @return JsonResponse
     */
    public function exportAction(Request $request, AdminService $adminService, LoggerInterface $logger)
    {
        try {
            $path = $adminService->validatePath($request->get('path'));
            $depth = $adminService->validateDepth($request->get('depth'));
            $user = $adminService->validateUser($this->getAdminUser());
        } catch (ValidationException $e) {
            return $this->adminJson(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $logger->info("[DocumentCopier] User " . $user->getUsername() . " requested export of document "
            . $path . " at depth " . $depth);

        try {
            $key = $adminService->export($path, $depth, $user);
        } catch (Exception $e) {
            return $this->adminJson(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->adminJson(['key' => $key], Response::HTTP_OK);
    }

    /**
     * @Route("/download-export", methods={"GET"})
     * @param Request $request
     * @param AdminService $adminService
     * @param LoggerInterface $logger
     * @return BinaryFileResponse|JsonResponse
     */
    public function downloadAction(Request $request, AdminService $adminService, LoggerInterface $logger)
    {
        try {
            $key = $adminService->validateDownloadKey($request->get('key'));
            $user = $adminService->validateUser($this->getAdminUser());
        } catch (ValidationException $e) {
            return $this->adminJson(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $filePath = $adminService->getDownloadPath($key);

        if (!is_readable($filePath)) {
            $logger->warning('[DocumentCopier] User ' . $user->getUsername() . ' requested unreadable file '
                . $filePath);

            return $this->adminJson(['message' => 'File is no longer available'], Response::HTTP_GONE);
        }

        return $this->file($filePath);
    }

    /**
     * @Route("/import-document", methods={"POST"})
     * @param Request $request
     * @param AdminService $adminService
     * @param LoggerInterface $logger
     * @return JsonResponse
     */
    public function importAction(Request $request, AdminService $adminService, LoggerInterface $logger)
    {
        try {
            $path = $adminService->validatePath($request->get('path'));
            $depth = $adminService->validateDepth($request->get('depth'));
            $user = $adminService->validateUser($this->getAdminUser());
        } catch (ValidationException $e) {
            return $this->adminJson(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $logger->info("[DocumentCopier] User " . $user->getUsername() . " requested import of document "
            . $path . " at depth " . $depth);

        $file = $request->files->get('file');

        try {
            $adminService->import($path, $depth, $user, $file);
        } catch (Exception $e) {
            return $this->adminJson(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->adminJson([], Response::HTTP_OK);
    }
}
