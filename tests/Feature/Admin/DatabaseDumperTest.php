<?php

use App\Services\Backup\DatabaseDumper;
use Illuminate\Support\Facades\DB;

/*
 * Runs against a scratch database of its own, because restoring drops and
 * creates tables, which would end the transaction that isolates each test.
 */

beforeEach(function () {
    $default = config('database.connections.'.config('database.default'));
    $this->scratchDatabase = $default['database'].'_dumper';

    $this->server = new PDO("mysql:host={$default['host']};port={$default['port']}", $default['username'], $default['password']);
    $this->server->exec("DROP DATABASE IF EXISTS `{$this->scratchDatabase}`");
    $this->server->exec("CREATE DATABASE `{$this->scratchDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    config(['database.connections.scratch' => [...$default, 'database' => $this->scratchDatabase]]);

    $scratch = DB::connection('scratch');
    $scratch->unprepared('CREATE TABLE offices (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL) ENGINE=InnoDB');
    $scratch->unprepared('CREATE TABLE staff (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, office_id BIGINT UNSIGNED NOT NULL, name VARCHAR(100) NOT NULL, notes TEXT NULL, salary DECIMAL(12,2) NOT NULL, CONSTRAINT staff_office FOREIGN KEY (office_id) REFERENCES offices (id)) ENGINE=InnoDB');
    $scratch->unprepared('CREATE TABLE sessions (id VARCHAR(40) PRIMARY KEY, payload TEXT NOT NULL) ENGINE=InnoDB');

    $scratch->table('offices')->insert([['id' => 1, 'name' => "Mayor's Office"], ['id' => 2, 'name' => 'Treasurer']]);
    $scratch->table('staff')->insert([
        ['office_id' => 1, 'name' => 'Ma. Peña', 'notes' => "Line one\nLine two; with a semicolon;\r\nand a back\\slash", 'salary' => '27000.00'],
        ['office_id' => 2, 'name' => 'Juan "JR" Cruz', 'notes' => null, 'salary' => '0.50'],
    ]);
    $scratch->table('sessions')->insert(['id' => 'current-session', 'payload' => 'kept']);

    $this->path = tempnam(sys_get_temp_dir(), 'dump');
});

afterEach(function () {
    DB::purge('scratch');
    $this->server->exec("DROP DATABASE IF EXISTS `{$this->scratchDatabase}`");
    @unlink($this->path);
});

it('restores exactly what it dumped', function () {
    $dumper = new DatabaseDumper('scratch');
    $scratch = DB::connection('scratch');
    $before = $scratch->table('staff')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

    $dumper->dump($this->path);

    $scratch->table('staff')->where('office_id', 2)->delete();
    $scratch->table('staff')->where('id', 1)->update(['name' => 'Changed']);
    $scratch->unprepared('CREATE TABLE added_later (id INT PRIMARY KEY) ENGINE=InnoDB');

    $dumper->restore($this->path);

    expect($scratch->table('staff')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all())->toBe($before)
        ->and($scratch->table('offices')->pluck('name')->all())->toBe(["Mayor's Office", 'Treasurer'])
        ->and(collect($scratch->select('SHOW TABLES'))->map(fn ($row) => array_values((array) $row)[0])->all())->not->toContain('added_later');

    $scratch->table('staff')->insert(['office_id' => 2, 'name' => 'New', 'salary' => '1.00']);
    expect($scratch->table('staff')->max('id'))->toBe(3);
});

it('leaves session and cache tables alone', function () {
    $dumper = new DatabaseDumper('scratch');
    $scratch = DB::connection('scratch');

    $dumper->dump($this->path);
    $scratch->table('sessions')->insert(['id' => 'signed-in-after-backup', 'payload' => 'kept']);
    $dumper->restore($this->path);

    expect(file_get_contents($this->path))->not->toContain('current-session')
        ->and($scratch->table('sessions')->pluck('id')->sort()->values()->all())->toBe(['current-session', 'signed-in-after-backup']);
});

it('writes one statement per line', function () {
    (new DatabaseDumper('scratch'))->dump($this->path);

    $lines = array_filter(explode("\n", file_get_contents($this->path)), fn (string $line): bool => $line !== '' && ! str_starts_with($line, '--'));

    expect($lines)->each->toEndWith(';');
});
