![Spectrum View](https://raw.githubusercontent.com/dkydivyansh/Nexlab86/main/img/NexLab86.png)

## System Overview
The API Management System provides a secure and efficient way to manage and access data entries through both public and private APIs. The system supports various data types and implements token-based authentication for secure access.

## Core Features
1. Public API for single data operations
   - GET endpoint for retrieving individual data entries by ID
   - PUT endpoint for updating individual entries with token authentication
   - Support for various data types (string, number, float, boolean, json, array)

2. User Management
   - User registration and authentication
   - Token-based access control
   - Admin dashboard for user management
   - User status management (active, pending, deactivated)

## Technical Stack
- PHP
- MySQL/MariaDB
- Bootstrap 
- jQuery 
- Modern JavaScript (ES6+)

## API Endpoints

### Public API (https://nexlab86.dkydivyansh.com/api/v1/public)
1. GET /data.php
   - Purpose: Retrieve a single data entry
   - Required Parameters:
     * id: Unique identifier of the entry
   - Optional Parameters:
     * token: Required only if token_required is true for the entry
   - Response: JSON object containing:
     * id: Entry ID
     * type: Data type
     * value: Entry value
     * created_at: Creation timestamp

2. PUT /data.php
   - Purpose: Update a single data entry
   - Required Parameters:
     * id: Unique identifier of the entry
     * type: Data type (string, number, float, boolean, json, array)
     * value: New value for the entry
   - Optional Parameters:
     * token: Required only if token_required is true for the entry
   - Response: JSON object containing updated entry details


## Security Measures
1. Input validation and sanitization
4. Rate limiting and request logging
5. User status management (active/deactivated)
6. admin can deactivate any user account , which will block data access through public api.


## Configuration
1. Database connection settings in config/database.php
2. Detailed error messages in JSON format
3. Error logging and monitoring

## Documentation
1. API documentation at /api-docs.html
   - Interactive testing console
   - Request/response examples
   - Authentication guide
2. Internal documentation for API
3. User guides and tutorials



## Public API Documentation

### Overview
The public API provides external access to data entries through a RESTful interface. It is designed to be simple, secure, and efficient, with built-in authentication for protected resources.
/api/v1/public is only used for public data entries.
### Base URL
```
https://nexlab86.dkydivyansh.com/api/v1/public
```

### Authentication
1. Token-based Authentication:
   - Required only for entries with token_required=true
   - Tokens are automatically generated when enabling authentication
   - Tokens must be included in the request URL as a query parameter
   - Example: `?token=YOUR_ACCESS_TOKEN`

### Available Endpoints

1. GET /data.php
   - Purpose: Retrieve single data entry
   - URL Structure: `/data.php?id={entry_id}`
   - Authentication: Optional (based on entry settings)
   - Parameters:
     * id: Entry identifier (required)
     * token: Access token (if entry requires authentication)
   - Response Format:
     ```json
     {
       "id": "numeric",
       "data_type": "string",
       "value": "mixed",
       "created_at": "timestamp"
     }
     ```
   - Status Codes:
     * 200: Success
     * 401: Authentication required
     * 404: Entry not found
     * 500: Server error

2. PUT /data.php
   - Purpose: Update existing data entry ,only work if entry has token authentication
   - URL Structure: `/data.php?id={entry_id}`
   - Authentication: Required
   - Request Body:
     ```json
     {
       "data_type": "string",
       "value": "mixed",
       "token": "string (if required)"
     }
     ```
   - Supported Data Types:
     * string: Text values
     * number: Integer values
     * float: Decimal values
     * boolean: true/false
     * json: Valid JSON objects
     * array: Comma-separated values
   - Status Codes:
     * 200: Success
     * 400: Invalid request
     * 401: Invalid token
     * 404: Entry not found
     * 500: Server error

### Security Features
1. Token Authentication:
   - 32-byte random tokens
   - Unique per data entry
   - Cannot be modified through API
   - Automatically invalidated on token disable

2. Access Control:
   - Per-entry token requirement
   - Token validation on each request
   - IP address logging
   - Request logging
   - Data access through public API is blocked if associated user account is deactivated

3. Rate Limiting:
   - Based on IP address
   - Configurable limits
   - Prevents abuse

### Error Handling
1. Standard Error Response:
   ```json
   {
     "error": "Error message description",
     "code": "Error code (if applicable)"
   }
   ```

2. Common Error Scenarios:
   - Missing Parameters:
     ```json
     {
       "error": "Required parameter 'id' is missing"
     }
     ```
   - Authentication Failure:
     ```json
     {
       "error": "Invalid or missing token"
     }
     ```
   - Not Found:
     ```json
     {
       "error": "Data entry not found"
     }
     ```

### Usage Examples

1. Retrieve Public Entry:
   ```
   GET https://nexlab86.dkydivyansh.com/api/v1/public/data?id=123
   ```

2. Retrieve Protected Entry:
   ```
   GET https://nexlab86.dkydivyansh.com/api/v1/public/data?id=123&token=abc123...
   ```

3. Update Entry:
   ```
   PUT https://nexlab86.dkydivyansh.com/api/v1/public/data?id=123&token=abc123...
   Content-Type: application/json
   {
     "data_type": "string",
     "value": "Updated value",
     "token": "abc123..." (if required)
     "new_access_key": "new_access_key" (to change access)
   }
   ```

### Best Practices
1. Data Access:
   - Use HTTPS for all requests
   - Include proper headers
   - Handle rate limits gracefully
   - Implement error handling

2. Token Management:
   - Store tokens securely
   - Never share tokens
   - Rotate tokens periodically
   - Disable unused tokens

3. Error Handling:
   - Implement proper retry logic
   - Handle all status codes
   - Log failed attempts
   - Monitor API usage

### Limitations
1. Request Limits:
   - Maximum 1000 requests per hour per IP
   - Maximum 100 requests per minute per IP
   - Maximum payload size: 1MB

2. Data Constraints:
   - Maximum value length: 65535 bytes
   - Supported character encoding: UTF-8
   - Maximum token length: 64 characters

3. Authentication:
   - Tokens cannot be retrieved via API
   - Tokens cannot be modified via API
   - Token generation requires dashboard access


Security Measures:
- Session-based authentication required for all operations
- Access control checks for entry ownership
- Input validation and sanitization
- Token generation for protected entries
- Action logging for audit trail

Best Practices:
1. Always validate user permissions before operations
2. Log all actions for accountability
3. Use prepared statements for database queries
4. Implement proper error handling
5. Return appropriate status codes and messages
6. Maintain data consistency across operations
7. Follow security best practices for authentication
8. Use transactions for complex operations
9. Implement rate limiting for API endpoints
10. Keep logs for debugging and auditing


## Project Overview

This project implements a PHP-based REST API server with an integrated web dashboard. The system provides secure data management through both a user interface and API endpoints.

### Key Components

1. Authentication System
   - Secure login for administrators and users
   - Password hashing using PHP's password_hash()
   - Session-based authentication and access control(server side)
   - Token-based API authentication

2. Dashboard
   - Modern responsive interface built with HTML, CSS and JavaScript
   - Data management features:
     * Grid view of all stored data entries
     * Advanced search and filtering
     * Add new data entries
     * Edit existing entries (users limited to own data)
     * Delete entries with proper authorization

   - Data display:
     * ID, type, value, timestamp columns
     * Access key (if enabled)
     * Private notes (dashboard-only, not exposed via API)
     * Action buttons for edit/delete
     * API endpoint information

3. Dashboard Security
   - Session-based authentication required
   - Role-based access control
   - Users can only view/modify their own data
   - Input validation and sanitization
   - CSRF protection

4. Database Architecture
   - Normalized tables for:
     * Administrator accounts
     * User accounts 
     * Data entries
     * Session management
     * API access logs
   - Proper indexing and relationships
   - Audit trail capabilities




make admin and user can login through single login page.
single page for login and registration.
regestation is only for user.
a page for profile edit.
user cant edit username.
user can login with username and password.
registation require email, username and password.
if user account is deactivated , user cant login and if already login , user will see a account deactivated message with logged out button.

Server contains:
1. Authentication System
   - Combined login/registration page
   - Login functionality for both admin and users
   - Registration for users only (requires email, username, password)
   - Secure session management
   - Logout functionality

2. Dashboard
   - Main interface after login
   - Role-specific views and permissions
   - Data management features
   - header will also contain admn pages links when admin is login.

3. Profile Management
   - Profile editing page
   - Password change
   - Email update
   - Username display (non-editable)

4. User Management (Admin Only)
   - View all users
   - Activate/deactivate accounts
   - Reset user passwords
   - Monitor user activity

5. api logs (admin only)
   - view api logs

6. admin activity logs (admin only)
   - view admin activity logs

how server works - 
user can login with username and password.
user create a data entry on dashboard which containes id, type, value, note(optional), access key(optional), enable access key authentication checkbox  (if checkbox is checked , access key is required)
this data entry will be stored in database with dataentry id(auto_increment), timestamp and user id.
user can edit data entry on dashboard which will update type, value, note, access key(if access key authentication checkbox is enabled), enable access key authentication checkbox (if checkbox is checked , access key is required) and timestamp will auto update, cant edit id.
user can access all daya entry with public api and data-entry id, access key(if access key authentication is enabled).
user can edit data with public api (access key is required and work if access key authentication is enabled).
public api can only show single data entry at a time, public api only return dataentry id, type, value, timestamp.
public api can only update single data entry at a time, public api only update dataentry id, type, value, access key (all fields are required) and timestamp will auto update.
id user account is deactivated , that user data entryes will be blocked from public api and server eill return error message.



mension every thing in detail what created or modifyes in server in project-start.txt file.

