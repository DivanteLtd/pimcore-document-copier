<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 09:51
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\ElementSerializer;

use Divante\DocumentCopierBundle\Exception\InvalidElementTypeException;
use Pimcore\Model\Document;
use Pimcore\Model\Document\PageSnippet;
use Pimcore\Model\Document\Tag;

/**
 * Class Date
 * @package Divante\DocumentCopierBundle\ElementSerializer
 */
class Date extends GenericType
{
    /**
     * @param Tag $element
     * @return mixed
     * @throws InvalidElementTypeException
     */
    public static function getData(Tag $element)
    {
        if ($element instanceof Tag\Date) {
            return $element->date ? $element->date->getTimestamp() : null;
        } else {
            throw new InvalidElementTypeException();
        }
    }

    /**
     * @param string $elementName
     * @param array $elementDto
     * @param PageSnippet $document
     */
    public static function setData(string $elementName, array $elementDto, PageSnippet $document): void
    {
        /** @var Tag\Date $element */
        $element = Document\Tag::factory($elementDto['type'], $elementName, $document->getId());
        $element->setDataFromResource($elementDto['data']);

        $document->setElement($elementName, $element);
    }
}
