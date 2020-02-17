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
use Pimcore\Model\Element\Service as ElementService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class DocumentCopierController extends AdminController
{
    /**
     * @Route("/export-document", methods={"POST"})
     * @param Request $request
     * @return Response
     */
    public function exportAction(Request $request)
    {
        $path = $request->get('path');
        $depth = $request->get('depth');
        $user = $this->getAdminUser();

        return $this->adminJson(
            [
                'path' => ElementService::correctPath($path ?? ''),
                'depth' => intval($depth ?? 0),
                'username' => $user ? $user->getUsername() : 'no user',
                'isAdmin' => $user ? $user->isAdmin() : 'no user',
            ]
        );
    }
}
