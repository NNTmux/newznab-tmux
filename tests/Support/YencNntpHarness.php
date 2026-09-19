<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\NNTP\NNTPService;
use App\Services\YencService;

final class YencNntpHarness extends NNTPService
{
    /** @var list<string> */
    public array $commands = [];

    /** @param resource $stream */
    public function __construct($stream, ?YencService $decoder = null)
    {
        $this->_socket = $stream;
        $this->_yencService = $decoder;
    }

    public function group(): mixed
    {
        return 'alt.test';
    }

    protected function _sendCommand(string $cmd): mixed
    {
        $this->commands[] = $cmd;

        return 222;
    }

    public function fetch(bool $withGroup = false): mixed
    {
        return $withGroup ? $this->_getMessage('alt.test', 'test@example') : $this->_getMessageByMessageID('test@example');
    }
}
