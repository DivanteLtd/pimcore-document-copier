<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 09:51
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\ElementSerializer;

use Pimcore\Model\Document;
use Pimcore\Model\Document\PageSnippet;
use Pimcore\Model\Document\Tag;

class GenericType
{
    /**
     * @param Tag $element
     * @return mixed
     */
    public static function getData(Tag $element)
    {
        // Default that works for simple fields
        // To be subclassed
        return $element->getData();
    }

    /**
     * @param string $elementName
     * @param array $elementDto
     * @param PageSnippet $document
     */
    public static function setData(string $elementName, array $elementDto, PageSnippet $document): void
    {
        // Default that works for simple fields
        // To be subclassed
        $element = Document\Tag::factory($elementDto['type'], $elementName, $document->getId());
        $element->setDataFromResource($elementDto['data']);

        $document->setElement($elementName, $element);
    }
}
