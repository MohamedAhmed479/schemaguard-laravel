<?php

declare(strict_types=1);

namespace SchemaGuard\Graph;

enum NodeType: string
{
    case COLUMN = 'column';
    case TABLE = 'table';
    case MODEL = 'model';
    case RESOURCE = 'resource';
    case CONTROLLER = 'controller';
    case CONTROLLER_ACTION = 'controller_action';
    case ROUTE = 'route';
}
