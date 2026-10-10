<?php

declare(strict_types=1);

use Minilytics\Http\Http;
use Minilytics\OAuth\OAuthServer;

// RFC 8414 metadata of the authorization server whose issuer is /mcp.php.
require_once __DIR__ . '/../../vendor/autoload.php';
Http::allowCors('GET');
Http::json(OAuthServer::authorizationServerMetadata());
