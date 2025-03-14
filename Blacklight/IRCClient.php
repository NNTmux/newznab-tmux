<?php

namespace Blacklight;

use Blacklight\utility\Utility;

/**
 * Basic IRC client for fetching IRCScraper.
 *
 *
 * Class IRCClient
 */
class IRCClient
{
    /**
     * Hostname IRC server used when connecting.
     */
    protected string $_remote_host = '';

    /**
     * Port number IRC server.
     */
    protected int $_remote_port = 6667;

    /**
     * Socket transport type for the IRC server.
     */
    protected string $_remote_transport = 'tcp';

    /**
     * Hostname the IRC server sent us back.
     */
    protected string $_remote_host_received = '';

    /**
     * String used when creating the stream socket.
     */
    protected string $_remote_socket_string = '';

    /**
     * Are we using tls/ssl?
     */
    protected bool $_remote_tls = false;

    /**
     * Time in seconds to timeout on connect.
     */
    protected int $_remote_connection_timeout = 30;

    /**
     * Time in seconds before we timeout when sending/receiving a command.
     */
    protected int $_socket_timeout = 180;

    /**
     * How many times to retry when connecting to IRC.
     */
    protected int $_reconnectRetries = 3;

    /**
     * Seconds to delay when reconnecting fails.
     */
    protected int $_reconnectDelay = 5;

    /**
     * Stream socket client.
     *
     * @var resource
     */
    protected $_socket = null;

    /**
     * Buffer contents.
     */
    protected ?string $_buffer = null;

    /**
     * When someone types something into a channel, buffer it.
     * array(
     *     'nickname' => string(The nick name of the person who posted.),
     *     'channel'  => string(The channel name.),
     *     'message'  => string(The message the person posted.)
     * );.
     *
     * @note   Used with the processChannelMessages() function.
     */
    protected array $_channelData = [];

    /**
     * Nickname when we log in.
     */
    protected string $_nickName;

    /**
     * Username when we log in.
     */
    protected string $_userName;

    /**
     * "Real" name when we log in.
     */
    protected string $_realName;

    /**
     * Password when we log in.
     */
    protected string $_password;

    /**
     * List of channels and passwords to join.
     */
    protected array $_channels;

    /**
     * Last time we received a ping or sent a ping to the server.
     */
    protected int $_lastPing;

    /**
     * How many times we've tried to reconnect to IRC.
     */
    protected int $_currentRetries = 0;

    /**
     * Turns on or off debugging.
     */
    protected bool $_debug = true;

    /**
     * Are we already logged in to IRC?
     */
    protected bool $_alreadyLoggedIn = false;

    /**
     * Disconnect from IRC.
     */
    public function __destruct()
    {
        $this->quit();
    }

    /**
     * Time before giving up when trying to read or write to the IRC server.
     * The default is fine, it will ping the server if the server does not ping us
     * within this time to keep the connection alive.
     *
     * @param  int  $timeout  Seconds.
     */
    public function setSocketTimeout(int $timeout)
    {
        $this->_socket_timeout = $timeout;
    }

    /**
     * Amount of time to wait before giving up when connecting.
     *
     * @param  int  $timeout  Seconds.
     */
    public function setConnectionTimeout(int $timeout)
    {
        $this->_remote_connection_timeout = $timeout;
    }

    /**
     * Amount of times to retry before giving up when connecting.
     */
    public function setConnectionRetries(int $retries)
    {
        $this->_reconnectRetries = $retries;
    }

    /**
     * Amount of time to wait between failed connects.
     *
     * @param  int  $delay  Seconds.
     */
    public function setReConnectDelay(int $delay)
    {
        $this->_reconnectDelay = $delay;
    }

    /**
     * Connect to a IRC server.
     *
     * @param  string  $hostname  Host name of the IRC server (can be a IP or a name).
     * @param  int  $port  Port number of the IRC server.
     * @param  bool  $tls  Use encryption for the socket transport? (make sure the port is right).
     */
    public function connect(string $hostname, int $port = 6667, bool $tls = false): bool
    {
        $this->_alreadyLoggedIn = false;
        $transport = ($tls === true ? 'tls' : 'tcp');

        $socket_string = $transport.'://'.$hostname.':'.$port;
        if ($socket_string !== $this->_remote_socket_string || ! $this->_connected()) {
            if ($hostname === '') {
                echo 'ERROR: IRC host name must not be empty!'.PHP_EOL;

                return false;
            }

            $this->_remote_host = $hostname;
            $this->_remote_port = $port;
            $this->_remote_transport = $transport;
            $this->_remote_tls = $tls;
            $this->_remote_socket_string = $socket_string;

            // Try to connect until we run out of retries.
            while ($this->_reconnectRetries >= $this->_currentRetries++) {
                $this->_initiateStream();
                if ($this->_connected()) {
                    break;
                }

                // Sleep between retries.
                sleep($this->_reconnectDelay);
            }
        } else {
            $this->_alreadyLoggedIn = true;
        }

        // Set last ping time to now.
        $this->_lastPing = time();
        // Reset retries.
        $this->_currentRetries = $this->_reconnectRetries;

        return $this->_connected();
    }

    /**
     * Log in to a IRC server.
     *
     * @param  string  $nickName  The nick name - visible in the channel.
     * @param  string  $userName  The user name - visible in the host name.
     * @param  string  $realName  The real name - visible in the WhoIs.
     * @param  null  $password  The password  - some servers require a password.
     */
    public function login(string $nickName, string $userName, string $realName, $password = null): bool
    {
        if (! $this->_connected()) {
            echo 'ERROR: You must connect to IRC first!'.PHP_EOL;

            return false;
        }

        if (empty($nickName) || empty($userName) || empty($realName)) {
            echo 'ERROR: nick/user/real name must not be empty!'.PHP_EOL;

            return false;
        }

        $this->_nickName = $nickName;
        $this->_userName = $userName;
        $this->_realName = $realName;
        $this->_password = $password;

        if (empty($password) && ! $this->_writeSocket('PASSWORD '.$password)) {
            return false;
        }

        if (! $this->_writeSocket('NICK '.$nickName)) {
            return false;
        }

        if (! $this->_writeSocket('USER '.$userName.' 0 * :'.$realName)) {
            return false;
        }

        // Loop over socket buffer until we find "001".
        while (true) {
            $this->_readSocket();

            // We got pinged, reply with a pong.
            if (preg_match('/^PING\s*:(.+?)$/', $this->_buffer, $hits)) {
                $this->_pong($hits[1]);
            } elseif (preg_match('/^:(.*?)\s+(\d+).*?(:.+?)?$/', $this->_buffer, $hits)) {
                // We found 001, which means we are logged in.
                if ((int) $hits[2] === 1) {
                    $this->_remote_host_received = $hits[1];
                    break;

                    // We got 464, which means we need to send a password.
                }

                if ((int) $hits[2] === 464) {
                    // Before the lower check, set the password : username:password
                    $tempPass = $userName.':'.$password;

                    // Check if the user has his password in this format: username/server:password
                    if (preg_match('/^.+?\/.+?:.+?$/', $password)) {
                        $tempPass = $password;
                    }

                    if ($password !== null && ! $this->_writeSocket('PASS '.$tempPass)) {
                        return false;
                    }

                    if (isset($hits[3]) && stripos($hits[3], 'invalid password') !== false) {
                        echo 'Invalid password or username for ('.$this->_remote_host.').';

                        return false;
                    }
                }
                // ERROR :Closing Link: kevin123[100.100.100.100] (This server is full.)
            } elseif (preg_match('/^ERROR\s*:/', $this->_buffer)) {
                echo $this->_buffer.PHP_EOL;

                return false;
            }
        }

        return true;
    }

    public function quit(?string $message = null): bool
    {
        if ($this->_connected()) {
            $this->_writeSocket('QUIT'.($message === null ? '' : ' :'.$message));
        }
        $this->_closeStream();

        return $this->_connected();
    }

    /**
     * Read the incoming buffer in a loop.
     */
    public function readIncoming(): void
    {
        while (true) {
            $this->_readSocket();

            // If the server pings us, return it a pong.
            if (preg_match('/^PING\s*:(.+?)$/', $this->_buffer, $hits)) {
                if ($hits[1] === $this->_remote_host_received) {
                    $this->_pong($hits[1]);
                }

                // Check for a channel message.
            } elseif (preg_match(
                '/^:(?P<nickname>.+?)\!.+?\s+PRIVMSG\s+(?P<channel>#.+?)\s+:\s*(?P<message>.+?)\s*$/',
                $this->_stripControlCharacters($this->_buffer),
                $hits
            )
            ) {
                $this->_channelData =
                    [
                        'nickname' => $hits['nickname'],
                        'channel' => $hits['channel'],
                        'message' => $hits['message'],
                    ];

                $this->processChannelMessages();
            }

            // Ping the server if it has not sent us a ping in a while.
            if ((time() - $this->_lastPing) > ($this->_socket_timeout / 2)) {
                $this->_ping($this->_remote_host_received);
            }
        }
    }

    /**
     * Join a channel or multiple channels.
     *
     * @param  array  $channels  Array of channels with their passwords (null if the channel doesn't need a password).
     *                           array( '#exampleChannel' => 'thePassword', '#exampleChan2' => null );
     */
    public function joinChannels(array $channels = []): bool
    {
        $this->_channels = $channels;

        if (! $this->_connected()) {
            echo 'ERROR: You must connect to IRC first!'.PHP_EOL;

            return false;
        }

        if (! empty($channels)) {
            foreach ($channels as $channel => $password) {
                $this->_joinChannel($channel, $password ?? '');
            }
        }

        return false;
    }

    /**
     * Implementation.
     * Extended classes will use this function to parse the messages in the channel using $this->_channelData.
     */
    protected function processChannelMessages() {}

    /**
     * Join a channel.
     */
    protected function _joinChannel(string $channel, string $password): void
    {
        $this->_writeSocket('JOIN '.$channel.(empty($password) ? '' : ' '.$password));
    }

    /**
     * Send PONG to a host.
     */
    protected function _pong(string $host)
    {
        if (! $this->_writeSocket('PONG '.$host)) {
            $this->_reconnect();
        }

        // If we got a ping from the IRC server, set the last ping time to now.
        if ($host === $this->_remote_host_received) {
            $this->_lastPing = time();
        }
    }

    /**
     * Send PING to a host.
     */
    protected function _ping(string $host): void
    {
        $pong = $this->_writeSocket('PING '.$host);

        // Check if there's a connection error.
        if ($pong === false || ((time() - $this->_lastPing) > ($this->_socket_timeout / 2) && ! 0 === strpos($this->_buffer, 'PONG'))) {
            $this->_reconnect();
        }

        // If sent a ping from the IRC server, set the last ping time to now.
        if ($host === $this->_remote_host_received) {
            $this->_lastPing = time();
        }
    }

    /**
     * Attempt to reconnect to IRC.
     */
    protected function _reconnect(): void
    {
        if (! $this->connect($this->_remote_host, $this->_remote_port, $this->_remote_tls)) {
            exit('FATAL: Could not reconnect to ('.$this->_remote_host.') after ('.$this->_reconnectRetries.') tries.'.PHP_EOL);
        }

        if (! $this->_alreadyLoggedIn) {
            if (! $this->login($this->_nickName, $this->_userName, $this->_realName, $this->_password)) {
                exit('FATAL: Could not log in to ('.$this->_remote_host.')!'.PHP_EOL);
            }

            $this->joinChannels($this->_channels);
        }
    }

    /**
     * Read response from the IRC server.
     */
    protected function _readSocket(): void
    {
        $buffer = '';
        do {
            stream_set_timeout($this->_socket, $this->_socket_timeout);
            $buffer .= fgets($this->_socket, 1024);
        } while (! empty($buffer) && ! preg_match('/\v+$/', $buffer));
        $this->_buffer = trim($buffer);

        if ($this->_debug && $this->_buffer !== '') {
            echo 'RECV '.$this->_buffer.PHP_EOL;
        }
    }

    /**
     * Send a command to the IRC server.
     */
    protected function _writeSocket(string $command): bool
    {
        $command .= "\r\n";
        for ($written = 0, $writtenMax = \strlen($command); $written < $writtenMax; $written += $fWrite) {
            stream_set_timeout($this->_socket, $this->_socket_timeout);
            $fWrite = $this->_writeSocketChar(substr($command, $written));

            // http://www.php.net/manual/en/function.fwrite.php#96951 | fwrite can return 0 causing an infinite loop.
            if ($fWrite === false || $fWrite <= 0) {
                // If it failed, try a second time.
                $fWrite = $this->_writeSocketChar(substr($command, $written));
                if ($fWrite === false || $fWrite <= 0) {
                    echo 'ERROR: Could no write to socket! (the IRC server might have closed the connection)'.PHP_EOL;

                    return false;
                }
            }
        }

        if ($this->_debug) {
            echo 'SEND :'.$command;
        }

        return true;
    }

    /**
     * Write a single character to the socket.
     *
     * @param  string  $character(char)  $character  A single character.
     * @return int|bool Number of bytes written or false.
     */
    protected function _writeSocketChar(string $character): bool|int
    {
        return @fwrite($this->_socket, $character);
    }

    /**
     * Initiate stream socket to IRC server.
     */
    protected function _initiateStream(): void
    {
        $this->_closeStream();

        $socket = stream_socket_client(
            $this->_remote_socket_string,
            $error_number,
            $error_string,
            $this->_remote_connection_timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create(Utility::streamSslContextOptions(true))
        );

        if ($socket === false) {
            echo 'ERROR: '.$error_string.' ('.$error_number.')'.PHP_EOL;
        } else {
            $this->_socket = $socket;
        }
    }

    /**
     * Close the socket.
     */
    protected function _closeStream(): void
    {
        if ($this->_socket !== null) {
            $this->_socket = null;
        }
    }

    /**
     * Check if we are connected to the IRC server.
     */
    protected function _connected(): bool
    {
        return \is_resource($this->_socket) && ! feof($this->_socket);
    }

    /**
     * Strips control characters from a IRC message.
     */
    protected function _stripControlCharacters(string $text): string
    {
        return preg_replace(
            [
                '/(\x03(?:\d{1,2}(?:,\d{1,2})?)?)/', // Color code
                '/\x02/', // Bold
                '/\x0F/', // Escaped
                '/\x16/', // Italic
                '/\x1F/', // Underline
                '/\x12/', // Device control 2
            ],
            '',
            $text
        );
    }
}
