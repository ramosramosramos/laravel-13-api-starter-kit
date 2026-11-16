# Laravel Decoupled REST API

A RESTful API backend built with **Laravel 13**, designed for integration with a separate frontend application using **React and TypeScript**.

The project demonstrates API-first development, token-based authentication, request validation, automated testing, OpenAPI documentation, and continuous integration practices.

## Technology Stack

| Technology | Purpose |
|---|---|
| PHP 8.4 | Backend programming language |
| Laravel 13 | REST API framework |
| Laravel Sanctum | Bearer token authentication |
| MySQL | Application database |
| Pest | Automated feature and architecture testing |
| Laravel Pint | Code formatting |
| Larastan / PHPStan | Static analysis |
| Scramble | OpenAPI documentation |
| GitHub Actions | Continuous integration |

## Architecture

The application follows a decoupled architecture, where the backend and frontend operate independently.

```text
React + TypeScript Frontend
           |
           | HTTPS / JSON
           | Bearer Token
           v
Laravel REST API
           |
           | Authentication
           | Validation
           | Business Logic
           v
       Database
```

The frontend communicates with Laravel through versioned REST API endpoints.

## Project Structure

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   ├── Requests/
│   └── Resources/
├── Models/
└── Providers/

routes/
└── api.php

tests/
├── Feature/
│   └── Auth/
├── Unit/
└── Architecture/

.github/
└── workflows/
```

Additional directories may be introduced as the application grows.

## Installation

### Requirements

- PHP 8.4
- Composer
- MySQL
- Required PHP extensions
- Git

### Clone the repository

```bash
git clone https://github.com/ramosramosramos/laravel-13-api-starter-kit.git
cd laravel-13-api-starter-kit
```

### Install dependencies

```bash
composer install
```

### Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Update your `.env` file:

```dotenv
APP_URL=http://laravel-13-api-starter-kit.test
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=backend_decoupled
DB_USERNAME=root
DB_PASSWORD=
```

Create the database, then run:

```bash
php artisan migrate
```

### Start the application

```bash
php artisan serve
```

By default, the application is available at:

`http://127.0.0.1:8000`

If using Laravel Herd, the local domain may be:

`http://laravel-13-api-starter-kit.test`

## API Versioning

All application API endpoints use the `/api/v1` prefix.

Example:

```http
GET /api/v1/auth/me
```

Future API versions can be introduced without immediately breaking existing integrations.

## Authentication

The API uses **Laravel Sanctum personal access tokens**.

Protected endpoints require an Authorization header:

```http
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
```

### Authentication endpoints

| Method | Endpoint | Authentication |
|---|---|---|
| POST | `/api/v1/auth/register` | Public |
| POST | `/api/v1/auth/login` | Public |
| GET | `/api/v1/auth/me` | Required |
| POST | `/api/v1/auth/logout` | Required |
| PATCH | `/api/v1/auth/profile` | Required |
| PATCH | `/api/v1/auth/change-password` | Required |
| POST | `/api/v1/auth/forgot-password` | Public |
| POST | `/api/v1/auth/reset-password` | Public |
| GET | `/api/v1/auth/email/verify/{id}/{hash}` | Signed URL |
| POST | `/api/v1/auth/email/verification-notification` | Required |

### Login example

**Request**

```http
POST /api/v1/auth/login
Content-Type: application/json
Accept: application/json
```

```json
{
  "email": "user@example.com",
  "password": "YourPassword123!"
}
```

**Example successful response**

```json
{
  "message": "Login successful.",
  "user": {
    "id": 1,
    "name": "Example User",
    "email": "user@example.com"
  },
  "token": "YOUR_ACCESS_TOKEN"
}
```

### Authenticated request example

```javascript
const response = await fetch(
  "http://laravel-13-api-starter-kit.test/api/v1/auth/me",
  {
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
  }
);

const user = await response.json();
```

Access tokens must be treated as secrets and should not be exposed in logs or public repositories.

## API Documentation

The API uses **Scramble** to generate OpenAPI documentation from Laravel routes, validation rules, and response definitions.

### Interactive documentation

When running the application locally:

- [API Documentation](http://laravel-13-api-starter-kit.test/docs/api)
- [OpenAPI JSON](http://laravel-13-api-starter-kit.test/docs/api.json)

These URLs assume Laravel Herd is configured with the `backend-decoupled.test` domain.

When using `php artisan serve`, replace the domain with `http://127.0.0.1:8000`.

### Validate API documentation

```bash
php artisan scramble:analyze --fail-on-empty --fail-on-unknown
```

The strict analyzer should pass before being enforced as a required CI check.

Documentation access outside the local environment should be explicitly configured and secured as appropriate.

## Automated Testing

The project uses **Pest** for automated testing.

### Run all tests

```bash
php artisan test
```

### Run authentication tests

```bash
php artisan test tests/Feature/Auth
```

### Run a specific test suite

```bash
php artisan test --filter=PasswordResetTest
```

### Run architecture tests

```bash
php artisan test tests/Unit/ArchitectureTest.php
```

Architecture tests enforce project conventions such as class naming, inheritance, and dependency boundaries.

### Authentication testing coverage

The authentication test suites cover:

- User registration and login
- Sanctum token authentication
- Logout and token revocation
- Password changes
- Profile updates
- Forgot password and password resets
- Email verification and signed URLs
- Unauthorized access and security checks

## Code Quality

### Laravel Pint

Check formatting:

```bash
vendor/bin/pint --test
```

Automatically fix formatting:

```bash
vendor/bin/pint
```

### Larastan / PHPStan

Run static analysis when configured:

```bash
vendor/bin/phpstan analyse --no-progress
```

### Scramble

Check OpenAPI schema generation:

```bash
php artisan scramble:analyze --fail-on-empty --fail-on-unknown
```

## Continuous Integration

The project is designed to use GitHub Actions to run automated quality checks on pushes and pull requests.

Recommended CI checks:

1. Install Composer dependencies.
2. Validate PHP formatting using Pint.
3. Run static analysis using Larastan.
4. Run Pest automated tests.
5. Validate the OpenAPI specification using Scramble.

Deployment automation should be configured separately when a staging or production environment is available.

## Email Verification

The application generates signed email verification URLs.

Verification links are directed to the React frontend, which can forward the signed verification parameters to the Laravel backend.

Signed verification URLs expire and must pass signature and email hash validation.

## Password Reset

Password reset notifications direct users to the frontend reset-password page.

The frontend submits the reset token, email address, and new password to the backend.

Laravel validates the reset token and securely updates the stored password hash.

## Security

The backend implements or is designed around the following security practices:

- Sanctum Bearer token authentication
- Laravel Form Request validation
- Hashed passwords
- Signed email verification URLs
- Password reset token validation
- Token revocation
- Protected API endpoints
- Automated security-related feature tests

Production deployments should additionally enforce HTTPS, appropriate CORS configuration, rate limiting, secure environment variables, and monitoring.

## Frontend Integration

The backend is designed to work independently from its frontend.

Suggested frontend stack:

- React
- TypeScript
- Tailwind CSS
- Fetch API or Axios

Frontend developers should use the generated OpenAPI documentation as the reference for endpoint contracts.

## Development Status

This project is under active development.

Current work focuses on authentication, API documentation, automated testing, code quality, and CI/CD readiness.

## License

See the repository's `LICENSE` file for applicable licensing terms, if provided.
