<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\WatsonX;

use IBMCloud\Services\AI\Models\Results\TextExtractionResult;
use IBMCloud\Services\ObjectStorage\Client as COSClient;
use IBMCloud\Services\ObjectStorage\Models\Queries\ObjectQuery;
use IBMCloud\Services\ObjectStorage\ValueObjects\BucketName;
use IBMCloud\Services\ObjectStorage\ValueObjects\ObjectKey;

/**
 * Downloads text extraction results from IBM Cloud Object Storage.
 */
final class ResultsDownloader
{
    public function __construct(
        private readonly COSClient $cosClient
    ) {}

    /**
     * Download all results for a text extraction job.
     */
    public function downloadResults(
        TextExtractionResult $extractionResult,
        string $bucketName,
        string $localDirectory = './downloads'
    ): array {
        if (!$extractionResult->isCompleted()) {
            throw new \InvalidArgumentException('Extraction must be completed to download results');
        }

        $resultsReference = $extractionResult->getResultsReference();
        if (!$resultsReference) {
            throw new \InvalidArgumentException('No results reference found in extraction result');
        }

        $resultsPath = $resultsReference['location']['file_name'] ?? null;
        if (!$resultsPath) {
            throw new \InvalidArgumentException('No results path found in extraction result');
        }

        // Ensure local directory exists.
        if (!is_dir($localDirectory)) {
            if (!mkdir($localDirectory, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: $localDirectory");
            }
        }

        $downloadedFiles = [];
        $bucket = BucketName::from($bucketName);

        // Common result files to try downloading.
        $possibleFiles = [
            'assembly' => 'assembly.json',
            'md' => 'document.md', 
            'tables_json' => 'tables.json',
            'plain_text' => 'plain_text.txt',
            'metadata' => 'metadata.json'
        ];

        foreach ($possibleFiles as $type => $filename) {
            $remotePath = rtrim($resultsPath, '/') . '/' . $filename;
            $localPath = $localDirectory . '/' . $extractionResult->getId() . '_' . $filename;

            try {
                $query = ObjectQuery::for($bucket, ObjectKey::from($remotePath));
                $result = $this->cosClient->retrieve($query);
                
                file_put_contents($localPath, $result->content);
                $downloadedFiles[$type] = $localPath;
                
            } catch (\Exception $e) {
                // File might not exist for this extraction type, continue.
                continue;
            }
        }

        if (empty($downloadedFiles)) {
            throw new \RuntimeException('No result files found to download');
        }

        return $downloadedFiles;
    }

    /**
     * Download a specific result file.
     */
    public function downloadResultFile(
        TextExtractionResult $extractionResult,
        string $bucketName,
        string $filename,
        string $localPath
    ): bool {
        if (!$extractionResult->isCompleted()) {
            throw new \InvalidArgumentException('Extraction must be completed to download results');
        }

        $resultsReference = $extractionResult->getResultsReference();
        if (!$resultsReference) {
            throw new \InvalidArgumentException('No results reference found in extraction result');
        }

        $resultsPath = $resultsReference['location']['file_name'] ?? null;
        if (!$resultsPath) {
            throw new \InvalidArgumentException('No results path found in extraction result');
        }

        $remotePath = rtrim($resultsPath, '/') . '/' . $filename;
        
        try {
            $query = ObjectQuery::for(
                BucketName::from($bucketName), 
                ObjectKey::from($remotePath)
            );
            
            $result = $this->cosClient->retrieve($query);
            
            // Ensure local directory exists.
            $localDir = dirname($localPath);
            if (!is_dir($localDir)) {
                if (!mkdir($localDir, 0755, true)) {
                    throw new \RuntimeException("Failed to create directory: $localDir");
                }
            }
            
            file_put_contents($localPath, $result->content);
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * List available result files for an extraction.
     */
    public function listResultFiles(
        TextExtractionResult $extractionResult,
        string $bucketName
    ): array {
        if (!$extractionResult->isCompleted()) {
            return [];
        }

        $resultsReference = $extractionResult->getResultsReference();
        if (!$resultsReference) {
            return [];
        }

        $resultsPath = $resultsReference['location']['file_name'] ?? null;
        if (!$resultsPath) {
            return [];
        }

        $bucket = BucketName::from($bucketName);
        $availableFiles = [];

        // Check for common result files.
        $possibleFiles = [
            'assembly' => 'assembly.json',
            'md' => 'document.md', 
            'tables_json' => 'tables.json',
            'plain_text' => 'plain_text.txt',
            'metadata' => 'metadata.json'
        ];

        foreach ($possibleFiles as $type => $filename) {
            $remotePath = rtrim($resultsPath, '/') . '/' . $filename;
            
            try {
                $query = ObjectQuery::for($bucket, ObjectKey::from($remotePath));
                if ($this->cosClient->exists($query)) {
                    $availableFiles[$type] = [
                        'filename' => $filename,
                        'remote_path' => $remotePath,
                        'type' => $type
                    ];
                }
            } catch (\Exception $e) {
                // File doesn't exist, continue.
                continue;
            }
        }

        return $availableFiles;
    }

    /**
     * Get the raw content of a result file without saving to disk.
     */
    public function getResultContent(
        TextExtractionResult $extractionResult,
        string $bucketName,
        string $filename
    ): string {
        if (!$extractionResult->isCompleted()) {
            throw new \InvalidArgumentException('Extraction must be completed to get results');
        }

        $resultsReference = $extractionResult->getResultsReference();
        if (!$resultsReference) {
            throw new \InvalidArgumentException('No results reference found');
        }

        $resultsPath = $resultsReference['location']['file_name'] ?? null;
        if (!$resultsPath) {
            throw new \InvalidArgumentException('No results path found');
        }

        $remotePath = rtrim($resultsPath, '/') . '/' . $filename;
        
        $query = ObjectQuery::for(
            BucketName::from($bucketName), 
            ObjectKey::from($remotePath)
        );
        
        $result = $this->cosClient->retrieve($query);
        return $result->content;
    }

    /**
     * Parse JSON result file and return structured data.
     */
    public function parseJsonResult(
        TextExtractionResult $extractionResult,
        string $bucketName,
        string $jsonFilename = 'assembly.json'
    ): array {
        $content = $this->getResultContent($extractionResult, $bucketName, $jsonFilename);
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse JSON result: ' . json_last_error_msg());
        }
        
        return $data;
    }

    /**
     * Get tables data as structured array.
     */
    public function getTablesData(
        TextExtractionResult $extractionResult,
        string $bucketName
    ): array {
        try {
            return $this->parseJsonResult($extractionResult, $bucketName, 'tables.json');
        } catch (\Exception $e) {
            // Try assembly.json if tables.json doesn't exist.
            $assemblyData = $this->parseJsonResult($extractionResult, $bucketName, 'assembly.json');
            
            // Extract tables from assembly data.
            $tables = [];
            if (isset($assemblyData['elements'])) {
                foreach ($assemblyData['elements'] as $element) {
                    if (($element['type'] ?? '') === 'table') {
                        $tables[] = $element;
                    }
                }
            }
            
            return $tables;
        }
    }

    /**
     * Get extracted text content.
     */
    public function getTextContent(
        TextExtractionResult $extractionResult,
        string $bucketName
    ): string {
        // Try different text formats in order of preference.
        $textFiles = ['document.md', 'plain_text.txt'];
        
        foreach ($textFiles as $filename) {
            try {
                return $this->getResultContent($extractionResult, $bucketName, $filename);
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Fallback to assembly.json text extraction.
        try {
            $assemblyData = $this->parseJsonResult($extractionResult, $bucketName, 'assembly.json');
            
            $text = '';
            if (isset($assemblyData['elements'])) {
                foreach ($assemblyData['elements'] as $element) {
                    if (isset($element['text'])) {
                        $text .= $element['text'] . "\n";
                    }
                }
            }
            
            return trim($text);
        } catch (\Exception $e) {
            throw new \RuntimeException('No text content found in extraction results');
        }
    }
}