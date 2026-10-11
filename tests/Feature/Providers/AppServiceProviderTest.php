<?php

use Illuminate\Foundation\Console\ServeCommand;

test('artisan serve passes the Windows temp folder to its workers so uploads work', function () {
    expect(ServeCommand::$passthroughVariables)->toContain('TEMP', 'TMP');
});
