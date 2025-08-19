<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\Results;

use IBMCloud\Services\AI\ValueObjects\ModelId;
use DateTimeImmutable;
use InvalidArgumentException;

final class EmbeddingResult
{
    public function __construct(
        private readonly ModelId $modelId,
        private readonly DateTimeImmutable $createdAt,
        private readonly array $results,
        private readonly ?array $system = null
    ) {}

    public static function fromApiResponse(array $response): self
    {
        return new self(
            ModelId::from($response['model_id']),
            new DateTimeImmutable($response['created_at']),
            $response['results'],
            $response['system'] ?? null
        );
    }

    /**
     * Get embeddings for all inputs.
     */
    public function getEmbeddings(): array
    {
        return array_map(
            fn(array $result) => $result['embedding'] ?? [],
            $this->results
        );
    }

    /**
     * Get the embedding for a single input (for single input requests).
     */
    public function getSingleEmbedding(): array
    {
        if (count($this->results) !== 1) {
            throw new InvalidArgumentException('This is not a single embedding result.');
        }

        return $this->results[0]['embedding'] ?? [];
    }

    /**
     * Get the dimensionality of the embeddings.
     */
    public function getDimensionality(): int
    {
        if (empty($this->results)) {
            return 0;
        }

        $embedding = $this->results[0]['embedding'] ?? [];
        return count($embedding);
    }

    /**
     * Get input token counts for all results.
     */
    public function getInputTokenCounts(): array
    {
        return array_map(
            fn(array $result) => $result['input_token_count'] ?? 0,
            $this->results
        );
    }

    /**
     * Get total token count across all inputs.
     */
    public function getTotalTokenCount(): int
    {
        return array_sum($this->getInputTokenCounts());
    }

    /**
     * Calculate cosine similarity between two embedding indices.
     */
    public function cosineSimilarity(int $indexA, int $indexB): float
    {
        if (!isset($this->results[$indexA], $this->results[$indexB])) {
            throw new InvalidArgumentException('Invalid embedding indices provided.');
        }

        $embeddingA = $this->results[$indexA]['embedding'];
        $embeddingB = $this->results[$indexB]['embedding'];

        return $this->calculateCosineSimilarity($embeddingA, $embeddingB);
    }

    /**
     * Calculate cosine similarity between this result and another.
     */
    public function similarityWith(EmbeddingResult $other): array
    {
        $similarities = [];
        $thisEmbeddings = $this->getEmbeddings();
        $otherEmbeddings = $other->getEmbeddings();

        foreach ($thisEmbeddings as $i => $thisEmb) {
            foreach ($otherEmbeddings as $j => $otherEmb) {
                $similarities["$i:$j"] = $this->calculateCosineSimilarity($thisEmb, $otherEmb);
            }
        }

        return $similarities;
    }

    /**
     * Find the most similar embedding to the one at given index.
     */
    public function findMostSimilar(int $targetIndex): array
    {
        if (!isset($this->results[$targetIndex])) {
            throw new InvalidArgumentException('Invalid target index provided.');
        }

        $targetEmbedding = $this->results[$targetIndex]['embedding'];
        $maxSimilarity = -1.0;
        $mostSimilarIndex = -1;

        foreach ($this->results as $i => $result) {
            if ($i === $targetIndex) {
                continue;
            }

            $similarity = $this->calculateCosineSimilarity(
                $targetEmbedding,
                $result['embedding']
            );

            if ($similarity > $maxSimilarity) {
                $maxSimilarity = $similarity;
                $mostSimilarIndex = $i;
            }
        }

        return [
            'index' => $mostSimilarIndex,
            'similarity' => $maxSimilarity,
            'embedding' => $this->results[$mostSimilarIndex]['embedding'] ?? [],
        ];
    }

    /**
     * Get normalized embeddings (L2 normalization).
     */
    public function getNormalizedEmbeddings(): array
    {
        return array_map(
            fn(array $result) => $this->normalizeVector($result['embedding'] ?? []),
            $this->results
        );
    }

    /**
     * Calculate statistics for the embeddings.
     */
    public function getStatistics(): array
    {
        if (empty($this->results)) {
            return [];
        }

        $embeddings = $this->getEmbeddings();
        $dimensionality = $this->getDimensionality();
        
        if ($dimensionality === 0) {
            return [];
        }

        $stats = [
            'count' => count($embeddings),
            'dimensionality' => $dimensionality,
            'total_tokens' => $this->getTotalTokenCount(),
            'mean_magnitude' => 0.0,
            'std_magnitude' => 0.0,
        ];

        // Calculate magnitudes
        $magnitudes = array_map([$this, 'calculateMagnitude'], $embeddings);
        
        $stats['mean_magnitude'] = array_sum($magnitudes) / count($magnitudes);
        
        if (count($magnitudes) > 1) {
            $variance = array_sum(
                array_map(
                    fn($mag) => pow($mag - $stats['mean_magnitude'], 2),
                    $magnitudes
                )
            ) / (count($magnitudes) - 1);
            
            $stats['std_magnitude'] = sqrt($variance);
        }

        return $stats;
    }

    /**
     * Export embeddings to array format.
     */
    public function toArray(): array
    {
        return [
            'model_id' => $this->modelId->toString(),
            'created_at' => $this->createdAt->format('c'),
            'embeddings' => $this->getEmbeddings(),
            'statistics' => $this->getStatistics(),
        ];
    }

    // Private helper methods

    private function calculateCosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new InvalidArgumentException('Embeddings must have the same dimensionality.');
        }

        if (empty($a)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    private function normalizeVector(array $vector): array
    {
        if (empty($vector)) {
            return [];
        }

        $magnitude = $this->calculateMagnitude($vector);
        
        if ($magnitude == 0.0) {
            return $vector;
        }

        return array_map(fn($val) => $val / $magnitude, $vector);
    }

    private function calculateMagnitude(array $vector): float
    {
        if (empty($vector)) {
            return 0.0;
        }

        return sqrt(array_sum(array_map(fn($val) => $val * $val, $vector)));
    }

    // Getters
    public function getModelId(): ModelId
    {
        return $this->modelId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getSystem(): ?array
    {
        return $this->system;
    }

    public function getResultCount(): int
    {
        return count($this->results);
    }
}