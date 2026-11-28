<?php
namespace ManualDemo;

// Web, queue and inspect processes share the local demo's SQLite file.
// Laravel rolls back a busy transaction before retrying; arbitrary errors are
// still thrown immediately. Production connections never include this class.
class SqliteConnection extends \Illuminate\Database\SQLiteConnection
{
    public function transaction(\Closure $callback, $attempts = 3)
    {
        return parent::transaction($callback, min(3, max(1, $attempts)));
    }
}
