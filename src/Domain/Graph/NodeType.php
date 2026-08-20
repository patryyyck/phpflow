<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Graph;

enum NodeType: string
{
    case ROUTE = 'route';
    case CONTROLLER = 'controller';
    case MESSAGE = 'message';
    case HANDLER = 'handler';
    case REPOSITORY = 'repository';
    case HTTP_ENDPOINT = 'http_endpoint';
    case SERVICE = 'service';
    case DATABASE = 'database';
    case MAIL = 'mail';
    case FILESYSTEM = 'filesystem';
    case CACHE = 'cache';
    case EXCEPTION = 'exception';
    case CONDITION = 'condition';
    case RETURN_VALUE = 'return_value';
    case HTTP_RESPONSE = 'http_response';
    case CONTINUATION = 'continuation';
    case CONTROL_BRANCH = 'control_branch';
    case LOOP = 'loop';
    case LOOP_CONTROL = 'loop_control';
}
