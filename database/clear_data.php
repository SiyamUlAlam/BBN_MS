<?php

declare(strict_types=1);

use App\Config\Database;
use App\Config\Env;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
Env::load($root);

$args = $argv ?? [];
$runAll = in_array('--all', $args, true);
$confirmed = in_array('--yes', $args, true);

if (!$confirmed) {
    fwrite(STDOUT, "Refusing to run without confirmation.\n");
    fwrite(STDOUT, "Usage: php database/clear_data.php --yes [--all]\n");
    fwrite(STDOUT, "Modes:\n");
    fwrite(STDOUT, "  default: clear transactional customer data only\n");
    fwrite(STDOUT, "  --all: clear all entered data including topology, products, packages, and users\n");
    exit(1);
}

$transactionalCollections = [
    'customers',
    'connection_orders',
    'bills',
    'payments',
    'income_entries',
    'expense_entries',
    'support_tickets',
];

$allCollections = [
    'zones',
    'areas',
    'line_sources',
    'distribution_boxes',
    'packages',
    'products',
    'users',
];

$targetCollections = $runAll
    ? array_merge($transactionalCollections, $allCollections)
    : $transactionalCollections;

$db = Database::connection();
$dbName = (string) Env::get('MONGO_DB', 'bbn_ms');

fwrite(STDOUT, "Clearing MongoDB database '{$dbName}'...\n");

$totalDeleted = 0;
foreach ($targetCollections as $collectionName) {
    $result = $db->selectCollection($collectionName)->deleteMany([]);
    $deleted = $result->getDeletedCount();
    $totalDeleted += $deleted;
    fwrite(STDOUT, sprintf(" - %-20s %d deleted\n", $collectionName . ':', $deleted));
}

fwrite(STDOUT, "Done. Total deleted documents: {$totalDeleted}\n");

if ($runAll) {
    fwrite(STDOUT, "Note: users collection was cleared. Default admin is recreated automatically on next app boot.\n");
}
