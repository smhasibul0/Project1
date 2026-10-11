<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * The live site runs MySQL, which refuses any table, column, index or key name over
 * 64 characters — the tests run on SQLite, which does not. Every migration is played
 * through a MySQL connection in pretend mode (no server needed: nothing is sent) and
 * each name it writes is checked.
 */
test('every name the migrations give fits MySQL\'s 64-character limit', function () {
    config(['database.connections.mysql_names' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'pretend', 'username' => 'pretend', 'password' => '', 'prefix' => '',
    ]]);
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('mysql_names');

    // The grammar asks the server its version; a stand-in answers so nothing connects.
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('getAttribute')->andReturn('8.0.36');
    $pdo->shouldReceive('quote')->andReturnUsing(fn ($value) => "'".addslashes((string) $value)."'");
    DB::connection('mysql_names')->setPdo($pdo)->setReadPdo($pdo);

    try {
        $queries = DB::connection('mysql_names')->pretend(function () {
            foreach (File::glob(database_path('migrations/*.php')) as $file) {
                (require $file)->up();
            }
        });
    } finally {
        DB::setDefaultConnection($default);
    }

    $tooLong = collect($queries)
        ->flatMap(fn (array $query) => preg_match_all('/`([^`]+)`/', $query['query'], $names) ? $names[1] : [])
        ->unique()
        ->filter(fn (string $name) => strlen($name) > 64)
        ->values()
        ->all();

    expect($queries)->not->toBeEmpty();
    expect($tooLong)->toBe([]);
});
