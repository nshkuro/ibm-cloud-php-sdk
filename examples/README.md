# IBM Cloud PHP SDK Examples

This directory contains practical examples demonstrating how to use the IBM Cloud PHP SDK.

## Quick Start

### 1. Setup Environment

Copy the example environment file and configure your credentials:

```bash
cp .env.example .env
```

Edit `.env` and add your IBM Cloud API key:

```bash
IBM_API_KEY=your-actual-ibm-cloud-api-key
TEST_BUCKET_NAME=my-test-bucket
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Run Examples

**Mock Example** (works without real credentials):
```bash
php examples/object-storage-mock-example.php
```

**Real API Example** (requires valid IBM Cloud API key):
```bash
php examples/object-storage-example.php
```

## Available Examples

### Object Storage Examples

#### `object-storage-mock-example.php`
**Purpose**: Demonstrates SDK architecture without requiring real IBM Cloud credentials.

**Features**:
- ✅ Complete SDK functionality demonstration
- ✅ Mock IBM COS API responses
- ✅ Authentication middleware pipeline
- ✅ All CRUD operations (Create, Read, Update, Delete)
- ✅ Streaming support for large files
- ✅ Range requests for partial content
- ✅ Custom metadata handling
- ✅ Debug output showing HTTP requests

**Usage**:
```bash
php examples/object-storage-mock-example.php
```

#### `object-storage-example.php`
**Purpose**: Real IBM Cloud Object Storage integration.

**Requirements**:
- Valid IBM Cloud API key
- Existing bucket in your IBM Cloud account
- Network access to IBM Cloud endpoints

**Features**:
- 🔐 Real IBM IAM authentication
- 🌐 Actual IBM COS API calls
- 📦 Production-ready patterns
- 🛡️ Error handling with real scenarios

**Setup**:
1. Get your API key from [IBM Cloud IAM](https://cloud.ibm.com/iam/apikeys)
2. Create a bucket in [IBM Cloud Object Storage](https://cloud.ibm.com/catalog/services/cloud-object-storage)
3. Update `.env` with your credentials
4. Run the example

## Environment Variables

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `IBM_API_KEY` | Your IBM Cloud API key | Yes* | - |
| `IBM_COS_ENDPOINT` | COS endpoint URL | No | us-south endpoint |
| `TEST_BUCKET_NAME` | Bucket name for testing | No | `my-demo-bucket` |
| `LOG_LEVEL` | Logging level | No | `DEBUG` |

*Required for real API examples, optional for mock examples.

## Getting IBM Cloud Credentials

### 1. Get API Key

1. Visit [IBM Cloud IAM API Keys](https://cloud.ibm.com/iam/apikeys)
2. Click "Create an API key"
3. Give it a name and description
4. Copy the API key and save it securely
5. Add it to your `.env` file

### 2. Create Object Storage Bucket

1. Go to [IBM Cloud Object Storage](https://cloud.ibm.com/catalog/services/cloud-object-storage)
2. Create a service instance (if you don't have one)
3. Create a bucket with a unique name
4. Update `TEST_BUCKET_NAME` in your `.env` file

## Example Output

When you run the mock example, you'll see output like:

```
IBM Cloud Object Storage SDK Demo
==================================

1. Setting up mock authentication...
   ✓ Using API key authentication (mock)

2. Creating mock COS transport...
   ✓ Mock transport ready with authentication

3. Creating Object Storage client...
   ✓ Client created for endpoint: https://mock-cos.example.com

4. Demo parameters:
   ✓ Bucket: my-demo-bucket
   ✓ Object: documents/demo-file.txt
   ✓ Content length: 204 bytes

5. Storing object...
  → PUT https://mock-cos.example.com/my-demo-bucket/documents%2Fdemo-file.txt
    ✓ Stored object with ETag: abc123...
   ✓ Object stored successfully!

[... continued output showing all operations ...]

🎉 Demo completed successfully!
```

## Architecture Highlights

The examples demonstrate key SDK features:

- **Type Safety**: Value objects with validation (BucketName, ObjectKey, StorageClass)
- **Middleware Pipeline**: Authentication, logging, and other cross-cutting concerns
- **Command/Query Pattern**: Separate models for operations and queries
- **Streaming Support**: Efficient handling of large files
- **Error Handling**: Comprehensive exception handling with context
- **Testability**: Mock transport for testing without real API calls

## Common Issues

### "API key not found" Error

Make sure you have:
1. Copied `.env.example` to `.env`
2. Added your real IBM Cloud API key to `.env`
3. The API key has proper permissions

### "Bucket not found" Error

Ensure:
1. The bucket exists in your IBM Cloud account
2. The bucket name in `.env` matches exactly
3. Your API key has access to the bucket

### Network/SSL Errors

Try:
1. Check your internet connection
2. Verify IBM Cloud service status
3. Check firewall/proxy settings

## Next Steps

1. **Explore the Code**: Look at the source code in `src/` to understand the implementation
2. **Run Tests**: Execute `composer test` to see the comprehensive test suite
3. **Integrate**: Use these patterns in your own applications
4. **Extend**: Add support for additional IBM Cloud services

## Support

- 📚 [IBM Cloud Documentation](https://cloud.ibm.com/docs)
- 🔧 [IBM Cloud PHP SDK Issues](https://github.com/your-repo/issues)
- 💬 [IBM Cloud Community](https://community.ibm.com/community/user/cloud)