# Jardis DotEnv

![Build Status](https://github.com/jardiscore/dotenv/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](https://phpstan.org/)
[![PSR-4](https://img.shields.io/badge/autoload-PSR--4-blue.svg)](https://www.php-fig.org/psr/psr-4/)
[![PSR-12](https://img.shields.io/badge/code%20style-PSR--12-blue.svg)](https://www.php-fig.org/psr/psr-12/)
[![Coverage](https://img.shields.io/badge/coverage->90%25-brightgreen)](https://github.com/jardiscore/dotenv)

### A support tool for reading .env files in global and protected contexts

## Description

Jardis Core DotEnv is a PHP library for reading `.env*` files with advanced features like type casting, variable substitution, and environment-specific file loading.

The extracted values can be made available either globally (`$_ENV`, `$_SERVER`, `putenv()`) or in protected contexts, such as domain-specific configurations. This approach enables different environment settings for different application domains without interference—particularly useful when refactoring monolithic applications into domain-driven architectures.

## Features

- **Global and protected contexts**: Load values into `$_ENV`/`$_SERVER` or return as isolated arrays
- **Modular configuration**: Include other `.env` files using `load()` and `load?()` directives
- **Automatic type casting**: Converts strings to `bool`, `int`, `float`, or `array` based on content
- **Variable substitution**: Use `${VARIABLE_NAME}` syntax for dynamic value resolution
- **Home directory expansion**: Automatically expands `~` to the user's home path
- **Environment-specific loading**: Automatically loads `.env`, `.env.local`, `.env.{APP_ENV}`, and `.env.{APP_ENV}.local` files
- **Array support**: Parse both indexed and associative arrays with nested type casting
- **Circular reference detection**: Prevents infinite loops when including files
- **Fully customizable**: Inject custom file readers, type casters, or file discovery strategies
- **PSR-4 Compatible**: Follows PSR-4 autoloading standards

## Installation

```bash
composer require jardiscore/dotenv
```

## Requirements

- PHP >= 8.2
- jardispsr/dotenv ^1.0

## Usage

### Basic Usage

```php
use JardisCore\DotEnv\DotEnv;

$dotEnv = new DotEnv();

// Load .env files into global scope ($_ENV, $_SERVER, putenv)
$dotEnv->loadPublic('/path/to/app');

// Load .env files into protected scope (returns array without polluting globals)
$domainEnv = $dotEnv->loadPrivate('/path/to/domain');
```

### Supported Data Types

The library automatically casts values to the following types:

| Type | Example |
|------|---------|
| **String** | `APP_NAME=MyApp` |
| **Boolean** | `DEBUG=true` or `DEBUG=false` |
| **Integer** | `MAX_CONNECTIONS=100` |
| **Float** | `TIMEOUT=2.5` |
| **Array** | `PORTS=[80,443]` or `CONFIG=[key=>value,nested=>[1,2,3]]` |

### Example `.env` File

```env
# Strings
APP_NAME=MyApplication

# Booleans
DEBUG=true
MAINTENANCE=false

# Numerics
MAX_CONNECTIONS=100
TIMEOUT=2.5

# Arrays (indexed and associative with nested type casting)
PORTS=[80,443,8080]
CONFIG=[host=>localhost,port=>3306,options=>[ssl=>true,timeout=>30]]

# Variable substitution
DB_HOST=localhost
DB_NAME=mydb
DATABASE_URL=mysql://${DB_HOST}/${DB_NAME}

# Home directory expansion
LOG_PATH=~/logs/app.log
```

## Advanced Features

### Modular Configuration with Includes

Split your configuration into smaller, focused files using the `load()` directive:

```env
# .env
APP_NAME=MyApplication
APP_ENV=production

# Load modular configurations
load(.env.database)
load(.env.logger)
load?(.env.cache)      # Optional - silent skip if missing
load?(.env.local)      # Local overrides (gitignored)
```

```env
# .env.database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=mydb
DB_USER=root
DB_PASS=secret
```

```env
# .env.logger
LOG_LEVEL=warning
LOG_CHANNEL=file
LOG_PATH=/var/log/app.log
```

**Include Syntax:**

| Directive | Behavior |
|-----------|----------|
| `load(.env.file)` | Required include - throws `EnvFileNotFoundException` if missing |
| `load?(.env.file)` | Optional include - silently skips if file doesn't exist |

**Path Resolution:**
- Relative paths are resolved relative to the including file's directory
- Absolute paths are used as-is
- Quoted paths support spaces: `load("path with spaces/.env")`

**Override Behavior:**
Later definitions override earlier ones. This enables local overrides:

```env
# .env.database
DB_HOST=production-db.example.com

# .env.local (loaded after, overrides DB_HOST)
DB_HOST=localhost
```

**Nested Includes:**
Files can include other files (A → B → C), with automatic circular reference detection:

```env
# .env.a includes .env.b, which includes .env.c - works fine
# .env.a includes .env.b, which includes .env.a - throws CircularEnvIncludeException
```

### Environment-Specific File Loading

The library automatically loads files in the following order based on the `APP_ENV` environment variable:

1. `.env` - Base configuration
2. `.env.local` - Local overrides (not committed to version control)
3. `.env.{APP_ENV}` - Environment-specific (e.g., `.env.production`, `.env.development`)
4. `.env.{APP_ENV}.local` - Local environment-specific overrides

**Example:**
```bash
# Set environment
export APP_ENV=production

# Will load (if they exist):
# 1. .env
# 2. .env.local
# 3. .env.production
# 4. .env.production.local
```

### Custom Type Casting

Add or remove type casting classes to customize value transformation:

```php
use JardisCore\DotEnv\Casting\CastTypeHandler;
use JardisCore\DotEnv\Casting\CastStringToBool;

$castTypeHandler = new CastTypeHandler();

// Add custom type caster
$castTypeHandler->setCastTypeClass(MyCustomCaster::class);

// Remove existing type caster
$castTypeHandler->removeCastTypeClass(CastStringToBool::class);
```

**Type Casting Chain:**

The `CastTypeHandler` runs the following casters in sequence (stops early if value becomes non-string):

1. `CastStringToValue` - Variable substitution (`${VAR}`)
2. `CastUserHome` - Home directory expansion (`~`)
3. `CastStringToNumeric` - Numeric conversion
4. `CastStringToBool` - Boolean conversion
5. `CastStringToArray` - Array parsing

### Dependency Injection

Customize the entire processing pipeline via constructor injection:

```php
use JardisCore\DotEnv\DotEnv;
use JardisCore\DotEnv\Reader\LoadFilesFromPath;
use JardisCore\DotEnv\Reader\LoadValuesFromFiles;
use JardisCore\DotEnv\Casting\CastTypeHandler;

$dotEnv = new DotEnv(
    fileFinder: new LoadFilesFromPath(),              // Custom file discovery
    fileContentReader: new LoadValuesFromFiles(       // Custom file parsing
        new CastTypeHandler()
    ),
    CastTypeHandler: new CastTypeHandler()            // Custom type casting
);
```

**Customization Points:**

- **`LoadFilesFromPath`**: Change which files are loaded and in what order
- **`LoadValuesFromFiles`**: Modify how file contents are parsed (e.g., different comment syntax)
- **`CastTypeHandler`**: Add/remove type converters or change their execution order

## Architecture

### Core Components

- **`DotEnv`** (`src/DotEnv.php`)
  Main entry point with two public methods:
  - `loadPublic(string $path)`: Loads `.env` files and populates `$_ENV`, `$_SERVER`, and `putenv()`
  - `loadPrivate(string $path)`: Loads `.env` files and returns values as an array without polluting global state

- **File Readers** (`src/Reader/`)
  - `LoadFilesFromPath`: Determines which `.env` files to load based on directory path and `APP_ENV` variable
  - `LoadValuesFromFiles`: Reads `.env` files line by line, processes includes, ignores comments (`#`), delegates type casting
  - `ParseLoadDirective`: Parses `load()` and `load?()` directives from `.env` lines

- **Type Casters** (`src/Casting/`)
  - `CastTypeHandler`: Orchestrates type conversion chain
  - `CastStringToValue`: Variable substitution (`${VAR_NAME}`)
  - `CastUserHome`: Expands `~` to user home directory
  - `CastStringToNumeric`: Converts numeric strings to `int`/`float`
  - `CastStringToBool`: Converts `"true"`/`"false"` to boolean
  - `CastStringToArray`: Parses array syntax `[1,2,3,key=>value]`

- **Exceptions** (`src/Exception/`)
  - `DotEnvException`: Base exception class
  - `CircularEnvIncludeException`: Thrown when circular includes are detected
  - `EnvFileNotFoundException`: Thrown when a required include file is missing
  - `EnvFileNotReadableException`: Thrown when a file exists but cannot be read
  - `EnvParseException`: Thrown for syntax errors in `.env` files

## Development

### Setup

```bash
# Install dependencies
make install

# Run all quality checks
make phpcs phpstan phpunit
```

### Available Commands

All development commands use Docker Compose. Run `make help` for the complete list.

**Dependency Management:**
```bash
make install          # Install composer dependencies
make update           # Update composer dependencies
make autoload         # Regenerate autoloader
```

**Testing:**
```bash
make phpunit                  # Run all tests
make phpunit-coverage         # Run tests with text coverage
make phpunit-coverage-html    # Generate HTML coverage report
make phpunit-reports          # Generate clover/XML coverage reports
```

**Code Quality:**
```bash
make phpstan          # Run static analysis (level 8)
make phpcs            # Run coding standards check (PSR-12)
```

**Docker:**
```bash
make shell            # Open shell in PHP container
make remove           # Remove all containers, images, volumes
```

### Running Specific Tests

```bash
# Run a specific test file
docker compose -f support/docker-compose.yml run --rm phpcli \
  vendor/bin/phpunit --bootstrap ./tests/bootstrap.php tests/unit/DotEnvTest.php

# Run tests for a specific directory
docker compose -f support/docker-compose.yml run --rm phpcli \
  vendor/bin/phpunit --bootstrap ./tests/bootstrap.php tests/unit/casting/
```

## Contributing

### Code Quality Standards

This project enforces strict quality standards:

- **PHPStan**: Level 8 static analysis
- **PHPCS**: PSR-12 coding standard (120 char soft limit, 150 hard limit)
- **PHPUnit**: Comprehensive test coverage
- **Pre-commit hooks**: Automated validation before commits

### Pre-commit Hooks

Git hooks are automatically installed via `composer post-install-cmd`. The hook validates:

1. Branch naming: `feature/`, `fix/`, or `hotfix/` followed by `{issue_number}_{description}`
   Example: `feature/123_add-type-casting`
2. Git username must not contain special characters
3. All staged PHP files must pass PHPCS standards

### Coding Patterns

- All classes use `declare(strict_types=1)` and strict type hints
- Reader classes are invokable (`__invoke()` method)
- Caster classes accept `CastTypeHandler` in constructor for recursive casting
- Custom exception hierarchy with `DotEnvException` as base class
- Array type hints use PHPDoc: `@param array<string, mixed>`

## License

MIT License - see LICENSE file for details

## Support

- Issues: https://github.com/jardiscore/dotenv/issues
- Email: jardisCore@headgent.dev
