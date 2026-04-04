<?php

declare(strict_types=1);

namespace App\Config;

use MongoDB\Client;
use MongoDB\Database as MongoDatabase;

final class Database
{
    private static ?MongoDatabase $db = null;

    public static function connection(): MongoDatabase
    {
        if (self::$db !== null) {
            return self::$db;
        }

        $uri = (string) Env::get('MONGO_URI', 'mongodb://127.0.0.1:27017');
        $dbName = (string) Env::get('MONGO_DB', 'bbn_ms');

        $client = new Client($uri);
        self::$db = $client->selectDatabase($dbName);

        return self::$db;
    }
}
