<?php

namespace Modules\Flowmaker\Services;
require __DIR__.'/../vendor/autoload.php';


use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Row;
use PhpOffice\PhpWord\Element\Cell;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentParserService
{
    /**
     * Extract text from various document types
     */
    public function extractText(string $filePath, string $fileType): array
    {
        try {
            $content = '';
            $title = basename($filePath);

            switch (strtolower($fileType)) {
                case 'pdf':
                    $content = $this->extractFromPdf($filePath);
                    break;
                    
                case 'docx':
                case 'doc':
                    $content = $this->extractFromWord($filePath);
                    break;
                    
                case 'txt':
                    $content = $this->extractFromTxt($filePath);
                    break;
                    
                default:
                    throw new \Exception('Unsupported file type: ' . $fileType);
            }

            return [
                'title' => $title,
                'content' => $content,
                'file_type' => $fileType,
                'file_path' => $filePath
            ];

        } catch (\Exception $e) {
            Log::error('Error parsing document: ' . $e->getMessage(), [
                'file_path' => $filePath,
                'file_type' => $fileType
            ]);
            
            return [
                'title' => basename($filePath),
                'content' => '',
                'file_type' => $fileType,
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract text from PDF file
     */
    private function extractFromPdf(string $filePath): string
    {
        try {
            $parser = new PdfParser();
            
            // Handle both local and S3 storage
            Log::info('PDF file exists in public_uploads');
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ]
                ]);
                $content = file_get_contents($filePath, false, $context);
                $pdf = $parser->parseContent($content);
            
            $text = $pdf->getText();
            
            // Clean up the extracted text
            $text = preg_replace('/\s+/', ' ', $text); // Replace multiple spaces with single space
            $text = trim($text);
            
            return $text;
            
        } catch (\Exception $e) {
            Log::error('PDF parsing error: ' . $e->getMessage());
            throw new \Exception('Failed to parse PDF: ' . $e->getMessage());
        }
    }

    /**
     * Extract text from Word document
     */
    private function extractFromWord(string $filePath): string
    {
        try {
            // Set the default reader for PhpWord
            Settings::setOutputEscapingEnabled(true);
            
            // Handle both local and S3 storage
            Log::info('Word file exists in public_uploads');
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            $content = file_get_contents($filePath, false, $context);
            $tempPath = tempnam(sys_get_temp_dir(), 'word_doc_');
            file_put_contents($tempPath, $content);
            $fullPath = $tempPath;

            // Load the document
            $phpWord = IOFactory::load($fullPath);
            
            $text = '';
            
            // Extract text from all sections
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $text .= $this->extractTextFromElement($element) . "\n";
                }
            }
            
            // Clean up temporary file if created
            if (isset($tempPath) && file_exists($tempPath)) {
                unlink($tempPath);
            }
            
            // Clean up the extracted text
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);
            
            return $text;
            
        } catch (\Exception $e) {
            Log::error('Word document parsing error: ' . $e->getMessage());
            throw new \Exception('Failed to parse Word document: ' . $e->getMessage());
        }
    }

    /**
     * Extract text from a single element
     */
    private function extractTextFromElement(AbstractElement $element): string
    {
        $text = '';
        
        if ($element instanceof Text) {
            $text .= $element->getText();
        } elseif ($element instanceof TextRun) {
            foreach ($element->getElements() as $textElement) {
                if ($textElement instanceof Text) {
                    $text .= $textElement->getText();
                }
            }
        } elseif ($element instanceof Table) {
            foreach ($element->getRows() as $row) {
                if ($row instanceof Row) {
                    foreach ($row->getCells() as $cell) {
                        if ($cell instanceof Cell) {
                            foreach ($cell->getElements() as $cellElement) {
                                $text .= $this->extractTextFromElement($cellElement) . ' ';
                            }
                        }
                    }
                    $text .= "\n";
                }
            }
        } elseif (method_exists($element, 'getElements') && is_callable([$element, 'getElements'])) {
            $subElements = call_user_func([$element, 'getElements']);
            if (is_array($subElements) || $subElements instanceof \Traversable) {
                foreach ($subElements as $subElement) {
                    if ($subElement instanceof AbstractElement) {
                        $text .= $this->extractTextFromElement($subElement) . ' ';
                    }
                }
            }
        } elseif (method_exists($element, 'getText') && is_callable([$element, 'getText'])) {
            $elementText = call_user_func([$element, 'getText']);
            if (is_string($elementText)) {
                $text .= $elementText;
            }
        }
        
        return $text;
    }

    /**
     * Extract text from nested elements (like tables, paragraphs)
     */
    private function extractTextFromElements($elements): string
    {
        $text = '';
        
        foreach ($elements as $element) {
            if ($element instanceof AbstractElement) {
                $text .= $this->extractTextFromElement($element) . ' ';
            } elseif (method_exists($element, 'getText')) {
                $text .= $element->getText() . ' ';
            } elseif (method_exists($element, 'getElements')) {
                $text .= $this->extractTextFromElements($element->getElements()) . ' ';
            }
        }
        
        return $text;
    }

    /**
     * Extract text from TXT file
     */
    private function extractFromTxt(string $filePath): string
    {
        try {
            // Handle both local and S3 storage

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            $content = file_get_contents($filePath, false, $context);
            
            // Clean up the text
            $content = preg_replace('/\r\n|\r|\n/', "\n", $content); // Normalize line endings
            $content = preg_replace('/\n{3,}/', "\n\n", $content); // Replace multiple newlines
            $content = trim($content);
            
            return $content;
            
        } catch (\Exception $e) {
            Log::error('TXT file parsing error: ' . $e->getMessage());
            throw new \Exception('Failed to read TXT file: ' . $e->getMessage());
        }
    }
} 