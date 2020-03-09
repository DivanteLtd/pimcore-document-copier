<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 09:51
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

namespace Divante\DocumentCopierBundle;

use Pimcore\Extension\Bundle\Traits\PackageVersionTrait;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;

/**
 * Class DocumentCopierBundle
 * @package Divante\DocumentCopierBundle
 */
class DocumentCopierBundle extends AbstractPimcoreBundle
{
    use PackageVersionTrait;

    /**
     * @return array|\Pimcore\Routing\RouteReferenceInterface[]|string[]
     */
    public function getJsPaths()
    {
        return [
            '/bundles/documentcopier/js/startup.js',
        ];
    }

    /**
     * @return string
     */
    public function getComposerPackageName(): string
    {
        return 'divante/pimcore-document-copier';
    }

    /**
     * @return string
     */
    public function getNiceName(): string
    {
        return 'Document Copier';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Pimcore bundle for copying documents between environments';
    }
}
