# E-Signature Service

A secure digital signature service built with PHP that allows users to create contracts and collect electronic signatures through both a web interface and RESTful API.

## Features

- Create digital contracts
- Send signing invitations via email
- Secure signature collection with tokens
- Session management and CSRF protection
- Rate limiting for security
- Modern responsive web interface
- RESTful API for integration
- Docker support for easy deployment

## Requirements

- Docker and Docker Compose
- OR PHP 8.0+ with Composer

## Quick Start with Docker

1. Clone the repository:
```bash
git clone <repository-url>
cd e-signature-service
```

2. Configure environment variables:
```bash
cp .env.example .env
# Edit .env with your SMTP settings
```

3. Start with Docker Compose:
```bash
docker-compose up -d
```

4. Open your browser and navigate to `http://localhost:8000`

### Local Email Testing

To inspect outgoing emails during development, use the provided `docker-compose.override.yml`
which starts a MailHog container. Emails sent by the application will be
available at `http://localhost:8025`.

## Usage

### Web Interface

#### Creating a Contract
1. Navigate to the home page
2. Click "Create New Contract"
3. Fill in the contract details and add signers
4. Submit the form to create the contract

#### Signing a Contract
1. Signers receive an email with a signing link
2. Click the link to access the signing page
3. Review the contract and sign
4. Submit to complete the signing process

### API Integration

The service provides a RESTful API for programmatic access. See [API_DOCUMENTATION.md](API_DOCUMENTATION.md) for detailed documentation.

#### Quick API Example
```bash
# Create a contract
curl -X POST http://localhost:8000/api/contracts \
  -H "X-API-Key: demo-api-key-123" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Service Agreement",
    "contract_text": "Contract content here...",
    "signers": [{"email": "signer@example.com"}]
  }'

# List contracts
curl -X GET http://localhost:8000/api/contracts \
  -H "X-API-Key: demo-api-key-123"
```

## Security Features

- CSRF token protection
- Rate limiting (30 requests per minute for API, 10 for web)
- Session timeout management
- Secure token-based signing
- Input sanitization and validation
- API key authentication

## File Structure

```
├── docker-compose.yml    # Docker configuration
├── Dockerfile           # Docker image definition
├── .env                # Environment variables
├── composer.json       # PHP dependencies
├── API_DOCUMENTATION.md # API documentation
src/
├── index.php          # Main application entry point
├── functions.php      # Core business logic
├── MailClient.php     # Email sending
├── RateLimiter.php    # Rate limiting functionality
├── SessionManager.php # Session management
├── api/
│   └── index.php      # API endpoints
└── public/           # Frontend files
    ├── index.html    # Home page
    ├── create.html   # Contract creation
    └── sign.html     # Contract signing
data/
└── contracts/        # Contract storage (JSON files)
```

## Environment Configuration

Configure the following variables in your `.env` file (example uses MailHog for local testing):

```env
# SMTP Configuration
SMTP_HOST=mailhog
SMTP_PORT=1025
SMTP_USER=
SMTP_PASS=

# Application Configuration
APP_URL=http://localhost:8000
APP_ENV=development
```

SMTP must be configured for email delivery. When running `docker-compose` with the
provided `docker-compose.override.yml`, MailHog will be available at `http://localhost:8025`
and the default settings in `.env.example` will work out of the box.

## API Endpoints

- `GET /api/status` - API status and information
- `GET /api/contracts` - List all contracts
- `GET /api/contracts/{id}` - Get contract details
- `POST /api/contracts` - Create new contract
- `PUT /api/contracts/{id}` - Update contract
- `DELETE /api/contracts/{id}` - Delete contract
- `GET /api/signatures/{contract_id}` - Get signature status
- `POST /api/signatures` - Sign contract

For detailed API documentation, see [API_DOCUMENTATION.md](API_DOCUMENTATION.md).

## Troubleshooting

### Common Issues

1. **CSRF Token Error**: Ensure cookies are enabled and the session is properly maintained
2. **Email Not Sending**: Check SMTP configuration in `.env` file
3. **Permission Denied**: Ensure `data/contracts` directory has write permissions
4. **API Authentication**: Verify the `X-API-Key` header is included in requests

### Docker Issues
```bash
# View logs
docker-compose logs esig-api

# Restart services
docker-compose restart

# Rebuild containers
docker-compose up --build
```

## License

This project is licensed under the MIT License.
