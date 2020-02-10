<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 09:51
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\Service;

use Divante\DocumentCopierBundle\DTO\PortableDocument;
use Divante\DocumentCopierBundle\Exception\InvalidElementTypeException;
use Divante\DocumentCopierBundle\ElementSerializer;
use Exception;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Model\Document\Tag;
use Psr\Log\LoggerInterface;
use Pimcore\Model\Element\Service as ElementService;

/**
 * Class ExportService
 * @package Divante\DocumentCopierBundle\Service
 */
class ExportService
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var DependencyManager */
    protected $dependencyManager;

    /**
     * DocumentCopierService constructor.
     * @param LoggerInterface $logger
     * @param DependencyManager $dependencyManager
     */
    public function __construct(LoggerInterface $logger, DependencyManager $dependencyManager)
    {
        $this->logger = $logger;
        $this->dependencyManager = $dependencyManager;
    }

    /**
     * @param Document $document
     * @return PortableDocument
     */
    public function export(Document $document): PortableDocument
    {
        $dto = new PortableDocument();

        $dto->setRealFullPath(ElementService::correctPath($document->getRealFullPath()));
        $dto->setType($document->getType());

        if ($document instanceof Document\PageSnippet) {
            $dto->setElements($this->exportElements($document));
        }

        $dto->setProperties($this->exportProperties($document));
        $dto->setSettings($this->exportSettings($document));
        $dto->setAncestors($this->exportAncestorStubs($document));

        try {
            $dto->setDependencies($this->dependencyManager->findDirectDependencies($document));
        } catch (Exception $e) {
            $this->logger->error('[DocumentCopier] Failed to find dependencies: ' . $e->getMessage());
        }

        return $dto;
    }

    /**
     * @param Document\PageSnippet $document
     * @return array
     */
    private function exportElements(Document\PageSnippet $document): array
    {
        $elements = [];

        foreach ($document->getElements() as $name => $element) {
            /** @var $element Tag */
            try {
                $elementType = $element->getType();

                if (!in_array($elementType, PortableDocument::SUPPORTED_EDITABLE_TYPES)) {
                    continue;
                }

                switch (strval($elementType)) {
                    case 'date':
                        $elementData = ElementSerializer\Date::getData($element);
                        break;
                    case 'image':
                        $elementData = ElementSerializer\Image::getData($element);
                        break;
                    case 'link':
                        $elementData = ElementSerializer\Link::getData($element);
                        break;
                    case 'snippet':
                        $elementData = ElementSerializer\Snippet::getData($element);
                        break;
                    default:
                        $elementData = ElementSerializer\GenericType::getData($element);
                }

                $elements[$name] = [
                    'type' => $elementType,
                    'data' => $elementData,
                ];
            } catch (InvalidElementTypeException $e) {
                $this->logger->warning('[DocumentCopier] Invalid element encountered while exporting element ' .
                    $name . ' ' . $document->getRealFullPath() . ': ' . $element->getType());

                continue;
            } catch (Exception $e) {
                $this->logger->error('[DocumentCopier] Exception while exporting element ' . $name . ': ' .
                    $e->getMessage());

                continue;
            }
        }

        return $elements;
    }

    /**
     * @param Document $document
     * @return array
     */
    private function exportProperties(Document $document): array
    {
        $properties = [];

        foreach ($document->getProperties() as $name => $property) {
            if ($property->getInherited() ||
                !in_array($property->getType(), PortableDocument::SUPPORTED_PROPERTY_TYPES)
            ) {
                continue;
            }

            $data = $property->getData();

            if ($property->getType() === 'document' || $property->getType() === 'asset') {
                if ($data instanceof Document) {
                    $data = $data->getRealFullPath();
                } elseif ($data instanceof Asset) {
                    $data = $data->getFullPath();
                } else {
                    $data = null;
                }
            }

            $properties[$name] = [
                'type' => $property->getType(),
                'data' => $data,
                'inheritable' => $property->getInheritable(),
            ];
        }

        return $properties;
    }

    /**
     * @param Document $document
     * @return array
     */
    private function exportSettings(Document $document): array
    {
        $settings = [];

        foreach (PortableDocument::SUPPORTED_SETTINGS as $setting) {
            $method = 'get' . ucfirst($setting);
            if (method_exists($document, $method)) {
                $settings[$setting] = $document->{$method}();
            }
        }

        if ($document instanceof Document\Link) {
            $settings['_link'] = $this->exportLinkSetting($document);
        } elseif ($document instanceof Document\Hardlink) {
            $settings['_source'] = $this->exportSourceSetting($document);
        }

        return $settings;
    }

    /**
     * @param Document\Link $document
     * @return string|null
     */
    private function exportLinkSetting(Document\Link $document): ?string
    {
        if ($document->getLinktype() === 'internal') {
            $linkedDocument = Document::getById($document->getInternal());

            return $linkedDocument ? $linkedDocument->getRealFullPath() : null;
        } elseif ($document->getLinktype() === 'direct') {
            return $document->getLink();
        } else {
            return null;
        }
    }

    /**
     * @param Document\Hardlink $document
     * @return string|null
     */
    private function exportSourceSetting(Document\Hardlink $document): ?string
    {
        return $document->getSourceDocument() ? $document->getSourceDocument()->getRealFullPath() : null;
    }

    /**
     * @param Document $document
     * @return array
     */
    private function exportAncestorStubs(Document $document): array
    {
        $ancestors = [];

        $parent = $document->getParent();

        while ($parent) {
            $ancestors[$parent->getRealFullPath()] = $parent->getType();
            $parent = $parent->getParent();
        }

        return array_reverse($ancestors);
    }
}
