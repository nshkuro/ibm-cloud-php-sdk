<?php

require_once __DIR__ . '/../vendor/autoload.php';

use IBMCloud\Authentication\Strategies\IamStrategy;
use IBMCloud\Authentication\ValueObjects\ApiKey;
use IBMCloud\Services\AI\WatsonX\Client;
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

echo "WatsonX.ai Real Document Test\n";
echo "============================\n\n";

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
    
    echo "✓ Authentication configured\n";
    echo "✓ Using endpoint: $watsonxEndpoint\n";
    echo "✓ Project ID: $projectId\n\n";

    // Setup COS client for file operations.
    $cosEndpoint = $_ENV['IBM_COS_ENDPOINT'] ?? throw new Exception('Missing IBM_COS_ENDPOINT');
    $cosServiceInstanceId = $_ENV['IBM_COS_SERVICE_INSTANCE_ID'] ?? throw new Exception('Missing IBM_COS_SERVICE_INSTANCE_ID');
    $bucketName = $_ENV['TEST_BUCKET_NAME'] ?? throw new Exception('Missing TEST_BUCKET_NAME');
    
    $cosClient = new COSClient($authenticatedTransport, $cosEndpoint, $cosServiceInstanceId);
    echo "✓ COS client configured: $cosEndpoint\n";
    echo "✓ Using bucket: $bucketName\n\n";
    
    $documentConnectionId = $_ENV['IBM_DOCUMENT_CONNECTION_ID'] ?? throw new Exception('Missing IBM_DOCUMENT_CONNECTION_ID');
    $resultsConnectionId = $_ENV['IBM_RESULTS_CONNECTION_ID'] ?? throw new Exception('Missing IBM_RESULTS_CONNECTION_ID');
    
    // Test 1: Upload file to COS, extract text, then cleanup.
    echo "1. Complete workflow: Upload → Extract → Cleanup\n";
    
    $localFile = __DIR__ . '/files/sample_1.txt';
    $remoteKey = 'test_documents/sample_1_' . time() . '.txt';
    $jobId = null;
    
    try {
        // Step 1: Upload file to COS.
        echo "   → Uploading $localFile to COS...\n";
        
        if (!file_exists($localFile)) {
            throw new Exception("Local file not found: $localFile");
        }
        
        $fileContent = file_get_contents($localFile);
        
        $bucketNameObj = BucketName::from($bucketName);
        $objectKey = ObjectKey::from($remoteKey);
        
        $command = ObjectCommand::store(
            bucket: $bucketNameObj,
            key: $objectKey,
            content: $fileContent
        );
        
        $cosClient->store($command);
        
        echo "   ✓ File uploaded to COS: $remoteKey\n";
        
        // Step 2: Create text extraction job.
        echo "   → Creating text extraction job...\n";
        
        $documentRef = TextExtractionDataReference::connectionAsset(
            connectionId: $documentConnectionId,
            fileName: $remoteKey
        );
        
        $resultsRef = TextExtractionDataReference::connectionAsset(
            connectionId: $resultsConnectionId,
            fileName: 'results/sample_1_' . time() . '/'
        );
        
        $basicParams = TextExtractionParameters::basic();
        
        $basicRequest = TextExtractionRequest::create($documentRef, $resultsRef)
            ->withProjectId($projectId)
            ->withParameters($basicParams);
        
        $basicResult = $watsonx->extractText($basicRequest);
        $jobId = $basicResult->getId();
        
        echo "   ✓ Job created: $jobId\n";
        echo "   ✓ Status: " . $basicResult->getStatus() . "\n";
        echo "   ✓ Results location: " . $resultsRef->getLocation()['file_name'] . "\n";
        
        // Step 3: Monitor job progress.
        echo "   → Monitoring job progress...\n";
        
        $maxAttempts = 12; // 60 seconds max.
        $attempt = 0;
        
        do {
            sleep(5);
            $attempt++;
            
            $statusResult = $watsonx->getTextExtraction($jobId, $projectId);
            $currentStatus = $statusResult->getStatus();
            
            echo "   → Attempt $attempt: $currentStatus\n";
            
            if ($statusResult->isFinished()) {
                break;
            }
            
        } while ($attempt < $maxAttempts);
        
        if ($statusResult->isCompleted()) {
            echo "   ✅ Text extraction completed successfully!\n";
            
            if ($statusResult->getNumberPagesProcessed() !== null) {
                echo "   → Pages processed: " . $statusResult->getNumberPagesProcessed() . "\n";
            }
            
        } elseif ($statusResult->isFailed()) {
            echo "   ❌ Text extraction failed.\n";
        } else {
            echo "   ⏳ Text extraction still in progress after " . ($attempt * 5) . " seconds\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Workflow failed: " . $e->getMessage() . "\n";
    } finally {
        // Step 4: Cleanup - delete uploaded file and job.
        echo "   → Cleaning up...\n";
        
        try {
            // Delete file from COS.
            $deleteCommand = ObjectCommand::delete(
                bucket: BucketName::from($bucketName),
                key: ObjectKey::from($remoteKey)
            );
            $cosClient->delete($deleteCommand);
            echo "   ✓ File deleted from COS: $remoteKey\n";
        } catch (Exception $e) {
            echo "   ⚠ Failed to delete file from COS: " . $e->getMessage() . "\n";
        }
        
        if ($jobId) {
            try {
                // Delete extraction job.
                $deleted = $watsonx->deleteTextExtraction($jobId, $projectId, hardDelete: true);
                if ($deleted) {
                    echo "   ✓ Extraction job deleted: $jobId\n";
                } else {
                    echo "   ⚠ Failed to delete extraction job: $jobId\n";
                }
            } catch (Exception $e) {
                echo "   ⚠ Failed to delete extraction job: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n";
    
    // Test 2: Process Excel file with comprehensive extraction.
    $excelFile = __DIR__ . '/files/Basingstoke - Categories and descriptions.xlsx';
    if (file_exists($excelFile)) {
        echo "2. Excel file comprehensive extraction...\n";
        
        $excelRemoteKey = 'test_documents/excel_' . time() . '.xlsx';
        $excelJobId = null;
        
        try {
            // Upload Excel file.
            echo "   → Uploading Excel file to COS...\n";
            $excelContent = file_get_contents($excelFile);
            
            $excelCommand = ObjectCommand::store(
                bucket: BucketName::from($bucketName),
                key: ObjectKey::from($excelRemoteKey),
                content: $excelContent,
                contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
            
            $cosClient->store($excelCommand);
            echo "   ✓ Excel file uploaded: $excelRemoteKey\n";
            
            // Create comprehensive extraction job.
            $excelDocRef = TextExtractionDataReference::connectionAsset(
                connectionId: $documentConnectionId,
                fileName: $excelRemoteKey
            );
            
            $excelResultsRef = TextExtractionDataReference::connectionAsset(
                connectionId: $resultsConnectionId,
                fileName: 'results/excel_' . time() . '/'
            );
            
            $comprehensiveParams = TextExtractionParameters::comprehensive();
            
            $excelRequest = TextExtractionRequest::create($excelDocRef, $excelResultsRef)
                ->withProjectId($projectId)
                ->withParameters($comprehensiveParams);
            
            $excelResult = $watsonx->extractText($excelRequest);
            $excelJobId = $excelResult->getId();
            
            echo "   ✓ Excel extraction job created: $excelJobId\n";
            echo "   ✓ Outputs: " . implode(', ', $comprehensiveParams->getRequestedOutputs()) . "\n";
            echo "   ✓ Tables processing: enabled\n";
            
        } catch (Exception $e) {
            echo "   ❌ Excel processing failed: " . $e->getMessage() . "\n";
        } finally {
            // Cleanup Excel file and job.
            if (isset($excelRemoteKey)) {
                try {
                    $cosClient->delete(ObjectCommand::delete(
                        bucket: BucketName::from($bucketName),
                        key: ObjectKey::from($excelRemoteKey)
                    ));
                    echo "   ✓ Excel file cleaned up\n";
                } catch (Exception $e) {
                    echo "   ⚠ Failed to cleanup Excel file: " . $e->getMessage() . "\n";
                }
            }
            
            if ($excelJobId) {
                try {
                    $watsonx->deleteTextExtraction($excelJobId, $projectId, hardDelete: true);
                    echo "   ✓ Excel job cleaned up\n";
                } catch (Exception $e) {
                    echo "   ⚠ Failed to cleanup Excel job: " . $e->getMessage() . "\n";
                }
            }
        }
        
    } else {
        echo "2. Excel file not found, skipping...\n";
    }
    
    echo "\n";
    
    // Test 3: List all jobs.
    echo "3. Listing recent extraction jobs...\n";
    
    try {
        $jobs = $watsonx->listTextExtractions(projectId: $projectId, limit: 10);
        
        echo "   Found " . count($jobs) . " extraction jobs:\n";
        
        foreach (array_slice($jobs, 0, 5) as $job) {
            if (isset($job['metadata'])) {
                $jobId = $job['metadata']['id'] ?? 'unknown';
                $status = $job['entity']['results']['status'] ?? 'unknown';
                $created = $job['metadata']['created_at'] ?? 'unknown';
                $name = isset($job['entity']['document_reference']['location']['file_name']) 
                    ? basename($job['entity']['document_reference']['location']['file_name'])
                    : 'unknown';
                    
                echo "   • $name: $status (ID: " . substr($jobId, 0, 8) . "..., $created)\n";
            }
        }
        
    } catch (Exception $e) {
        echo "   ❌ Job listing failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    echo "🎉 Real document testing completed!\n\n";
    
    echo "📋 Next Steps:\n";
    echo "   1. Check your IBM Cloud Object Storage bucket for results\n";
    echo "   2. Download the extracted text, markdown, or JSON files\n";
    echo "   3. Use the job IDs to check status or delete completed jobs\n\n";
    
    echo "💡 File Upload Instructions:\n";
    echo "   • Upload sample_1.txt to your COS bucket root\n";
    echo "   • Upload the Excel file to your COS bucket root\n";
    echo "   • Make sure your connection asset points to the correct bucket\n";
    echo "   • Results will be saved to the 'results/' directory in your bucket\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}