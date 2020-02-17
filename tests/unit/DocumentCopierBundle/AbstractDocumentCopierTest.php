<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 15:51
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Tests\Unit\DocumentCopierBundle;

use Codeception\Test\Unit;
use Exception;
use Pimcore\Cache\Runtime;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use RuntimeException;

abstract class AbstractDocumentCopierTest extends Unit
{
    const DOCUMENT_PATH = '/codecept-document-copier/foo/bar';
    const INVALID_DOCUMENT_PATH = '/codecept-document-copier/foo/not-bar';
    const ASSET_PATH = '/codecept-document-copier/my-dir/my-asset.png';
    const INVALID_ASSET_PATH = '/codecept-document-copier/my-dir/not-my-asset.png';

    const DOCUMENT_JSON_PATH = '/documents/codecept-document-copier/foo/bar.json';
    const LINK_JSON_PATH = '/documents/codecept-document-copier/links/internal-link.json';
    const HARDLINK_JSON_PATH = '/documents/codecept-document-copier/links/hardlink-with-inheritance.json';
    const EMAIL_JSON_PATH = '/documents/codecept-document-copier/emails/dear-foo.json';

    /**
     * @return string
     */
    protected function getRootDirectory(): string
    {
        return __DIR__ . '/../../../app/Resources/test_root';
    }

    /**
     * @return string
     */
    protected function getNewRootDirectory(): string
    {
        return __DIR__ . '/../../../app/Resources/new_root';
    }

    /**  */
    protected function clearNewRootDirectory(): void
    {
        $dir = $this->getNewRootDirectory();

        system("rm -rf " . escapeshellarg($dir));

        if (is_dir($dir)) {
            throw new RuntimeException('Could not delete directory ' . $dir);
        }
    }

    /**
     * @param string $json
     * @param string $otherJson
     * @return array
     */
    protected function jsonDiff(string $json, string $otherJson): array
    {
        $decoded = json_decode($json, true);
        $otherDecoded = json_decode($otherJson, true);

        if (is_array($decoded) && is_array($otherDecoded)) {
            return array_diff($decoded, $otherDecoded);
        } else {
            return [];
        }
    }

    /**
     * @throws Exception
     */
    protected function cleanUp(): void
    {
        $documentRoot = Document::getByPath('/codecept-document-copier');

        if ($documentRoot) {
            $documentRoot->delete();
        }

        $assetRoot = Asset::getByPath('/codecept-document-copier');

        if ($assetRoot) {
            $assetRoot->delete();
        }

        $this->clearNewRootDirectory();

        Runtime::clear();
    }

    /**
     * Contains assertions about /codecept-document-copier/foo/bar and its dependencies
     *
     * @param Document\Page $document
     * @param int $dependenciesDepth
     */
    protected function documentAssertions(Document\Page $document, int $dependenciesDepth = 0)
    {
        // Document type and path
        $this->assertTrue($document instanceof Document\Page);
        $this->assertEquals('/codecept-document-copier/foo/bar', $document->getRealFullPath());

        // Settings
        $this->assertEquals('My Title', $document->getTitle());
        $this->assertEquals('Żółć', $document->getDescription());
        $this->assertEquals('/myprettyurl', $document->getPrettyUrl());
        $this->assertEquals('DocumentCopierBundle', $document->getModule());
        $this->assertEquals('@Divante\DocumentCopierBundle\Controller\DocumentController', $document->getController());
        $this->assertEquals('default', $document->getAction());
        $this->assertEquals('DocumentCopierBundle:Document:poc.html.twig', $document->getTemplate());
        $this->assertEquals(true, boolval($document->isPublished()));

        // Properties
        $this->assertEquals('test', $document->getProperty('navigation_name'));
        $this->assertEquals('testing', $document->getProperty('textProp'));
        $this->assertTrue($document->getProperty('checkboxProp'));
        $this->assertFalse($document->getProperty('uncheckedProp'));

        // Elements
        $this->assertEquals(false, $document->getElement('myCheckbox')->getData());
        $this->assertEquals(1576450800, $document->getElement('myDate1')->getData()->getTimestamp());
        $this->assertEquals(1576490449, $document->getElement('myDate2')->getData()->getTimestamp());
        $this->assertEquals('hello world', $document->getElement('myInput')->getData());
        $this->assertEquals('/foo/baz', $document->getElement('myLink1')->getData()['path']);
        $this->assertEquals('Click me', $document->getElement('myLink1')->getData()['text']);
        $this->assertEquals('https://example.com', $document->getElement('myLink2')->getData()['path']);
        $this->assertEquals('Example', $document->getElement('myLink2')->getData()['text']);
        $this->assertContains('a', $document->getElement('myMultiselect')->getData());
        $this->assertContains('b', $document->getElement('myMultiselect')->getData());
        $this->assertCount(2, $document->getElement('myMultiselect')->getData());
        $this->assertEquals('14', $document->getElement('myNumeric')->getData());
        $this->assertEquals('b', $document->getElement('mySelect')->getData());
        $this->assertEquals([["A1", " A2"], [" B1", "B2 "], [" ", " "]], $document->getElement('myTable')->getData());
        $this->assertEquals('lorem ipsum', $document->getElement('myTextarea')->getData());
        $this->assertContains('<p>dolor sit amet</p>', $document->getElement('myWysiwyg')->getData());
        $this->assertEquals(['1', '2'], $document->getElement('myBlock')->getData());

        /** @var Document\Tag\Block\Item[] $block */
        $blocks = $document->getElement('myBlock')->getElements();
        $this->assertCount(2, $blocks);
        $this->assertEquals('Block A', $blocks[0]->getElement('heading')->getData());
        $this->assertEquals('Text for block A', $blocks[0]->getElement('content')->getData());
        $this->assertEquals('Block B', $blocks[1]->getElement('heading')->getData());
        $this->assertEquals('Text for block B', $blocks[1]->getElement('content')->getData());

        /** @var Document\Tag\Area $areaA */
        $areaA = $document->getElement('myArea');
        $this->assertEquals('Area A', $areaA->getElement('heading')->getData());
        $this->assertEquals('Text for area A', $areaA->getElement('content')->getData());

        /** @var Document\Tag\Area $areaB */
        $areaB = $document->getElement('myNestedArea');
        $this->assertEquals('Area B', $areaB->getElement('heading')->getData());

        /** @var Document\Tag\Area $areaC */
        $areaC = $areaB->getElement('content');
        $this->assertEquals('Area C', $areaC->getElement('heading')->getData());
        $this->assertEquals('Text for area C', $areaC->getElement('content')->getData());

        /** @var Document\Tag\Areablock $areablock */
        $areablock = $document->getElement('myAreablock');
        $this->assertCount(3, $areablock->getData());
        $this->assertTrue($areablock->getData()[1]['hidden']);

        /** @var Document\Tag\Area $areaD */
        $areaD = $areablock->getElement('example-areabrick')[0];
        $this->assertEquals('Area D', $areaD->getElement('heading')->getData());
        $this->assertEquals('Text for area D', $areaD->getElement('content')->getData());

        /** @var Document\Tag\Area $areaE */
        $areaE = $areablock->getElement('example-areabrick')[1];
        $this->assertEquals('Area E', $areaE->getElement('heading')->getData());
        $this->assertEquals('Text for area E', $areaE->getElement('content')->getData());

        /** @var Document\Tag\Area $areaF */
        $areaF = $areablock->getElement('nested-areabrick')[2];
        $this->assertEquals('Area F', $areaF->getElement('heading')->getData());

        /** @var Document\Tag\Area $areaG */
        $areaG = $areaF->getElement('content');
        $this->assertEquals('Area G', $areaG->getElement('heading')->getData());
        $this->assertEquals('Text for area G', $areaG->getElement('content')->getData());

        /** @var Document\Tag\Scheduledblock $scheduledBlock */
        $scheduledBlock = $document->getElement('myScheduledBlock');
        $this->assertCount(2, $scheduledBlock->getData());
        $this->assertEquals(0, $scheduledBlock->getData()[0]['key']);
        $this->assertEquals(1581937240, $scheduledBlock->getData()[0]['date']);
        $this->assertEquals(1, $scheduledBlock->getData()[1]['key']);
        $this->assertEquals(1581973240, $scheduledBlock->getData()[1]['date']);

        /** @var Document\Tag\Block\Item[] $scheduledBlocks */
        $scheduledBlocks = $document->getElement('myScheduledBlock')->getElements();
        $this->assertCount(2, $scheduledBlocks);
        $this->assertEquals('Schedule A', $scheduledBlocks[0]->getElement('heading')->getData());
        $this->assertEquals('Text for schedule A', $scheduledBlocks[0]->getElement('content')->getData());
        $this->assertEquals('Schedule B', $scheduledBlocks[1]->getElement('heading')->getData());
        $this->assertEquals('Text for schedule B', $scheduledBlocks[1]->getElement('content')->getData());

        if ($dependenciesDepth >= 1) {
            // Elements
            /** @var Document\Tag\Image $imageElement */
            $imageElement = $document->getElement('myImage');
            $this->assertEquals('image', $imageElement->getType());
            $this->assertIsInt($imageElement->getData()['id']);

            $image = Asset\Image::getById($imageElement->getData()['id']);
            $this->assertNotNull($image);
            $this->assertEquals('my-asset.png', $image->getFilename());
            $this->assertEquals('/codecept-document-copier/my-dir/my-asset.png', $image->getFullPath());

            /** @var Document\Tag\Snippet $snippetElement */
            $snippetElement = $document->getElement('mySnippet');
            $this->assertEquals('snippet', $snippetElement->getType());
            $this->assertIsInt($snippetElement->getData());

            $snippet = Document::getById($snippetElement->getData());
            $this->assertNotNull($snippet);
            $this->assertEquals('snip', $snippet->getKey());
            $this->assertEquals('/codecept-document-copier/snippets/snip', $snippet->getRealFullPath());
            $this->assertEquals('Hello world from snippet', $snippet->getElement('myInput')->getData());

            // Children
            $this->assertCount(1, $document->getChildren());
            /** @var Document\Page $child */
            $child = $document->getChildren()[0];
            $this->assertEquals('/codecept-document-copier/foo/bar/bar-child', $child->getRealFullPath());
            $this->assertEquals('Hello world from child document', $child->getElement('myInput')->getData());

            // Properties
            $docProp = $document->getProperty('docProp');
            $this->assertNotNull($docProp);
            $this->assertEquals('/codecept-document-copier/foo/baz', $docProp->getRealFullPath());
            $this->assertEquals('Hello world from baz', $docProp->getElement('myInput')->getData());

            $assetProp = $document->getProperty('assetProp');
            $this->assertNotNull($assetProp);
            $this->assertEquals('my-asset.png', $assetProp->getFilename());
            $this->assertEquals('/codecept-document-copier/my-dir/my-asset.png', $assetProp->getFullPath());

            // Dependencies
            if ($dependenciesDepth >= 2) {
                /** @var Document\Tag\Snippet $anotherSnippetElement */
                $anotherSnippetElement = $child->getElement('mySnippet');
                $this->assertEquals('snippet', $anotherSnippetElement->getType());
                $this->assertIsInt($anotherSnippetElement->getData());

                $anotherSnippet = Document::getById($anotherSnippetElement->getData());
                $this->assertNotNull($anotherSnippet);
                $this->assertEquals('another-snip', $anotherSnippet->getKey());
                $this->assertEquals(
                    'Hello world from another snippet',
                    $anotherSnippet->getElement('myInput')->getData()
                );

                $this->assertNotNull($docProp->getProperty('selfReferencingProp'));
                $this->assertNotNull($docProp->getProperty('loopProp'));

                if ($dependenciesDepth >= 3) {
                    $repeatedSnippetElement = $anotherSnippet->getElement('mySnippet');
                    $this->assertEquals('snippet', $repeatedSnippetElement->getType());
                    $this->assertIsInt($repeatedSnippetElement->getData());
                    $this->assertEquals($snippetElement->getData(), $repeatedSnippetElement->getData());
                }
            }
        }
    }
}
