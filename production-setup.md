# Production Setup for PlayerProfit Betting Tracker

## Secure API Key Storage

The application now includes encrypted session storage for LLM API keys. Here's how to configure it for production:

### Environment Variables

Set the following environment variable on your production server:

```bash
# Generate a secure 32-character encryption key
export API_ENCRYPTION_KEY="your-32-character-encryption-key-here"
```

### Apache/HTTPD Configuration

Add to your virtual host configuration:

```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /path/to/playerprofit
    
    # Set the encryption key
    SetEnv API_ENCRYPTION_KEY "your-32-character-encryption-key-here"
    
    # Other SSL and security configurations...
</VirtualHost>
```

### Docker Configuration

If using Docker, add the environment variable to your docker-compose.yml:

```yaml
version: '3.8'
services:
  playerprofit:
    build: .
    environment:
      - API_ENCRYPTION_KEY=your-32-character-encryption-key-here
    volumes:
      - ./data:/var/www/html/data
    ports:
      - "80:80"
```

### Security Features

- **AES-256-GCM Encryption**: API keys are encrypted using industry-standard encryption
- **Session-based Storage**: Keys are stored in encrypted PHP sessions (not files)
- **4-Hour Expiration**: Keys automatically expire after 4 hours
- **Server-specific Fallback**: If no env var is set, generates server-specific key
- **Masked Display**: Only shows partial key for verification (first 8 chars + ***)

### Key Generation

Generate a secure encryption key:

```bash
# Linux/Mac
openssl rand -hex 32

# Or use PHP
php -r "echo bin2hex(random_bytes(32));"
```

### File Permissions

Ensure the includes directory has proper permissions:

```bash
chmod 755 includes/
chmod 644 includes/ApiKeyManager.php
```

### Production Security Checklist

- [ ] Set `API_ENCRYPTION_KEY` environment variable
- [ ] Enable HTTPS/SSL
- [ ] Set proper file permissions on data/ directory
- [ ] Configure CSP headers (already included)
- [ ] Set up regular backups of data/ directory
- [ ] Monitor PHP error logs
- [ ] Consider using a reverse proxy (Nginx) for additional security

The application will work without the environment variable (using server-specific fallback), but setting `API_ENCRYPTION_KEY` is recommended for production deployments.