<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 15:51
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Tests\DocumentCopierBundle;

use Codeception\Test\Unit;
use Exception;
use FilesystemIterator;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
        return array_diff(json_decode($json, true), json_decode($otherJson, true));
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
    }

    /**
     * Contains assertions about entire document tree
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
        $this->assertEquals('@AppBundle\Controller\DocumentController', $document->getController());
        $this->assertEquals('default', $document->getAction());
        $this->assertEquals('Document/poc.html.twig', $document->getTemplate());
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
        $this->assertEquals('Block A', $document->getElement('myBlock:1.heading')->getData());
        $this->assertEquals('Text for block A', $document->getElement('myBlock:1.content')->getData());
        $this->assertEquals('Block B', $document->getElement('myBlock:2.heading')->getData());
        $this->assertEquals('Text for block B', $document->getElement('myBlock:2.content')->getData());


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
