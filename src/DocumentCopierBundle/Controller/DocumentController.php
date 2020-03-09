<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 12:59
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\Controller;

use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Class DocumentController
 * @package Divante\DocumentCopierBundle\Controller
 */
class DocumentController extends FrontendController
{
    /**
     * @param FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $request = $event->getRequest();

        $this->setViewAutoRender($request, true, 'twig');
    }

    /**
     * Default action to view test document
     */
    public function defaultAction()
    {
    }
}
