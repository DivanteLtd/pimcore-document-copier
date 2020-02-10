<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 09:51
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

use Divante\DocumentCopierBundle\DocumentCopierBundle;
use Pimcore\HttpKernel\BundleCollection\BundleCollection;
use Pimcore\Kernel;

class AppKernel extends Kernel
{
    /**
     * {@inheritdoc}
     */
    public function registerBundlesToCollection(BundleCollection $collection)
    {
        $collection->addBundle(new DocumentCopierBundle());
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        parent::boot();

        \Pimcore::setKernel($this);
    }
}
