<?php

declare(strict_types=1);

namespace SchemaGuard\ValueObjects;

enum SurfaceType: string
{
    case MODEL_SCHEMA = 'model_schema';
    case ELOQUENT_QUERY = 'eloquent_query';
    case API_RESOURCE = 'api_resource';
    case CONTROLLER = 'controller';
    case RELATION = 'relation';
    case RAW_SQL = 'raw_sql';
}
