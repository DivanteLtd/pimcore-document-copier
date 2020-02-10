<?php
/**
 * @category    pimcore-document-copier
 * @date        10/02/2020 14:54
 * @author      Pascal Dunaj <pdunaj@divante.pl>
 * @copyright   Copyright (c) 2020 Divante Ltd. (https://divante.co)
 */

declare(strict_types=1);

namespace Tests\DocumentCopierBundle\Service;

use Divante\DocumentCopierBundle\DTO\PortableDocument;
use Divante\DocumentCopierBundle\Service\ImportService;
use Exception;
use Monolog\Logger;
use Pimcore\Model\Document;
use Tests\DocumentCopierBundle\AbstractDocumentCopierTest;

class ImportServiceTest extends AbstractDocumentCopierTest
{
    /** @var ImportService */
    private $importService;


    /**
     * @throws Exception
     */
    public function testImport()
    {
        // given
        $dto = PortableDocument::fromJson(
            file_get_contents($this->getRootDirectory() . self::DOCUMENT_JSON_PATH)
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
            file_get_contents($this->getRootDirectory() . self::DOCUMENT_JSON_PATH)
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
            file_get_contents($this->getRootDirectory() . self::DOCUMENT_JSON_PATH)
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
            file_get_contents($this->getRootDirectory() . self::EMAIL_JSON_PATH)
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
     * @throws Exception
     */
    protected function _before()
    {
        $this->cleanUp();

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
        $this->cleanUp();
    }
}
