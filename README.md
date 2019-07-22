Graphael
========

Graphael is a framework for rapidly building GraphQL API Servers.

## Usage

Create a new project directory, and add the following line to your `composer.json` in the `require` section:

```json
"require": {
  "linkorb/graphael": "^1.0"
}
```

Create a `public/` directory, with an `index.php` file like the following:

```php
<?php

use Graphael\Server;
use Symfony\Component\Dotenv\Dotenv;

$loader = require_once __DIR__.'/../vendor/autoload.php';

// Load .env file if it exists
$envFilename = __DIR__ . '/../.env';
if (file_exists($envFilename)) {
    $dotenv = new Dotenv();
    $dotenv->load($envFilename);
}

// Application level configuration
$config = [
    'environment_prefix' => 'MY_API_',
    'type_namespace' => 'MyApi\\Type', //
    'type_path' => __DIR__ . '/../src/Type' // Directory to scan for Type classes
    'type_postfix' => 'Type',
];

// Instantiate a GraphQL server based on the configuration
$server = new Server($config);
$server->handleRequest();
```

### Application configuration

The server is being instantiated with a `$config` array that contains the following configuration options:

* `environment_prefix`: Prefix of your environment config variables
* `type_path`: Directory to scan for Type class files
* `type_namespace`: Namespace of your Type classes. Should match PSR 4 namespace in your `composer.json`
* `type_postfix`: Postfix of your type classes. Defaults to `Type`.

### Environment configuration

Create a `.env` file (or use other means to configure your application's environment variables).

Each variable is prefixed with the `environment_prefix` defined earlier, in this example `MY_API_`:

```ini
MY_API_DEBUG=1
MY_API_PDO_URL=mysql://username:password@localhost/my_db
MY_API_JWT_KEY=supersecret
```

Supported environment variables:

* `DEBUG`: Set to `1` to run the app in debug mode
* `PDO_URL`: Connection string to your database. Supports all PDO backends
* `JWT_KEY`: Optional. If defined, the API only allows connections with JWTs signed with this key. Can be a string value or an absolute path to a public key file.

### Authentication

If the `JWT_KEY` environment variable is defined, the server checks for a JWT in one of two places:

1. A `jwt` query parameter (i.e. `/graphql?jwt=abc.def.ghi`)
2. A `X-Authorization` HTTP header (i.e. `X-Authorization: Bearer abc.def.ghi`)

