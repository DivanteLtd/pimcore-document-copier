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
use Pimcore\Model\Asset\Image as AssetImage;
use Pimcore\Model\Asset\Image as ImageAsset;
use Pimcore\Model\Document;
use Pimcore\Model\Document\PageSnippet;
use Pimcore\Model\Document\Tag;

/**
 * Class Image
 * @package Divante\DocumentCopierBundle\ElementSerializer
 */
class Image extends GenericType
{
    /**
     * @param Tag $element
     * @return mixed
     * @throws InvalidElementTypeException
     */
    public static function getData(Tag $element)
    {
        if ($element instanceof Tag\Image) {
            $data = $element->getData();

            $imageAsset = AssetImage::getById($data['id']);

            if (!$imageAsset) {
                // Image asset does not exist
                return null;
            }

            $data['path'] = $imageAsset->getFullPath();
            unset($data['id']);

            return $data;
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
        $element = Document\Tag::factory($elementDto['type'], $elementName, $document->getId());

        $imageAsset = ImageAsset::getByPath($elementDto['data']['path']);

        if (!$imageAsset) {
            return;
        }

        unset($elementDto['data']['path']);
        $elementDto['data']['id'] = $imageAsset->getId();

        $element->setDataFromResource(serialize($elementDto['data']));
        $document->setElement($elementName, $element);
    }
}
