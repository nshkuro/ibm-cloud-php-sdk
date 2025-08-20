# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the IBM Cloud PHP SDK - a modern PHP library for working with IBM Cloud Services focused on performance, type safety, and enterprise application usability. **Phase 1 is now complete** with full middleware pipeline, three major services, and comprehensive examples.

## Key Architecture Principles

- **Contract-oriented architecture** using interfaces and DTOs
- **Full typing** with PHP 8.1+ features (enums, readonly properties, union types)  
- **Middleware pipeline** for request/response processing
- **Domain-Driven Design** for code organization
- **SOLID principles** throughout
- **Minimal dependencies** and plugin-based extensibility

## Phase 1 Completed Features

✅ **Middleware Pipeline**
- RetryMiddleware with exponential, linear, and fixed delay strategies
- CircuitBreakerMiddleware with threshold-based failure protection  
- RateLimitMiddleware with token bucket algorithm
- AuthenticationMiddleware with automatic token refresh
- LoggingMiddleware with structured request/response logging

✅ **IBM Cloud Services**
- **WatsonX.ai**: Foundation models, streaming responses, text extraction
- **Object Storage**: Full CRUD operations with streaming support
- **Text Extraction**: Document processing via WatsonX.ai with results management

✅ **Configuration System**
- Fluent ConfigurationBuilder with method chaining
- Environment and file-based credential providers
- Service-specific configuration with defaults
- Production, development, and testing presets

✅ **Examples and Documentation**
- Complete pipeline demonstration with all middleware
- Real-world usage examples for all services
- Streaming responses and text extraction workflows
- Unit tests for core middleware components

## Current Directory Structure

```
src/
├── Contracts/           # Interfaces for all major components
├── Transport/           # HTTP transport with middleware support
├── Authentication/      # IAM, API Key, Token strategies
├── Services/           # ✅ IBM Cloud service implementations
│   ├── ObjectStorage/      # ✅ Full CRUD operations with streaming
│   └── AI/WatsonX/         # ✅ Foundation models + text extraction
├── Configuration/       # Configuration builders and providers
└── Exceptions/         # Hierarchical exception system
```

## Development Commands

Phase 1 is complete with full test suite and examples:

```bash
# Install dependencies
composer install

# Run tests
composer test
# or
./vendor/bin/phpunit

# Run examples
cd examples
php watsonx-example.php
php complete-pipeline-example.php

# Code style checking (when configured)
composer cs-check
# or  
./vendor/bin/php-cs-fixer fix --dry-run

# Static analysis (when configured)
composer analyse
# or
./vendor/bin/phpstan analyse
```

## Core Service Architecture

### Transport Layer
- Middleware pipeline for request/response modification
- Connection pooling for optimization
- Circuit breaker pattern for resilience
- Native async support with Promise/Future patterns

### Domain Layer  
- Immutable Value Objects for data representation
- Domain Events for state change tracking
- Repository pattern for data abstraction
- Specification pattern for complex queries

### Service Implementation Pattern
All services follow a consistent pattern:
1. Interface definition in `Contracts/`
2. Implementation with constructor dependency injection
3. Builder patterns for complex requests
4. Middleware integration for cross-cutting concerns
5. Comprehensive exception handling with context

## Authentication Strategy

The SDK uses a pluggable authentication system:
- `IamStrategy` for IBM IAM authentication
- `ApiKeyStrategy` for direct API key usage  
- `TokenStrategy` for pre-existing tokens
- `TokenManager` for automatic token refresh and caching

## Error Handling Philosophy

- Hierarchical exception system with detailed context
- `RetryableExceptionInterface` for automatic retry logic
- Recovery strategies for different error types
- Structured error responses with suggestions

## Configuration Management

- Fluent `ConfigurationBuilder` for setup
- Environment-based credential providers
- Service discovery and auto-configuration
- Middleware registration and retry policies

## Target Services

✅ **Phase 1 Completed Services:**
1. **Object Storage** - ✅ Full CRUD operations with streaming support
2. **WatsonX AI** - ✅ Foundation models with streaming and prompt optimization  
3. **Text Extraction** - ✅ Document processing via WatsonX.ai (integrated)

## Development Standards

- PHP 8.1+ required, 8.2+ recommended
- PSR-12 code style compliance
- Minimum 80% test coverage
- Full type declarations and PHPDoc
- No sensitive information in code/commits

## MCP (Model Context Protocol) Resources

This project has access to specialized IBM Cloud documentation through Context7 MCP integration:

### Available Context7 Resources

1. **IBM Cloud SDK Handbook**: https://context7.com/ibm-cloud-docs/sdk-handbook
   - Guidelines for building IBM Cloud SDKs
   - Best practices for SDK architecture
   - Authentication and error handling patterns

2. **IBM Cloud CLI Documentation**: https://context7.com/ibm-cloud-docs/cli
   - IBM API usage patterns and conventions
   - Command-line interface examples
   - Service integration guidelines

3. **PHP SDK Reference Implementation**: https://context7.com/seanluis/ibm-cloud-sdk
   - Existing PHP SDK examples for IBM NLU and COS
   - Code patterns and implementation strategies
   - Real-world usage examples

### Using MCP Resources During Development

When implementing specific IBM Cloud service integrations:
1. Reference the SDK Handbook for architectural guidance
2. Check CLI documentation for API endpoint patterns
3. Review the PHP reference implementation for language-specific patterns
4. Use these resources to ensure consistency with IBM Cloud standards

### IBM Cloud Standards Compliance

Based on available IBM Cloud documentation:
- Follow IBM's SDK architectural patterns
- Implement standard IBM authentication flows
- Use consistent error handling across services
- Maintain compatibility with IBM Cloud service contracts

## Notes for Implementation
- Always use English for code comments and documentation
- Always implement interfaces first, then concrete classes
- Use readonly properties and constructor promotion where possible
- Leverage PHP 8.1+ enums for constants and states
- Follow the middleware pattern for extensible request processing
- Implement proper resource cleanup for streaming operations
- Consult MCP Context7 resources for IBM-specific implementation guidance