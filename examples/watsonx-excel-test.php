<?php

require_once __DIR__ . '/../vendor/autoload.php';

use IBMCloud\Authentication\Strategies\IamStrategy;
use IBMCloud\Authentication\ValueObjects\ApiKey;
use IBMCloud\Services\AI\WatsonX\Client;
use IBMCloud\Services\AI\WatsonX\ResultsDownloader;
use IBMCloud\Services\AI\Models\Requests\TextExtractionRequest;
use IBMCloud\Services\AI\Models\TextExtraction\TextExtractionDataReference;
use IBMCloud\Services\AI\Models\TextExtraction\TextExtractionParameters;
use IBMCloud\Services\ObjectStorage\Client as COSClient;
use IBMCloud\Services\ObjectStorage\Models\Commands\ObjectCommand;
use IBMCloud\Services\ObjectStorage\ValueObjects\BucketName;
use IBMCloud\Services\ObjectStorage\ValueObjects\ObjectKey;
use IBMCloud\Transport\HttpTransport;
use IBMCloud\Transport\Middleware\AuthenticationMiddleware;
use IBMCloud\Transport\Middleware\LoggingMiddleware;
use Dotenv\Dotenv;

// Load environment variables.
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

echo "WatsonX.ai Excel Processing Test\n";
echo "===============================\n\n";

try {
    // Setup authentication.
    $apiKey = ApiKey::fromEnvironment('IBM_API_KEY');
    $transport = new HttpTransport();
    $iamAuth = new IamStrategy($apiKey, $transport);
    
    $authenticatedTransport = $transport
        ->withMiddleware(new AuthenticationMiddleware($iamAuth))
        ->withMiddleware(new LoggingMiddleware());
    
    $watsonxEndpoint = $_ENV['IBM_WATSONX_URL'] ?? 'https://us-south.ml.cloud.ibm.com';
    $watsonx = new Client($authenticatedTransport, $watsonxEndpoint);
    
    $projectId = $_ENV['IBM_WATSONX_PROJECT_ID'] ?? null;
    if (!$projectId) {
        throw new Exception('IBM_WATSONX_PROJECT_ID environment variable is required');
    }
    
    // Setup COS client.
    $cosEndpoint = $_ENV['IBM_COS_ENDPOINT'] ?? throw new Exception('Missing IBM_COS_ENDPOINT');
    $cosServiceInstanceId = $_ENV['IBM_COS_SERVICE_INSTANCE_ID'] ?? throw new Exception('Missing IBM_COS_SERVICE_INSTANCE_ID');
    $bucketName = $_ENV['TEST_BUCKET_NAME'] ?? throw new Exception('Missing TEST_BUCKET_NAME');
    $cosClient = new COSClient($authenticatedTransport, $cosEndpoint, $cosServiceInstanceId);
    
    $documentConnectionId = $_ENV['IBM_DOCUMENT_CONNECTION_ID'] ?? throw new Exception('Missing IBM_DOCUMENT_CONNECTION_ID');
    $resultsConnectionId = $_ENV['IBM_RESULTS_CONNECTION_ID'] ?? throw new Exception('Missing IBM_RESULTS_CONNECTION_ID');
    
    echo "✓ Authentication configured\n";
    echo "✓ Using WatsonX endpoint: $watsonxEndpoint\n";
    echo "✓ Using COS endpoint: $cosEndpoint\n";
    echo "✓ Project ID: $projectId\n";
    echo "✓ Bucket: $bucketName\n\n";

    // Process Excel file.
    $excelFile = __DIR__ . '/files/Basingstoke - Categories and descriptions.xlsx';
    if (!file_exists($excelFile)) {
        throw new Exception("Excel file not found: $excelFile");
    }
    
    echo "Processing Excel file: " . basename($excelFile) . "\n";
    echo "File size: " . number_format(filesize($excelFile)) . " bytes\n\n";
    
    $remoteKey = 'excel_processing/excel_' . time() . '.xlsx';
    $jobId = null;
    
    try {
        // Step 1: Upload Excel file to COS.
        echo "1. Uploading Excel file to COS...\n";
        
        $excelContent = file_get_contents($excelFile);
        
        $command = ObjectCommand::store(
            bucket: BucketName::from($bucketName),
            key: ObjectKey::from($remoteKey),
            content: $excelContent,
            contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        
        $cosClient->store($command);
        echo "   ✓ File uploaded to COS: $remoteKey\n\n";
        
        // Step 2: Create comprehensive text extraction job.
        echo "2. Creating comprehensive text extraction job...\n";
        
        $documentRef = TextExtractionDataReference::connectionAsset(
            connectionId: $documentConnectionId,
            fileName: $remoteKey
        );
        
        $resultsRef = TextExtractionDataReference::connectionAsset(
            connectionId: $resultsConnectionId,
            fileName: 'results/excel_comprehensive_' . time() . '/'
        );
        
        // Use comprehensive parameters for Excel.
        $comprehensiveParams = TextExtractionParameters::comprehensive();
        
        $request = TextExtractionRequest::create($documentRef, $resultsRef)
            ->withProjectId($projectId)
            ->withParameters($comprehensiveParams);
        
        $result = $watsonx->extractText($request);
        $jobId = $result->getId();
        
        echo "   ✓ Job created: $jobId\n";
        echo "   ✓ Status: " . $result->getStatus() . "\n";
        echo "   ✓ Outputs: " . implode(', ', $comprehensiveParams->getRequestedOutputs()) . "\n";
        echo "   ✓ Mode: " . $comprehensiveParams->getMode() . "\n";
        echo "   ✓ Tables processing: " . ($comprehensiveParams->isTablesProcessingEnabled() ? 'enabled' : 'disabled') . "\n";
        echo "   ✓ Results will be saved to: " . $resultsRef->getLocation()['file_name'] . "\n\n";
        
        // Step 3: Monitor job progress with detailed status updates.
        echo "3. Monitoring job progress...\n";
        
        $maxAttempts = 30; // 150 seconds max (Excel files can take longer).
        $attempt = 0;
        $lastStatus = '';
        
        do {
            sleep(5);
            $attempt++;
            
            $statusResult = $watsonx->getTextExtraction($jobId, $projectId);
            $currentStatus = $statusResult->getStatus();
            
            // Only show status if it changed.
            if ($currentStatus !== $lastStatus) {
                echo "   → Attempt $attempt: $currentStatus\n";
                
                if ($statusResult->getNumberPagesProcessed() !== null) {
                    echo "   → Pages processed: " . $statusResult->getNumberPagesProcessed() . "\n";
                }
                
                if ($currentStatus === 'running' && $statusResult->getRunningAt()) {
                    $duration = time() - $statusResult->getRunningAt()->getTimestamp();
                    echo "   → Running for: {$duration}s\n";
                }
                
                $lastStatus = $currentStatus;
            } else {
                echo "   → Attempt $attempt: $currentStatus (waiting...)\n";
            }
            
            if ($statusResult->isFinished()) {
                break;
            }
            
        } while ($attempt < $maxAttempts);
        
        // Step 4: Show final results.
        echo "\n4. Final Results:\n";
        
        if ($statusResult->isCompleted()) {
            echo "   🎉 Text extraction completed successfully!\n";
            
            if ($statusResult->getNumberPagesProcessed() !== null) {
                echo "   → Pages processed: " . $statusResult->getNumberPagesProcessed() . "\n";
            }
            
            if ($statusResult->getProcessingDuration()) {
                echo "   → Processing time: " . round($statusResult->getProcessingDuration(), 2) . "s\n";
            }
            
            if ($statusResult->getTotalDuration()) {
                echo "   → Total time: " . round($statusResult->getTotalDuration(), 2) . "s\n";
            }
            
            echo "\n   📁 Output Files Generated:\n";
            echo "   • assembly - Structured document assembly\n";
            echo "   • md - Markdown formatted text\n";
            echo "   • tables_json - JSON format tables data\n";
            
            echo "\n   📥 Downloading Results:\n";
            
            // Download and analyze results.
            $downloader = new ResultsDownloader($cosClient);
            
            try {
                // List available files.
                $availableFiles = $downloader->listResultFiles($statusResult, $bucketName);
                echo "   → Available files: " . implode(', ', array_keys($availableFiles)) . "\n";
                
                // Download all results to local directory.
                $downloadDir = './downloads/excel_' . time();
                $downloadedFiles = $downloader->downloadResults($statusResult, $bucketName, $downloadDir);
                
                echo "   ✓ Downloaded " . count($downloadedFiles) . " files to: $downloadDir\n";
                
                foreach ($downloadedFiles as $type => $localPath) {
                    $fileSize = number_format(filesize($localPath));
                    echo "   → $type: " . basename($localPath) . " ($fileSize bytes)\n";
                }
                
                // Analyze specific content.
                echo "\n   📊 Content Analysis:\n";
                
                // Get text content.
                try {
                    $textContent = $downloader->getTextContent($statusResult, $bucketName);
                    $wordCount = str_word_count($textContent);
                    $lineCount = substr_count($textContent, "\n") + 1;
                    echo "   → Text content: $wordCount words, $lineCount lines\n";
                } catch (Exception $e) {
                    echo "   ⚠ No text content available\n";
                }
                
                // Get tables data.
                try {
                    $tablesData = $downloader->getTablesData($statusResult, $bucketName);
                    echo "   → Tables found: " . count($tablesData) . "\n";
                    
                    foreach ($tablesData as $i => $table) {
                        if (isset($table['cells'])) {
                            $cellCount = count($table['cells']);
                            echo "   → Table " . ($i + 1) . ": $cellCount cells\n";
                        }
                    }
                } catch (Exception $e) {
                    echo "   ⚠ No tables data available\n";
                }
                
                // Show sample content.
                if (isset($downloadedFiles['md'])) {
                    $mdContent = file_get_contents($downloadedFiles['md']);
                    $preview = substr($mdContent, 0, 200);
                    echo "\n   📝 Markdown Preview:\n";
                    echo "   " . str_replace("\n", "\n   ", $preview) . "\n";
                    if (strlen($mdContent) > 200) {
                        echo "   ... (truncated)\n";
                    }
                }
                
            } catch (Exception $e) {
                echo "   ❌ Failed to download results: " . $e->getMessage() . "\n";
            }
            
            echo "\n   💡 Next Steps:\n";
            echo "   1. Check downloaded files in: $downloadDir\n";
            echo "   2. Parse JSON files for structured data processing\n";
            echo "   3. Use markdown for human-readable content\n";
            
        } elseif ($statusResult->isFailed()) {
            echo "   ❌ Text extraction failed\n";
            
            if ($statusResult->getResults()) {
                $results = $statusResult->getResults();
                if (isset($results['error'])) {
                    echo "   → Error: " . ($results['error']['message'] ?? 'Unknown error') . "\n";
                }
            }
            
        } else {
            echo "   ⏳ Text extraction still in progress after " . ($attempt * 5) . " seconds\n";
            echo "   → Current status: " . $statusResult->getStatus() . "\n";
            echo "   → You can check the job later with ID: $jobId\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Excel processing failed: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    } finally {
        // Step 5: Cleanup (optional - comment out if you want to keep files).
        echo "\n5. Cleanup:\n";
        
        $cleanup = readline("Delete uploaded file and job? (y/N): ");
        if (strtolower(trim($cleanup)) === 'y') {
            try {
                // Delete file from COS.
                $deleteCommand = ObjectCommand::delete(
                    bucket: BucketName::from($bucketName),
                    key: ObjectKey::from($remoteKey)
                );
                $cosClient->delete($deleteCommand);
                echo "   ✓ File deleted from COS\n";
            } catch (Exception $e) {
                echo "   ⚠ Failed to delete file: " . $e->getMessage() . "\n";
            }
            
            if ($jobId) {
                try {
                    $deleted = $watsonx->deleteTextExtraction($jobId, $projectId, hardDelete: true);
                    if ($deleted) {
                        echo "   ✓ Extraction job deleted\n";
                    }
                } catch (Exception $e) {
                    echo "   ⚠ Failed to delete job: " . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "   → Files kept for manual inspection\n";
            echo "   → Job ID: $jobId\n";
            echo "   → COS file: $remoteKey\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}