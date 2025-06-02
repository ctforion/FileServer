# Separated API Documentation

## Overview
This is the separated API system for the File Server. All API functionality has been extracted from the integrated PHP code into dedicated, fast, and maintainable endpoints.

## Base URL
- Development: `http://localhost/api`
- Production: `https://yourdomain.com/api`

## Authentication
The API supports multiple authentication methods:

### 1. Session-based (for web interface)
- Login via web interface creates session
- Subsequent API calls use session cookies

### 2. Bearer Token
```http
Authorization: Bearer <token>
```

### 3. Basic Authentication
```http
Authorization: Basic <base64(username:password)>
```

### 4. Token Parameter
```http
GET /api/endpoint?token=<token>
```

## Endpoints

### Authentication
- `POST /auth/login` - Login and get token
- `POST /auth/logout` - Logout
- `GET /auth/me` - Get current user info
- `POST /auth/token` - Generate API token

### Users (Admin required for most operations)
- `GET /users` - List all users
- `GET /users/{id}` - Get user details
- `POST /users` - Create new user
- `PUT /users/{id}` - Update user
- `DELETE /users/{id}` - Delete user

### Files
- `GET /files` - List user files
- `POST /files/upload` - Upload file
- `GET /files/{id}/download` - Download file
- `DELETE /files/{id}` - Delete file
- `GET /files/{id}/info` - Get file information

### Admin (Admin only)
- `GET /admin/stats` - System statistics
- `GET /admin/logs` - System logs
- `POST /admin/cleanup` - Run cleanup operations

### System
- `GET /system/health` - Health check (no auth required)
- `GET /system/info` - System information (admin only)

## Response Format
All API responses follow this format:

### Success Response
```json
{
  "success": true,
  "status_code": 200,
  "data": { ... },
  "message": "Optional message",
  "timestamp": "2025-06-02T22:30:00Z",
  "request_id": "api_123456789",
  "api_version": "1.0.0"
}
```

### Error Response
```json
{
  "success": false,
  "status_code": 400,
  "error": "Error message",
  "details": { ... },
  "timestamp": "2025-06-02T22:30:00Z",
  "request_id": "api_123456789",
  "api_version": "1.0.0"
}
```

### Paginated Response
```json
{
  "success": true,
  "status_code": 200,
  "data": {
    "items": [ ... ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total_items": 100,
      "total_pages": 5,
      "has_next": true,
      "has_prev": false
    }
  }
}
```

## Example Requests

### Login
```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "your_password"}'
```

### Upload File
```bash
curl -X POST http://localhost/api/files/upload \
  -H "Authorization: Bearer <token>" \
  -F "file=@/path/to/file.txt"
```

### List Files
```bash
curl -X GET "http://localhost/api/files?page=1&per_page=10" \
  -H "Authorization: Bearer <token>"
```

### Get System Health
```bash
curl -X GET http://localhost/api/system/health
```

## Rate Limiting
The API implements rate limiting to prevent abuse:
- Authentication: 100 requests per hour per IP
- File uploads: 10 requests per minute per user
- General API calls: 1000 requests per hour per user

## Error Codes
- `400` - Bad Request (validation errors)
- `401` - Unauthorized (authentication required)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `409` - Conflict (resource already exists)
- `413` - Payload Too Large (file too big or quota exceeded)
- `422` - Unprocessable Entity (validation failed)
- `429` - Too Many Requests (rate limit exceeded)
- `500` - Internal Server Error

## Features of the Separated API

### Performance Benefits
- No session overhead for stateless requests
- Dedicated error handling
- Optimized routing system
- Minimal dependencies per endpoint

### Maintenance Benefits
- Clean separation of concerns
- Standardized response formats
- Centralized authentication
- Easy to test and debug

### Security Features
- Multiple authentication methods
- Rate limiting protection
- Input validation
- Secure error handling (no sensitive data leakage)

## Migration from Old API
The old integrated API files are still present for backward compatibility, but the new separated API provides:
- Better performance
- Cleaner code structure
- Easier maintenance
- Standardized responses
- Better error handling

It's recommended to migrate to the new API endpoints for all new integrations.
