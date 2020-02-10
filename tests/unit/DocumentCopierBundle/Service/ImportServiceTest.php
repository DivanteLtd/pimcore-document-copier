<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 14:54
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace unit\DocumentCopierBundle\Service;

use Codeception\Test\Unit;
use Divante\DocumentCopierBundle\DTO\PortableDocument;
use Divante\DocumentCopierBundle\Service\ImportService;
use Exception;
use Monolog\Logger;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Tests\UnitTester;

class ImportServiceTest extends Unit
{
    /** @var ImportService */
    private $importService;

    const DOCUMENT_JSON_PATH = '/documents/codecept-document-copier/foo/bar.json';
    const EMAIL_JSON_PATH = '/documents/codecept-document-copier/emails/dear-foo.json';

    /**
     * @throws Exception
     */
    public function testImport()
    {
        // given
        $dto = PortableDocument::fromJson(
            file_get_contents(UnitTester::getRootDirectory() . self::DOCUMENT_JSON_PATH)
        );

        // when
        $document = $this->importService->import($dto);

        // then
        /** @var Document\Page $document */
        $this->documentAssertions($document);
    }

    /**
     * @throws Exception
     */
    public function testOverwrite()
    {
        // given
        $doc1 = new Document\Page();
        $doc1->setKey('codecept-document-copier');
        $doc1->setParent(Document::getByPath('/'));
        $doc1->save();

        $doc2 = new Document\Page();
        $doc2->setKey('foo');
        $doc2->setParent($doc1);
        $doc2->save();

        $doc3 = new Document\Page();  // this document will be overwritten
        $doc3->setKey('bar');
        $doc3->setParent($doc2);
        $doc3->setTitle('overwrite me');
        $doc3->setProperty('navigation_name', 'text', 'overwrite me');
        $doc3->setProperty('my_property', 'text', 'still here');
        $element = Document\Tag::factory('input', 'myInput', $doc3->getId());
        $element->setDataFromResource('overwrite me');
        $doc3->setElement('myInput', $element);
        $doc3->save();

        $dto = PortableDocument::fromJson(
            file_get_contents(UnitTester::getRootDirectory() . self::DOCUMENT_JSON_PATH)
        );

        // when
        $document = $this->importService->import($dto);

        // then
        /** @var Document\Page $document */
        $this->documentAssertions($document);
        $this->assertEquals('still here', $document->getProperty('my_property'));
    }

    /**
     * @throws Exception
     */
    public function testOverwriteDifferentType()
    {
        // given
        $doc1 = new Document\Page();
        $doc1->setKey('codecept-document-copier');
        $doc1->setParent(Document::getByPath('/'));
        $doc1->save();

        $doc2 = new Document\Page();
        $doc2->setKey('foo');
        $doc2->setParent($doc1);
        $doc2->save();

        $doc3 = new Document\Snippet();  // this snippet will be changed to page and overwritten
        $doc3->setKey('bar');
        $doc3->setParent($doc2);
        $doc3->setProperty('navigation_name', 'text', 'overwrite me');
        $element = Document\Tag::factory('input', 'myInput', $doc3->getId());
        $element->setDataFromResource('overwrite me');
        $doc3->setElement('myInput', $element);
        $doc3->save();

        $dto = PortableDocument::fromJson(
            file_get_contents(UnitTester::getRootDirectory() . self::DOCUMENT_JSON_PATH)
        );

        // when
        $document = $this->importService->import($dto);

        // then
        /** @var Document\Page $document */
        $this->documentAssertions($document);
    }


    /**
     * @throws Exception
     */
    public function testImportEmail()
    {
        // given
        $dto = PortableDocument::fromJson(
            file_get_contents(UnitTester::getRootDirectory() . self::EMAIL_JSON_PATH)
        );

        // when
        $document = $this->importService->import($dto);

        // then
        /** @var Document\Email $document */
        $this->assertTrue($document instanceof Document\Email);
        $this->assertEquals('/codecept-document-copier/emails/dear-foo', $document->getRealFullPath());

        $this->assertEquals('Test e-mail', $document->getSubject());
        $this->assertEquals('sender@example.com', $document->getFrom());
        $this->assertEquals('reply@example.com', $document->getReplyTo());
        $this->assertEquals('recipient@example.com; otherrecipient@example.com', $document->getTo());
        $this->assertEquals('carboncopy@example.com', $document->getCc());
        $this->assertEquals('blankcopy@example.com; otherblankcopy@example.com', $document->getBcc());

        $this->assertNotNull($document->getElement('myInput'));
        $this->assertEquals('Hello world from e-mail', $document->getElement('myInput')->getData());
        $this->assertNotNull($document->getElement('myTextarea'));
        $this->assertEquals('Dear %Text(firstName)', $document->getElement('myTextarea')->getData());
    }

    /**
     * Contains assertions about entire document tree
     *
     * @param Document\Page $document
     * @param int $dependenciesDepth
     */
    public function documentAssertions(Document\Page $document, int $dependenciesDepth = 0)
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

    /**
     * @throws Exception
     */
    protected function _before()
    {
        UnitTester::cleanUp();

        $logger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->importService = $this->construct(ImportService::class, [$logger]);
    }

    /**
     * @throws Exception
     */
    protected function _after()
    {
        UnitTester::cleanUp();
    }
}
