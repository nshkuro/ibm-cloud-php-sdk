<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Services\AI\ValueObjects;

use IBMCloud\Services\AI\ValueObjects\Prompt;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PromptTest extends TestCase
{
    public function testCreateValidPrompt(): void
    {
        $content = 'What is PHP?';
        $prompt = Prompt::from($content);
        
        $this->assertSame($content, $prompt->toString());
        $this->assertSame($content, (string) $prompt);
    }

    public function testRejectsEmptyPrompt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt content cannot be empty.');
        
        Prompt::from('');
    }

    public function testSystemMessage(): void
    {
        $prompt = Prompt::systemMessage('You are a helpful assistant.');
        
        $this->assertSame('System: You are a helpful assistant.', $prompt->toString());
    }

    public function testUserMessage(): void
    {
        $prompt = Prompt::userMessage('What is PHP?');
        
        $this->assertSame('User: What is PHP?', $prompt->toString());
    }

    public function testAssistantMessage(): void
    {
        $prompt = Prompt::assistantMessage('PHP is a programming language.');
        
        $this->assertSame('Assistant: PHP is a programming language.', $prompt->toString());
    }

    public function testChatFormat(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => 'Hello!'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ];
        
        $prompt = Prompt::chat($messages);
        $expected = "System: You are helpful.\n\nUser: Hello!\n\nAssistant: Hi there!";
        
        $this->assertSame($expected, $prompt->toString());
    }

    public function testChatFormatValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chat messages must have "role" and "content" keys.');
        
        Prompt::chat([['role' => 'user']]); // Missing content
    }

    public function testWithContext(): void
    {
        $prompt = Prompt::withContext('PHP is a language', 'What is it?');
        $expected = "Context: PHP is a language\n\nQuestion: What is it?\n\nAnswer:";
        
        $this->assertSame($expected, $prompt->toString());
    }

    public function testFewShot(): void
    {
        $examples = [
            ['input' => '2 + 2', 'output' => '4'],
            ['input' => '5 + 3', 'output' => '8'],
        ];
        
        $prompt = Prompt::fewShot('Math problems', $examples, '7 + 1');
        
        $this->assertStringContainsString('Task: Math problems', $prompt->toString());
        $this->assertStringContainsString('Example 1:', $prompt->toString());
        $this->assertStringContainsString('Input: 2 + 2', $prompt->toString());
        $this->assertStringContainsString('Output: 4', $prompt->toString());
        $this->assertStringContainsString('Input: 7 + 1', $prompt->toString());
    }

    public function testGetWordCount(): void
    {
        $prompt = Prompt::from('Hello world, how are you?');
        
        $this->assertSame(5, $prompt->getWordCount());
    }

    public function testGetCharacterCount(): void
    {
        $prompt = Prompt::from('Hello');
        
        $this->assertSame(5, $prompt->getCharacterCount());
    }

    public function testGetEstimatedTokenCount(): void
    {
        $prompt = Prompt::from('This is a test'); // 14 characters
        
        // Should be approximately 14/4 = 3.5, rounded up to 4
        $this->assertSame(4, $prompt->getEstimatedTokenCount());
    }

}