<?php

require_once __DIR__ . '/../vendor/autoload.php';

use IBMCloud\Services\AI\WatsonX\ResultsDownloader;
use IBMCloud\Services\ObjectStorage\Client as COSClient;
use IBMCloud\Authentication\Strategies\IamStrategy;
use IBMCloud\Authentication\ValueObjects\ApiKey;
use IBMCloud\Transport\HttpTransport;
use IBMCloud\Transport\Middleware\AuthenticationMiddleware;
use Dotenv\Dotenv;

// Load environment variables.
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

echo "WatsonX.ai Results Analysis Tool\n";
echo "===============================\n\n";

try {
    // Setup.
    $apiKey = ApiKey::fromEnvironment('IBM_API_KEY');
    $transport = new HttpTransport();
    $iamAuth = new IamStrategy($apiKey, $transport);
    $authenticatedTransport = $transport->withMiddleware(new AuthenticationMiddleware($iamAuth));
    
    $cosEndpoint = $_ENV['IBM_COS_ENDPOINT'] ?? throw new Exception('Missing IBM_COS_ENDPOINT');
    $cosServiceInstanceId = $_ENV['IBM_COS_SERVICE_INSTANCE_ID'] ?? throw new Exception('Missing IBM_COS_SERVICE_INSTANCE_ID');
    $bucketName = $_ENV['TEST_BUCKET_NAME'] ?? throw new Exception('Missing TEST_BUCKET_NAME');
    
    $cosClient = new COSClient($authenticatedTransport, $cosEndpoint, $cosServiceInstanceId);
    $downloader = new ResultsDownloader($cosClient);
    
    echo "✓ COS client configured\n";
    echo "✓ Bucket: $bucketName\n\n";
    
    // Analyze downloaded results.
    $downloadsDir = './downloads';
    if (!is_dir($downloadsDir)) {
        throw new Exception("Downloads directory not found: $downloadsDir");
    }
    
    $resultDirs = glob($downloadsDir . '/excel_*', GLOB_ONLYDIR);
    if (empty($resultDirs)) {
        throw new Exception("No Excel result directories found in: $downloadsDir");
    }
    
    $latestDir = end($resultDirs);
    echo "📁 Analyzing latest results: " . basename($latestDir) . "\n\n";
    
    $assemblyFile = glob($latestDir . '/*_assembly.json')[0] ?? null;
    if (!$assemblyFile) {
        throw new Exception("No assembly.json file found in: $latestDir");
    }
    
    echo "📊 Processing assembly file: " . basename($assemblyFile) . "\n";
    echo "File size: " . number_format(filesize($assemblyFile)) . " bytes\n\n";
    
    // Parse assembly JSON.
    $assemblyData = json_decode(file_get_contents($assemblyFile), true);
    if (!$assemblyData) {
        throw new Exception("Failed to parse assembly JSON");
    }
    
    echo "🔍 Assembly Structure Analysis:\n";
    echo "• Metadata pages: " . ($assemblyData['metadata']['num_pages'] ?? 0) . "\n";
    echo "• Title: " . ($assemblyData['metadata']['title'] ?: 'N/A') . "\n";
    echo "• Author: " . ($assemblyData['metadata']['author'] ?: 'N/A') . "\n";
    echo "• Top level structures: " . implode(', ', $assemblyData['top_level_structures'] ?? []) . "\n\n";
    
    // Analyze tables.
    if (isset($assemblyData['all_structures']['tables'])) {
        $tables = $assemblyData['all_structures']['tables'];
        echo "📋 Tables Analysis:\n";
        echo "• Number of tables: " . count($tables) . "\n";
        
        foreach ($tables as $i => $table) {
            $tableId = $table['id'] ?? "table_$i";
            $rowCount = count($table['children_ids'] ?? []);
            echo "• $tableId: $rowCount rows\n";
        }
        echo "\n";
        
        // Analyze table cells for the first table.
        if (!empty($tables) && isset($assemblyData['all_structures']['table_cells'])) {
            $cells = $assemblyData['all_structures']['table_cells'];
            echo "🔬 First Table Content Sample:\n";
            
            // Get headers (first row).
            $headers = [];
            foreach ($cells as $cell) {
                if (($cell['row_start'] ?? 0) === 1) {
                    $headers[$cell['col_start'] ?? 0] = $cell['text'] ?? '';
                }
            }
            ksort($headers);
            echo "• Headers: " . implode(' | ', $headers) . "\n";
            
            // Get first few data rows.
            $rows = [];
            foreach ($cells as $cell) {
                $rowNum = $cell['row_start'] ?? 0;
                $colNum = $cell['col_start'] ?? 0;
                if ($rowNum > 1 && $rowNum <= 4) { // Rows 2-4
                    $rows[$rowNum][$colNum] = $cell['text'] ?? '';
                }
            }
            
            foreach ($rows as $rowNum => $row) {
                ksort($row);
                echo "• Row $rowNum: " . implode(' | ', array_slice($row, 0, 3)) . "\n";
            }
            echo "\n";
            
            // Statistics.
            $totalCells = count($cells);
            $nonEmptyCells = array_filter($cells, fn($cell) => !empty(trim($cell['text'] ?? '')));
            $fillRate = round((count($nonEmptyCells) / $totalCells) * 100, 1);
            
            echo "📈 Table Statistics:\n";
            echo "• Total cells: $totalCells\n";
            echo "• Non-empty cells: " . count($nonEmptyCells) . "\n";
            echo "• Fill rate: $fillRate%\n";
            
            // Most common content types.
            $textLengths = array_map(fn($cell) => strlen($cell['text'] ?? ''), $cells);
            $avgLength = round(array_sum($textLengths) / count($textLengths), 1);
            $maxLength = max($textLengths);
            
            echo "• Average text length: $avgLength chars\n";
            echo "• Longest cell: $maxLength chars\n";
        }
    }
    
    echo "\n";
    
    // Export structured data.
    echo "💾 Exporting Structured Data:\n";
    
    // Export as CSV.
    if (isset($assemblyData['all_structures']['table_cells'])) {
        $csvFile = $latestDir . '/extracted_table.csv';
        $cells = $assemblyData['all_structures']['table_cells'];
        
        // Group cells by row.
        $csvRows = [];
        foreach ($cells as $cell) {
            $rowNum = $cell['row_start'] ?? 0;
            $colNum = $cell['col_start'] ?? 0;
            $csvRows[$rowNum][$colNum] = $cell['text'] ?? '';
        }
        
        // Write CSV.
        $fp = fopen($csvFile, 'w');
        foreach ($csvRows as $row) {
            ksort($row);
            fputcsv($fp, array_values($row));
        }
        fclose($fp);
        
        echo "• CSV exported: " . basename($csvFile) . " (" . number_format(filesize($csvFile)) . " bytes)\n";
    }
    
    // Export summary JSON.
    $summaryFile = $latestDir . '/extraction_summary.json';
    $summary = [
        'extraction_date' => date('c'),
        'source_file_size' => filesize($assemblyFile),
        'metadata' => $assemblyData['metadata'] ?? [],
        'structure_counts' => [
            'tables' => count($assemblyData['all_structures']['tables'] ?? []),
            'table_rows' => count($assemblyData['all_structures']['table_rows'] ?? []),
            'table_cells' => count($assemblyData['all_structures']['table_cells'] ?? []),
        ],
        'content_stats' => [
            'total_cells' => $totalCells ?? 0,
            'non_empty_cells' => count($nonEmptyCells ?? []),
            'fill_rate_percent' => $fillRate ?? 0,
            'avg_text_length' => $avgLength ?? 0,
            'max_text_length' => $maxLength ?? 0,
        ]
    ];
    
    file_put_contents($summaryFile, json_encode($summary, JSON_PRETTY_PRINT));
    echo "• Summary JSON: " . basename($summaryFile) . " (" . number_format(filesize($summaryFile)) . " bytes)\n";
    
    echo "\n🎉 Analysis complete!\n";
    echo "📂 Check results in: $latestDir\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}