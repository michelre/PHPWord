<?php
/**
 * This file is part of PHPWord - A pure PHP library for reading and writing
 * word processing documents.
 *
 * PHPWord is free software distributed under the terms of the GNU Lesser
 * General Public License version 3 as published by the Free Software Foundation.
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code. For the full list of
 * contributors, visit https://github.com/PHPOffice/PHPWord/contributors.
 *
 * @see         https://github.com/PHPOffice/PHPWord
 * @copyright   2010-2018 PHPWord contributors
 * @license     http://www.gnu.org/licenses/lgpl.txt LGPL version 3
 */

namespace PhpOffice\PhpWord;

/**
 * @covers \PhpOffice\PhpWord\TemplateProcessor
 * @coversDefaultClass \PhpOffice\PhpWord\TemplateProcessor
 * @runTestsInSeparateProcesses
 */
final class TemplateProcessorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Template can be saved in temporary location.
     *
     * @covers ::save
     * @test
     */
    final public function testTemplateCanBeSavedInTemporaryLocation()
    {
        $templateFqfn = __DIR__ . '/_files/templates/with_table_macros.docx';

        $templateProcessor = new TemplateProcessor($templateFqfn);
        $xslDomDocument = new \DOMDocument();
        $xslDomDocument->load(__DIR__ . '/_files/xsl/remove_tables_by_needle.xsl');
        foreach (array('${employee.', '${scoreboard.', '${reference.') as $needle) {
            $templateProcessor->applyXslStyleSheet($xslDomDocument, array('needle' => $needle));
        }

        $documentFqfn = $templateProcessor->save();

        $this->assertNotEmpty($documentFqfn, 'FQFN of the saved document is empty.');
        $this->assertFileExists($documentFqfn, "The saved document \"{$documentFqfn}\" doesn't exist.");

        $templateZip = new \ZipArchive();
        $templateZip->open($templateFqfn);
        $templateHeaderXml = $templateZip->getFromName('word/header1.xml');
        $templateMainPartXml = $templateZip->getFromName('word/document.xml');
        $templateFooterXml = $templateZip->getFromName('word/footer1.xml');
        if (false === $templateZip->close()) {
            throw new \Exception("Could not close zip file \"{$templateZip}\".");
        }

        $documentZip = new \ZipArchive();
        $documentZip->open($documentFqfn);
        $documentHeaderXml = $documentZip->getFromName('word/header1.xml');
        $documentMainPartXml = $documentZip->getFromName('word/document.xml');
        $documentFooterXml = $documentZip->getFromName('word/footer1.xml');
        if (false === $documentZip->close()) {
            throw new \Exception("Could not close zip file \"{$documentZip}\".");
        }

        $this->assertNotEquals($templateHeaderXml, $documentHeaderXml);
        $this->assertNotEquals($templateMainPartXml, $documentMainPartXml);
        $this->assertNotEquals($templateFooterXml, $documentFooterXml);

        return $documentFqfn;
    }

    /**
     * XSL stylesheet can be applied.
     *
     * @test
     * @covers ::applyXslStyleSheet
     * @depends testTemplateCanBeSavedInTemporaryLocation
     *
     * @param string $actualDocumentFqfn
     *
     * @throws \Exception
     */
    final public function testXslStyleSheetCanBeApplied($actualDocumentFqfn)
    {
        $expectedDocumentFqfn = __DIR__ . '/_files/documents/without_table_macros.docx';

        $actualDocumentZip = new \ZipArchive();
        $actualDocumentZip->open($actualDocumentFqfn);
        $actualHeaderXml = $actualDocumentZip->getFromName('word/header1.xml');
        $actualMainPartXml = $actualDocumentZip->getFromName('word/document.xml');
        $actualFooterXml = $actualDocumentZip->getFromName('word/footer1.xml');
        if (false === $actualDocumentZip->close()) {
            throw new \Exception("Could not close zip file \"{$actualDocumentFqfn}\".");
        }

        $expectedDocumentZip = new \ZipArchive();
        $expectedDocumentZip->open($expectedDocumentFqfn);
        $expectedHeaderXml = $expectedDocumentZip->getFromName('word/header1.xml');
        $expectedMainPartXml = $expectedDocumentZip->getFromName('word/document.xml');
        $expectedFooterXml = $expectedDocumentZip->getFromName('word/footer1.xml');
        if (false === $expectedDocumentZip->close()) {
            throw new \Exception("Could not close zip file \"{$expectedDocumentFqfn}\".");
        }

        $this->assertXmlStringEqualsXmlString($expectedHeaderXml, $actualHeaderXml);
        $this->assertXmlStringEqualsXmlString($expectedMainPartXml, $actualMainPartXml);
        $this->assertXmlStringEqualsXmlString($expectedFooterXml, $actualFooterXml);
    }

    /**
     * XSL stylesheet cannot be applied on failure in setting parameter value.
     *
     * @covers                   ::applyXslStyleSheet
     * @expectedException        \PhpOffice\PhpWord\Exception\Exception
     * @expectedExceptionMessage Could not set values for the given XSL style sheet parameters.
     * @test
     */
    final public function testXslStyleSheetCanNotBeAppliedOnFailureOfSettingParameterValue()
    {
        $templateProcessor = new TemplateProcessor(__DIR__ . '/_files/templates/blank.docx');

        $xslDomDocument = new \DOMDocument();
        $xslDomDocument->load(__DIR__ . '/_files/xsl/passthrough.xsl');

        /*
         * We have to use error control below, because \XSLTProcessor::setParameter omits warning on failure.
         * This warning fails the test.
         */
        @$templateProcessor->applyXslStyleSheet($xslDomDocument, array(1 => 'somevalue'));
    }

    /**
     * XSL stylesheet can be applied on failure of loading XML from template.
     *
     * @covers                   ::applyXslStyleSheet
     * @expectedException        \PhpOffice\PhpWord\Exception\Exception
     * @expectedExceptionMessage Could not load the given XML document.
     * @test
     */
    final public function testXslStyleSheetCanNotBeAppliedOnFailureOfLoadingXmlFromTemplate()
    {
        $templateProcessor = new TemplateProcessor(__DIR__ . '/_files/templates/corrupted_main_document_part.docx');

        $xslDomDocument = new \DOMDocument();
        $xslDomDocument->load(__DIR__ . '/_files/xsl/passthrough.xsl');

        /*
         * We have to use error control below, because \DOMDocument::loadXML omits warning on failure.
         * This warning fails the test.
         */
        @$templateProcessor->applyXslStyleSheet($xslDomDocument);
    }

    /**
     * @covers ::setValue
     * @covers ::cloneRow
     * @covers ::saveAs
     * @test
     */
    public function testCloneRow()
    {
        $templateProcessor = new TemplateProcessor(__DIR__ . '/_files/templates/clone-merge.docx');

        $this->assertEquals(
            array('tableHeader', 'userId', 'userName', 'userLocation'),
            $templateProcessor->getVariables()
        );

        $docName = 'clone-test-result.docx';
        $templateProcessor->setValue('tableHeader', utf8_decode('ééé'));
        $templateProcessor->cloneRow('userId', 1);
        $templateProcessor->setValue('userId#1', 'Test');
        $templateProcessor->saveAs($docName);
        $docFound = file_exists($docName);
        unlink($docName);
        $this->assertTrue($docFound);
    }

    /**
     * @covers ::setValue
     * @covers ::saveAs
     * @test
     */
    public function testMacrosCanBeReplacedInHeaderAndFooter()
    {
        $templateProcessor = new TemplateProcessor(__DIR__ . '/_files/templates/header-footer.docx');

        $this->assertEquals(array('documentContent', 'headerValue:100:100', 'footerValue'), $templateProcessor->getVariables());

        $macroNames = array('headerValue', 'documentContent', 'footerValue');
        $macroValues = array('Header Value', 'Document text.', 'Footer Value');
        $templateProcessor->setValue($macroNames, $macroValues);

        $docName = 'header-footer-test-result.docx';
        $templateProcessor->saveAs($docName);
        $docFound = file_exists($docName);
        unlink($docName);
        $this->assertTrue($docFound);
    }

    /**
     * @covers ::setImageValue
     * @test
     */
    public function testSetImageValue()
    {
        $templateProcessor = new TemplateProcessor(__DIR__ . '/_files/templates/header-footer.docx');
        $imagePath = __DIR__ . '/_files/images/earth.jpg';

        $variablesReplace = array(
                                'headerValue'       => $imagePath,
                                'documentContent'   => array('path' => $imagePath, 'width' => 500, 'height' => 500),
                                'footerValue'       => array('path' => $imagePath, 'width' => 100, 'height' => 50, 'ratio' => false),
        );
        $templateProcessor->setImageValue(array_keys($variablesReplace), $variablesReplace);

        $docName = 'header-footer-images-test-result.docx';
        $templateProcessor->saveAs($docName);
        $docFound = file_exists($docName);

        if ($docFound) {
            $expectedDocumentZip = new \ZipArchive();
            $expectedDocumentZip->open($docName);
            $expectedContentTypesXml = $expectedDocumentZip->getFromName('[Content_Types].xml');
            $expectedDocumentRelationsXml = $expectedDocumentZip->getFromName('word/_rels/document.xml.rels');
            $expectedHeaderRelationsXml = $expectedDocumentZip->getFromName('word/_rels/header1.xml.rels');
            $expectedFooterRelationsXml = $expectedDocumentZip->getFromName('word/_rels/footer1.xml.rels');
            $expectedMainPartXml = $expectedDocumentZip->getFromName('word/document.xml');
            $expectedHeaderPartXml = $expectedDocumentZip->getFromName('word/header1.xml');
            $expectedFooterPartXml = $expectedDocumentZip->getFromName('word/footer1.xml');
            $expectedImage = $expectedDocumentZip->getFromName('word/media/image_rId11_document.jpeg');
            if (false === $expectedDocumentZip->close()) {
                throw new \Exception("Could not close zip file \"{$docName}\".");
            }

            $this->assertTrue(!empty($expectedImage), 'Embed image doesn\'t found.');
            $this->assertTrue(strpos($expectedContentTypesXml, '/word/media/image_rId11_document.jpeg') > 0, '[Content_Types].xml missed "/word/media/image5_document.jpeg"');
            $this->assertTrue(strpos($expectedContentTypesXml, '/word/_rels/header1.xml.rels') > 0, '[Content_Types].xml missed "/word/_rels/header1.xml.rels"');
            $this->assertTrue(strpos($expectedContentTypesXml, '/word/_rels/footer1.xml.rels') > 0, '[Content_Types].xml missed "/word/_rels/footer1.xml.rels"');
            $this->assertTrue(strpos($expectedMainPartXml, '${documentContent}') === false, 'word/document.xml has no image.');
            $this->assertTrue(strpos($expectedHeaderPartXml, '${headerValue}') === false, 'word/header1.xml has no image.');
            $this->assertTrue(strpos($expectedFooterPartXml, '${footerValue}') === false, 'word/footer1.xml has no image.');
            $this->assertTrue(strpos($expectedDocumentRelationsXml, 'media/image_rId11_document.jpeg') > 0, 'word/_rels/document.xml.rels missed "media/image5_document.jpeg"');
            $this->assertTrue(strpos($expectedHeaderRelationsXml, 'media/image_rId11_document.jpeg') > 0, 'word/_rels/header1.xml.rels missed "media/image5_document.jpeg"');
            $this->assertTrue(strpos($expectedFooterRelationsXml, 'media/image_rId11_document.jpeg') > 0, 'word/_rels/footer1.xml.rels missed "media/image5_document.jpeg"');

            unlink($docName);
        } else {
            throw new \Exception("Generated file '{$docName}' not found!");
        }

        // dynamic generated doc
        $testFileName = 'images-test-sample.docx';
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $section->addText('${Test} --- ${Test}');
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($testFileName);
        if (!file_exists($testFileName)) {
            throw new \Exception("Generated file '{$testFileName}' not found!");
            return;
        }

        $resultFileName = 'images-test-result.docx';
        $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($testFileName);
        unlink($testFileName);
        $templateProcessor->setImageValue('Test', $imagePath);
        $templateProcessor->saveAs($resultFileName);
        if (!file_exists($resultFileName)) {
            throw new \Exception("Generated file '{$resultFileName}' not found!");
            return;
        }

        $expectedDocumentZip = new \ZipArchive();
        $expectedDocumentZip->open($resultFileName);
        $expectedMainPartXml = $expectedDocumentZip->getFromName('word/document.xml');
        if (false === $expectedDocumentZip->close()) {
            throw new \Exception("Could not close zip file \"{$resultFileName}\".");
        }
        unlink($resultFileName);

        $this->assertTrue(strpos($expectedMainPartXml, '${Test}') === false, 'word/document.xml has no image.');
    }

    /**
     * @covers ::cloneBlock
     * @covers ::deleteBlock
     * @covers ::saveAs
     * @test
     */
    public function testCloneDeleteBlock()
    {
        $templateProcessor = new TemplateProcessor(__DIR__ . '/_files/templates/clone-delete-block.docx');

        $this->assertEquals(
            array('DELETEME', '/DELETEME', 'CLONEME', '/CLONEME'),
            $templateProcessor->getVariables()
        );

        $docName = 'clone-delete-block-result.docx';
        $templateProcessor->cloneBlock('CLONEME', 3);
        $templateProcessor->deleteBlock('DELETEME');
        $templateProcessor->saveAs($docName);
        $docFound = file_exists($docName);
        unlink($docName);
        $this->assertTrue($docFound);
    }

    /**
     * @covers ::cloneBlock
     * @test
     */
    public function cloneBlockCanCloneABlockTwice()
    {
        // create template with placeholders and block
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $documentElements = array(
            'Title: ${title}',
            '${subreport}',
            '${subreport.id}: ${subreport.text}. ',
            '${/subreport}',
        );
        foreach ($documentElements as $documentElement) {
            $section->addText($documentElement);
        }
        $objWriter = IOFactory::createWriter($phpWord);
        $templatePath = 'test.docx';
        $objWriter->save($templatePath);

        // replace placeholders and save the file
        $templateProcessor = new TemplateProcessor($templatePath);
        $templateProcessor->setValue('title', 'Some title');
        $templateProcessor->cloneBlock('subreport', 2);
        $templateProcessor->setValue('subreport.id', '123', 1);
        $templateProcessor->setValue('subreport.text', 'Some text', 1);
        $templateProcessor->setValue('subreport.id', '456', 1);
        $templateProcessor->setValue('subreport.text', 'Some other text', 1);
        $templateProcessor->saveAs($templatePath);

        // assert the block has been cloned twice
        // and the placeholders have been replaced correctly
        $phpWord = IOFactory::load($templatePath);
        $sections = $phpWord->getSections();
        /** @var \PhpOffice\PhpWord\Element\TextRun[] $actualElements */
        $actualElements = $sections[0]->getElements();
        unlink($templatePath);
        $expectedElements = array(
            'Title: Some title',
            '123: Some text. ',
            '456: Some other text. ',
        );
        $this->assertCount(count($expectedElements), $actualElements);
        foreach ($expectedElements as $i => $expectedElement) {
            $this->assertEquals(
                $expectedElement,
                $actualElements[$i]->getElement(0)->getText()
            );
        }
    }
}
