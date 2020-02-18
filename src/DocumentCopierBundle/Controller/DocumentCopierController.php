<?php
/**
 * @category    enea5
 * @date        17/02/2020 09:40
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\Controller;

use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse;
use Pimcore\Model\Element\Service as ElementService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class DocumentCopierController extends AdminController
{
    /**
     * @Route("/export-document", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     */
    public function exportAction(Request $request)
    {
        /** @var LoggerInterface $logger */
        $logger = $this->container->get('logger');

        $path = ElementService::correctPath($request->get('path') ?? '');
        $depth = intval($request->get('depth') ?? 0);
        $user = $this->getAdminUser();

        if (!$path) {
            return $this->adminJson(['message' => "Invalid document path"], Response::HTTP_BAD_REQUEST);
        }

        if ($depth < 0 || $depth > 10) {
            return $this->adminJson(['message' => "Depth must be between 0 and 10"], Response::HTTP_BAD_REQUEST);
        }

        if (!$user || !$user->isAdmin()) {
            return $this->adminJson(['message' => "You need to be an admin to do this"], Response::HTTP_BAD_REQUEST);
        }

        $key = uniqid('documentcopier_export_', true);
        $logger->info("[DocumentCopier] User " . $user->getUsername() . " requested export of document "
            . $path . " at depth " . $depth . " (download key: " . $key . ")");

        // TODO: export, zip and store

        return $this->adminJson(['downloadKey' => $key], Response::HTTP_OK);
    }

    /**
     * @Route("/export-download", methods={"GET"})
     * @param Request $request
     * @return JsonResponse
     */
    public function downloadAction(Request $request)
    {
        /** @var LoggerInterface $logger */
        $logger = $this->container->get('logger');

        $key = $request->get('key');
        $user = $this->getAdminUser();

        if ($user && $user->isAdmin()) {
            $logger->info("[DocumentCopier] Streaming " . $key . " to user " . $user->getUsername());
            // TODO: return zipped export
        } else {
            $logger->info("[DocumentCopier] Denied user " . $user->getUsername() . " to download " . $key);

            return $this->adminJson(['message' => "You need to be an admin to do this"], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @Route("/import-document", methods={"POST"})
     * @param Request $request
     * @return Response
     */

    public function importAction(Request $request)
    {
        $path = $request->get('path');
        $depth = $request->get('depth');
        $user = $this->getAdminUser();

        // TODO: unzip to temp, validate and import

        return $this->adminJson([]);
    }
}
