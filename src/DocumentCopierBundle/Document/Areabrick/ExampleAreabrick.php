<?php
/**
 * @category    pimcore-document-copier
 * @date        14/02/2020 07:57
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\Document\Areabrick;

use Pimcore\Extension\Document\Areabrick\AbstractTemplateAreabrick;
use Pimcore\Model\Document\Tag\Area\Info;

class ExampleAreabrick extends AbstractTemplateAreabrick
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'Example areabrick';
    }

    /**
     * @inheritDoc
     */
    public function getTemplateSuffix()
    {
        return static::TEMPLATE_SUFFIX_TWIG;
    }

    /**
     * Prevent default wrapper from rendering
     *
     * @param Info $info
     * @return string
     */
    public function getHtmlTagOpen(Info $info)
    {
        return '';
    }

    /**
     * Prevent default wrapper from rendering
     *
     * @param Info $info
     * @return string
     */
    public function getHtmlTagClose(Info $info)
    {
        return '';
    }
}
