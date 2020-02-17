<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 09:51
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Divante\DocumentCopierBundle\DTO;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Class PortableDocument
 * @package Divante\DocumentCopierBundle\DTO
 */
class PortableDocument implements JsonSerializable
{
    const SUPPORTED_DOCUMENT_TYPES = [
        'folder',
        'page',
        'snippet',
        'link',
        'hardlink',
        'email',
        //'newsletter',
        //'printpage',
        //'printcontainer',
    ];

    const SUPPORTED_EDITABLE_TYPES = [
        'input',
        'textarea',
        'wysiwyg',
        'checkbox',
        'date',
        'image',
        'link',
        'numeric',
        'table',
        'multiselect',
        'select',
        'snippet',
        'block',
        'area',
        'areablock',
        //'embed',
        //'pdf',
        //'relation',
        //'relations',
        //'renderlet',
        //'scheduledblock',
        //'video',
    ];

    const SUPPORTED_SETTINGS = [
        'title',
        'description',
        'module',
        'controller',
        'action',
        'template',
        'published',
        'prettyUrl',
        '_link',
        'linkType',
        '_source',
        'childrenFromSource',
        'propertiesFromSource',
        'subject',
        'from',
        'replyTo',
        'to',
        'cc',
        'bcc',
        //'_contentMaster',
    ];

    const SUPPORTED_PROPERTY_TYPES = [
        'text',
        'bool',
        'document',
        'asset',
        //'object',
    ];

    /** @var string */
    private $realFullPath;

    /** @var string */
    private $type;

    /** @var array */
    private $ancestors;

    /** @var array */
    private $elements;

    /** @var array */
    private $properties;

    /** @var array */
    private $settings;

    /** @var array */
    private $dependencies;

    /**
     * @param string $json
     * @return null|PortableDocument
     */
    public static function fromJson(string $json): ?PortableDocument
    {
        $decoded = json_decode($json, true);

        if (!$decoded) {
            return null;
        }

        $dto = new PortableDocument();
        $dto->setRealFullPath(strval($decoded['realFullPath']));
        $dto->setType(strval($decoded['type']));
        $dto->setAncestors($decoded['ancestors'] ?? []);
        $dto->setElements($decoded['elements'] ?? []);
        $dto->setProperties($decoded['properties'] ?? []);
        $dto->setSettings($decoded['settings'] ?? []);
        $dto->setDependencies($decoded['dependencies'] ?? []);

        return $dto;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        if (!in_array($type, self::SUPPORTED_DOCUMENT_TYPES)) {
            throw new InvalidArgumentException('Unsupported document type: ' . $type);
        }
        $this->type = $type;
    }

    /**
     * @return array
     */
    public function getAncestors(): array
    {
        return $this->ancestors;
    }

    /**
     * @param array $ancestors
     */
    public function setAncestors(array $ancestors): void
    {
        $this->ancestors = $ancestors;
    }

    /**
     * @return string
     */
    public function getRealFullPath(): string
    {
        return $this->realFullPath;
    }

    /**
     * @param string $realFullPath
     */
    public function setRealFullPath(string $realFullPath): void
    {
        $this->realFullPath = $realFullPath;
    }

    /**
     * @return array
     */
    public function getElements(): array
    {
        return $this->elements;
    }

    /**
     * @param array $elements
     */
    public function setElements(array $elements): void
    {
        $this->elements = array_filter(
            $elements,
            function ($element) {
                return array_key_exists('data', $element) &&
                    array_key_exists('type', $element) &&
                    in_array($element['type'], self::SUPPORTED_EDITABLE_TYPES);
            }
        );
    }

    /**
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @param array $properties
     */
    public function setProperties(array $properties): void
    {
        $this->properties = array_filter(
            $properties,
            function ($property) {
                return array_key_exists('data', $property) &&
                    array_key_exists('type', $property) &&
                    array_key_exists('inheritable', $property) &&
                    in_array($property['type'], self::SUPPORTED_PROPERTY_TYPES);
            }
        );
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * @param array $settings
     */
    public function setSettings(array $settings): void
    {
        $this->settings = array_filter(
            $settings,
            function ($key) {
                return in_array($key, self::SUPPORTED_SETTINGS);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return array
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * @param array $dependencies
     */
    public function setDependencies(array $dependencies): void
    {
        $this->dependencies = $dependencies;
    }

    /**  */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
