# E-Signature Service API Documentation

## Overview
The E-Signature Service provides a RESTful API for managing digital contracts and signatures.

## Authentication
All API requests require an API key to be included in the `X-API-Key` header.

**Demo API Keys:**
- `demo-api-key-123`
- `test-key-456`

## Base URL
```
http://localhost:8000/api
```

## Rate Limiting
- 30 requests per minute per IP address
- Returns HTTP 429 when limit exceeded

## Endpoints

### Status
Get API status and available endpoints.

```http
GET /api/status
```

**Response:**
```json
{
  "status": "online",
  "version": "1.0.0",
  "timestamp": "2025-06-20T03:15:00+00:00",
  "endpoints": {
    "GET /api/contracts": "List all contracts",
    "GET /api/contracts/{id}": "Get contract details",
    "POST /api/contracts": "Create new contract",
    "PUT /api/contracts/{id}": "Update contract",
    "DELETE /api/contracts/{id}": "Delete contract",
    "GET /api/signatures/{contract_id}": "Get signature status",
    "POST /api/signatures": "Sign contract"
  }
}
```

### Contracts

#### List Contracts
```http
GET /api/contracts
```

**Headers:**
```
X-API-Key: demo-api-key-123
```

**Response:**
```json
{
  "contracts": [
    {
      "contract_id": "uuid-here",
      "title": "Service Agreement",
      "status": "pending",
      "created_at": "2025-06-20T03:15:00+00:00",
      "signers_count": 2,
      "signed_count": 1
    }
  ]
}
```

#### Get Contract
```http
GET /api/contracts/{contract_id}
```

**Headers:**
```
X-API-Key: demo-api-key-123
```

**Response:**
```json
{
  "contract_id": "uuid-here",
  "title": "Service Agreement",
  "contract_text": "This is the contract content...",
  "status": "pending",
  "created_at": "2025-06-20T03:15:00+00:00",
  "signers": [
    {
      "email": "signer1@example.com",
      "status": "signed",
      "signed_at": "2025-06-20T03:20:00+00:00",
      "token": "signer-token-uuid"
    },
    {
      "email": "signer2@example.com",
      "status": "pending",
      "token": "signer-token-uuid"
    }
  ]
}
```

#### Create Contract
```http
POST /api/contracts
```

**Headers:**
```
X-API-Key: demo-api-key-123
Content-Type: application/json
```

**Request Body:**
```json
{
  "title": "Service Agreement",
  "contract_text": "This is the contract content that needs to be signed...",
  "signers": [
    {
      "email": "signer1@example.com"
    },
    {
      "email": "signer2@example.com"
    }
  ]
}
```

**Response (201 Created):**
```json
{
  "contract_id": "uuid-here",
  "status": "pending",
  "signers": [
    {
      "email": "signer1@example.com",
      "status": "pending",
      "token": "signer-token-uuid"
    },
    {
      "email": "signer2@example.com",
      "status": "pending",
      "token": "signer-token-uuid"
    }
  ]
}
```

#### Update Contract
```http
PUT /api/contracts/{contract_id}
```

**Headers:**
```
X-API-Key: demo-api-key-123
Content-Type: application/json
```

**Request Body:**
```json
{
  "title": "Updated Service Agreement",
  "contract_text": "Updated contract content..."
}
```

**Response:**
```json
{
  "message": "Contract updated successfully"
}
```

#### Delete Contract
```http
DELETE /api/contracts/{contract_id}
```

**Headers:**
```
X-API-Key: demo-api-key-123
```

**Response:**
```json
{
  "message": "Contract deleted successfully"
}
```

### Signatures

#### Get Signature Status
```http
GET /api/signatures/{contract_id}
```

**Headers:**
```
X-API-Key: demo-api-key-123
```

**Response:**
```json
{
  "contract_id": "uuid-here",
  "status": "pending",
  "signers": [
    {
      "email": "signer1@example.com",
      "status": "signed",
      "signed_at": "2025-06-20T03:20:00+00:00"
    },
    {
      "email": "signer2@example.com",
      "status": "pending"
    }
  ],
  "created_at": "2025-06-20T03:15:00+00:00",
  "completed_at": null
}
```

#### Sign Contract
```http
POST /api/signatures
```

**Headers:**
```
X-API-Key: demo-api-key-123
Content-Type: application/json
```

**Request Body:**
```json
{
  "contract_id": "uuid-here",
  "signer_email": "signer1@example.com",
  "token": "signer-token-uuid"
}
```

**Response:**
```json
{
  "contract_id": "uuid-here",
  "title": "Service Agreement",
  "status": "completed",
  "completed_at": "2025-06-20T03:25:00+00:00",
  "signers": [
    {
      "email": "signer1@example.com",
      "status": "signed",
      "signed_at": "2025-06-20T03:20:00+00:00"
    },
    {
      "email": "signer2@example.com",
      "status": "signed",
      "signed_at": "2025-06-20T03:25:00+00:00"
    }
  ]
}
```

## Error Responses

### 400 Bad Request
```json
{
  "error": "Missing required fields: title, contract_text, signers"
}
```

### 401 Unauthorized
```json
{
  "error": "Invalid API key"
}
```

### 404 Not Found
```json
{
  "error": "Contract not found"
}
```

### 405 Method Not Allowed
```json
{
  "error": "Method not allowed"
}
```

### 429 Too Many Requests
```json
{
  "error": "Rate limit exceeded"
}
```

### 500 Internal Server Error
```json
{
  "error": "Internal server error message"
}
```

## Example Usage

### Using cURL

#### Create a contract:
```bash
curl -X POST http://localhost:8000/api/contracts \
  -H "X-API-Key: demo-api-key-123" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Service Agreement",
    "contract_text": "This is a test contract...",
    "signers": [
      {"email": "test@example.com"}
    ]
  }'
```

#### List contracts:
```bash
curl -X GET http://localhost:8000/api/contracts \
  -H "X-API-Key: demo-api-key-123"
```

#### Get contract details:
```bash
curl -X GET http://localhost:8000/api/contracts/{contract_id} \
  -H "X-API-Key: demo-api-key-123"
```

### Using JavaScript (fetch)

```javascript
// Create contract
const response = await fetch('http://localhost:8000/api/contracts', {
  method: 'POST',
  headers: {
    'X-API-Key': 'demo-api-key-123',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    title: 'Service Agreement',
    contract_text: 'This is a test contract...',
    signers: [
      { email: 'test@example.com' }
    ]
  })
});

const contract = await response.json();
console.log(contract);
```

## Environment Variables

Make sure to configure the following environment variables in your `.env` file:

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
APP_URL=http://localhost:8000
APP_ENV=development
