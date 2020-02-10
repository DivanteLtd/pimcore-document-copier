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
use Exception;
use InvalidArgumentException;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\Document;
use Pimcore\Model\Document\Tag;

class DependencyManager
{
    /** @var FileService */
    private $fileService;

    /**
     * DependencyManager constructor.
     * @param FileService $storageService
     */
    public function __construct(FileService $storageService)
    {
        $this->fileService = $storageService;
    }

    /**
     * @param Document|PortableDocument $document
     * @param int $depth
     * @param string|null $rootDirectory
     * @param array $foundDependencies
     * @return array
     * @throws Exception
     */
    public function findDependencies(
        $document,
        int $depth = 2,
        ?string $rootDirectory = null,
        array $foundDependencies = []
    ): array {
        if ($depth <= 0) {
            return $foundDependencies;
        }

        $newDependencies = $this->findDirectDependencies($document);
        $uniqueDependencies = $this->uniqueDependencies(
            $newDependencies,
            $foundDependencies,
            $document->getRealFullPath()
        );

        foreach ($uniqueDependencies as $dependencyDto) {
            $foundDependencies[] = $dependencyDto;

            if ($dependencyDto['type'] === 'document') {
                if ($document instanceof Document) {
                    $dependency = Document::getByPath($dependencyDto['path']);
                } else {
                    $dependency = $this->fileService->loadDto($dependencyDto['path'], $rootDirectory);
                }

                if ($dependency instanceof Document || $dependency instanceof PortableDocument) {
                    $foundDependencies = array_unique(
                        array_merge(
                            $foundDependencies,
                            $this->findDependencies($dependency, $depth - 1, $rootDirectory, $foundDependencies)
                        ),
                        SORT_REGULAR
                    );
                }
            }
        }

        usort(
            $foundDependencies,
            function ($a, $b) {
                // Assets before documents, short paths before long paths
                if ($a['type'] === 'document' && $b['type'] !== 'document') {
                    return 1;
                } elseif ($a['type'] !== 'document' && $b['type'] === 'document') {
                    return -1;
                } else {
                    return strlen($a['path']) <=> strlen($b['path']);
                }
            }
        );

        return $foundDependencies;
    }

    /**
     * @param Document|PortableDocument $document
     * @return array
     * @throws Exception
     */
    public function findDirectDependencies($document): array
    {
        if ($document instanceof PortableDocument) {
            return $document->getDependencies();
        } elseif (!$document instanceof Document) {
            throw new InvalidArgumentException(
                'Expected ' . Document::class . ' or ' . PortableDocument::class . ' as $document argument'
            );
        }

        $dependencies = array_merge(
            $this->findChildrenDependencies($document),
            $this->findPropertyDependencies($document),
            $this->findSettingsDependencies($document)
        );

        if ($document instanceof Document\PageSnippet) {
            foreach ($document->getElements() as $element) {
                $dependency = $this->findElementDependency($element);
                if ($dependency) {
                    $dependencies[] = $dependency;
                }
            }

            if ($document->getContentMasterDocument()) {
                $dependencies[] = [
                    'type' => 'document',
                    'path' => $document->getContentMasterDocument()->getRealFullPath(),
                    'reason' => 'Content master document'
                ];
            }
        }

        return $dependencies;
    }

    /**
     * @param Document $document
     * @return array
     */
    private function findChildrenDependencies(Document $document): array
    {
        $dependencies = [];

        if ($document instanceof Document\Hardlink && $document->getChildrenFromSource()) {
            return [];
        }

        foreach ($document->getChildren() as $child) {
            if (in_array($child->getType(), PortableDocument::SUPPORTED_DOCUMENT_TYPES)) {
                $dependencies[] = [
                    'type' => 'document',
                    'path' => $child->getRealFullPath(),
                    'reason' => 'Child document',
                ];
            }
        }

        return $dependencies;
    }

    /**
     * @param Document $document
     * @return array
     */
    private function findPropertyDependencies(Document $document): array
    {
        $dependencies = [];

        foreach ($document->getProperties() as $property) {
            if ($property->isInherited()) {
                continue;
            }

            $reason = 'Property \'' . $property->getName() . '\'';

            if ($property->getType() === 'asset' && $property->getData() instanceof Asset) {
                $dependencies[] = [
                    'type' => 'asset',
                    'path' => $property->getData()->getFullPath(),
                    'reason' => $reason,
                ];
            } elseif ($property->getType() === 'document' && $property->getData() instanceof Document) {
                $dependencies[] = [
                    'type' => 'document',
                    'path' => $property->getData()->getRealFullPath(),
                    'reason' => $reason,
                ];
            }
        }

        return $dependencies;
    }


    /**
     * @param Document $document
     * @return array
     */
    private function findSettingsDependencies(Document $document): array
    {
        $dependencies = [];

        if ($document instanceof Document\Link && $document->getLinktype() === 'internal') {
            $linkedDocument = Document::getById($document->getInternal());

            if ($linkedDocument) {
                $dependencies[] = [
                    'type' => 'document',
                    'path' => $linkedDocument->getRealFullPath(),
                    'reason' => 'Internal link',
                ];
            }
        } elseif ($document instanceof Document\Hardlink && $document->getSourceDocument()) {
            $dependencies[] = [
                'type' => 'document',
                'path' => $document->getSourceDocument()->getRealFullPath(),
                'reason' => 'Internal hardlink',
            ];
        }

        return $dependencies;
    }

    /**
     * @param Tag $element
     * @return array|null
     */
    private function findElementDependency(Tag $element): ?array
    {
        $elementType = strval($element->getType());
        $reason = 'Element \'' . $element->getName() . '\' of type \'' . $elementType . '\'';

        if ($elementType === 'image') {
            $image = Image::getById($element->getData()['id'] ?? 0);

            if ($image) {
                return [
                    'type' => 'asset',
                    'path' => $image->getFullPath(),
                    'reason' => $reason,
                ];
            }
        } elseif ($elementType === 'snippet') {
            $snippet = Document\Snippet::getById($element->getData());

            if ($snippet) {
                return [
                    'type' => 'document',
                    'path' => $snippet->getRealFullPath(),
                    'reason' => $reason,
                ];
            }
        }

        return null;
    }

    /**
     * @param array $newDependencies
     * @param array $foundDependencies
     * @param string $foundPath
     * @return array
     */
    private function uniqueDependencies(
        array $newDependencies,
        array $foundDependencies,
        string $foundPath
    ): array {
        $uniqueNewDependencies = [];

        foreach ($newDependencies as $dependencyDto) {
            $duplicates = array_filter(
                $foundDependencies,
                function ($val) use ($dependencyDto) {
                    return $dependencyDto['path'] === $val['path'];
                }
            );

            if (empty($duplicates) && $dependencyDto['path'] !== $foundPath) {
                $uniqueNewDependencies[] = $dependencyDto;
            }
        }

        return $uniqueNewDependencies;
    }
}
