# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **BREAKING**: Lowered minimum PHP version requirement from 8.2 to 8.1
- Removed `readonly` modifier from class declarations for PHP 8.1 compatibility
- Updated the following classes to support PHP 8.1:
  - Authentication: `ApiKey`, `IamToken`, `Region`, `Token`
  - AI ValueObjects: `ModelId`, `ProjectId`, `SpaceId`
  - ObjectStorage Models: `BucketCommand`, `BucketQuery`, `ListQuery`, `ObjectCommand`, `ObjectQuery`
  - ObjectStorage ValueObjects: `BucketName`, `ObjectKey`, `StorageClass`

### Technical Details
- Maintained `readonly` properties within classes while removing class-level `readonly` modifier
- This change allows the SDK to run on PHP 8.1 while preserving immutability semantics

## [1.0.0] - 2024-01-01

### Added
- Initial release with Phase 1 features
- WatsonX.ai integration with foundation models
- Object Storage service with full CRUD operations
- Text extraction capabilities via WatsonX.ai
- Comprehensive middleware pipeline
- Configuration system with environment support
- Complete examples and documentation