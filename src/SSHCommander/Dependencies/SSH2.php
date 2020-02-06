<?php /** @noinspection PhpUndefinedVariableInspection */
/** @noinspection PhpParamsInspection */
/** @noinspection PhpMissingBreakStatementInspection */
/** @noinspection PhpInconsistentReturnPointsInspection */
/** @noinspection PhpUndefinedConstantInspection */

namespace Neskodi\SSHCommander\Dependencies;

use phpseclib\Net\SSH2 as PhpSecLibSSH2;

class SSH2 extends PhpSecLibSSH2
{
    /**
     * @var null|callable
     */
    protected $timeoutFunction = null;

    protected $readInterval = [
        'sec'  => 0,
        'usec' => 500000,
    ];

    /**
     * Set both the readInterval and the timeout function in one call.
     *
     * @param float|null    $readInterval
     * @param callable|null $timeoutFunction
     */
    public function configureTimeouts(?float $readInterval = null, ?callable $timeoutFunction = null): void
    {
        if (!is_null($readInterval)) {
            $this->setReadInterval($readInterval);
        }

        $this->setTimeoutFunction($timeoutFunction);
    }

    /**
     * Set the function that will be run after each iteration of stream_select
     * to determine if we should break out of the waiting cycle.
     *
     * @param callable|null $function
     */
    public function setTimeoutFunction(?callable $function = null): void
    {
        $this->timeoutFunction = $function;
    }

    /**
     * Set the interval that each iteration of stream_select will occupy.
     *
     * @param float $readInterval
     */
    public function setReadInterval(float $readInterval): void
    {
        $sec  = floor($readInterval);
        $usec = ($readInterval - $sec) * 1000000;

        $this->readInterval = compact($sec, $usec);
    }

    /**
     * This function will be run on each iteration of stream_select and return
     * true to tell that we need to break from the cycle.
     *
     * First, see if the caller has passed a timeout function. If so, use it
     * to determine if reading from the stream should stop. If not, use the
     * standard timeout value.
     *
     * @return bool
     */
    protected function shouldBreak(): bool
    {
        if (is_callable($this->timeoutFunction)) {
            return (bool)call_user_func($this->timeoutFunction);
        }

        if (!$this->timeout) {
            // never break
            return false;
        }

        return $this->curTimeout < 0;
    }

    function _get_channel_packet($client_channel, $skip_extended = false)
    {
        if (!empty($this->channel_buffers[$client_channel])) {
            return array_shift($this->channel_buffers[$client_channel]);
        }

        while (true) {
            if ($this->binary_packet_buffer !== false) {
                $response                   = $this->binary_packet_buffer;
                $this->binary_packet_buffer = false;
            } else {
                $read  = [$this->fsock];
                $write = $except = null;

                if (!$this->timeout) {
                    // no timeout whatsoever
                    @stream_select($read, $write, $except, null);
                } else {
                    if ($this->curTimeout < 0) {
                        $this->is_timeout = true;

                        return true;
                    }

                    $start = microtime(true);

                    $sec  = $this->readInterval['sec'];
                    $usec = $this->readInterval['usec'];

                    do {
                        // run stream_select in cycle, on each iteration delegate
                        // the timeout check to the caller.
                        $result           = @stream_select($read, $write, $except, $sec, $usec);
                        $elapsed          = microtime(true) - $start;
                        $this->curTimeout -= $elapsed;
                    } while (!$result && !$this->shouldBreak());

                    if (!$result && !count($read)) {
                        $this->is_timeout = true;
                        if ($client_channel == self::CHANNEL_EXEC && !$this->request_pty) {
                            $this->_close_channel($client_channel);
                        }

                        return true;
                    }
                }

                $response = $this->_get_binary_packet(true);
                if ($response === false) {
                    $this->bitmap = 0;
                    user_error('Connection closed by server');

                    return false;
                }
            }

            if ($client_channel == -1 && $response === true) {
                return true;
            }
            if (!strlen($response)) {
                return false;
            }

            extract(unpack('Ctype', $this->_string_shift($response, 1)));

            if (strlen($response) < 4) {
                return false;
            }
            if ($type == NET_SSH2_MSG_CHANNEL_OPEN) {
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
            } else {
                extract(unpack('Nchannel', $this->_string_shift($response, 4)));
            }

            // will not be setup yet on incoming channel open request
            if (isset($channel) && isset($this->channel_status[$channel]) && isset($this->window_size_server_to_client[$channel])) {
                $this->window_size_server_to_client[$channel] -= strlen($response);

                // resize the window, if appropriate
                if ($this->window_size_server_to_client[$channel] < 0) {
                    $packet = pack('CNN', NET_SSH2_MSG_CHANNEL_WINDOW_ADJUST, $this->server_channels[$channel],
                        $this->window_size);
                    if (!$this->_send_binary_packet($packet)) {
                        return false;
                    }
                    $this->window_size_server_to_client[$channel] += $this->window_size;
                }

                switch ($type) {
                    case NET_SSH2_MSG_CHANNEL_EXTENDED_DATA:
                        // currently, there's only one possible value for $data_type_code: NET_SSH2_EXTENDED_DATA_STDERR
                        if (strlen($response) < 8) {
                            return false;
                        }
                        extract(unpack('Ndata_type_code/Nlength', $this->_string_shift($response, 8)));
                        $data              = $this->_string_shift($response, $length);
                        $this->stdErrorLog .= $data;
                        if ($skip_extended || $this->quiet_mode) {
                            continue 2;
                        }
                        if ($client_channel == $channel && $this->channel_status[$channel] == NET_SSH2_MSG_CHANNEL_DATA) {
                            return $data;
                        }
                        if (!isset($this->channel_buffers[$channel])) {
                            $this->channel_buffers[$channel] = [];
                        }
                        $this->channel_buffers[$channel][] = $data;

                        continue 2;
                    case NET_SSH2_MSG_CHANNEL_REQUEST:
                        if ($this->channel_status[$channel] == NET_SSH2_MSG_CHANNEL_CLOSE) {
                            continue 2;
                        }
                        if (strlen($response) < 4) {
                            return false;
                        }
                        extract(unpack('Nlength', $this->_string_shift($response, 4)));
                        $value = $this->_string_shift($response, $length);
                        switch ($value) {
                            case 'exit-signal':
                                $this->_string_shift($response, 1);
                                if (strlen($response) < 4) {
                                    return false;
                                }
                                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                                $this->errors[] = 'SSH_MSG_CHANNEL_REQUEST (exit-signal): ' . $this->_string_shift($response,
                                        $length);
                                $this->_string_shift($response, 1);
                                if (strlen($response) < 4) {
                                    return false;
                                }
                                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                                if ($length) {
                                    $this->errors[count($this->errors)] .= "\r\n" . $this->_string_shift($response,
                                            $length);
                                }

                                $this->_send_binary_packet(pack('CN', NET_SSH2_MSG_CHANNEL_EOF,
                                    $this->server_channels[$client_channel]));
                                $this->_send_binary_packet(pack('CN', NET_SSH2_MSG_CHANNEL_CLOSE,
                                    $this->server_channels[$channel]));

                                $this->channel_status[$channel] = NET_SSH2_MSG_CHANNEL_EOF;

                                continue 3;
                            case 'exit-status':
                                if (strlen($response) < 5) {
                                    return false;
                                }
                                extract(unpack('Cfalse/Nexit_status', $this->_string_shift($response, 5)));
                                $this->exit_status = $exit_status;

                                // "The client MAY ignore these messages."
                                // -- http://tools.ietf.org/html/rfc4254#section-6.10

                                continue 3;
                            default:
                                // "Some systems may not implement signals, in which case they SHOULD ignore this message."
                                //  -- http://tools.ietf.org/html/rfc4254#section-6.9
                                continue 3;
                        }
                }

                switch ($this->channel_status[$channel]) {
                    case NET_SSH2_MSG_CHANNEL_OPEN:
                        switch ($type) {
                            case NET_SSH2_MSG_CHANNEL_OPEN_CONFIRMATION:
                                if (strlen($response) < 4) {
                                    return false;
                                }
                                extract(unpack('Nserver_channel', $this->_string_shift($response, 4)));
                                $this->server_channels[$channel] = $server_channel;
                                if (strlen($response) < 4) {
                                    return false;
                                }
                                extract(unpack('Nwindow_size', $this->_string_shift($response, 4)));
                                if ($window_size < 0) {
                                    $window_size &= 0x7FFFFFFF;
                                    $window_size += 0x80000000;
                                }
                                $this->window_size_client_to_server[$channel] = $window_size;
                                if (strlen($response) < 4) {
                                    return false;
                                }
                                $temp                                         = unpack('Npacket_size_client_to_server',
                                    $this->_string_shift($response, 4));
                                $this->packet_size_client_to_server[$channel] = $temp['packet_size_client_to_server'];
                                $result                                       = $client_channel == $channel ? true : $this->_get_channel_packet($client_channel,
                                    $skip_extended);
                                $this->_on_channel_open();

                                return $result;
                            //case NET_SSH2_MSG_CHANNEL_OPEN_FAILURE:
                            default:
                                user_error('Unable to open channel');

                                return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
                        }
                        break;
                    case NET_SSH2_MSG_CHANNEL_REQUEST:
                        switch ($type) {
                            case NET_SSH2_MSG_CHANNEL_SUCCESS:
                                return true;
                            case NET_SSH2_MSG_CHANNEL_FAILURE:
                                return false;
                            default:
                                user_error('Unable to fulfill channel request');

                                return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
                        }
                    case NET_SSH2_MSG_CHANNEL_CLOSE:
                        return $type == NET_SSH2_MSG_CHANNEL_CLOSE ? true : $this->_get_channel_packet($client_channel,
                            $skip_extended);
                }
            }

            // ie. $this->channel_status[$channel] == NET_SSH2_MSG_CHANNEL_DATA

            switch ($type) {
                case NET_SSH2_MSG_CHANNEL_DATA:
                    /*
                    if ($channel == self::CHANNEL_EXEC) {
                        // SCP requires null packets, such as this, be sent.  further, in the case of the ssh.com SSH server
                        // this actually seems to make things twice as fast.  more to the point, the message right after
                        // SSH_MSG_CHANNEL_DATA (usually SSH_MSG_IGNORE) won't block for as long as it would have otherwise.
                        // in OpenSSH it slows things down but only by a couple thousandths of a second.
                        $this->_send_channel_packet($channel, chr(0));
                    }
                    */
                    if (strlen($response) < 4) {
                        return false;
                    }
                    extract(unpack('Nlength', $this->_string_shift($response, 4)));
                    $data = $this->_string_shift($response, $length);

                    if ($channel == self::CHANNEL_AGENT_FORWARD) {
                        $agent_response = $this->agent->_forward_data($data);
                        if (!is_bool($agent_response)) {
                            $this->_send_channel_packet($channel, $agent_response);
                        }
                        break;
                    }

                    if ($client_channel == $channel) {
                        return $data;
                    }
                    if (!isset($this->channel_buffers[$channel])) {
                        $this->channel_buffers[$channel] = [];
                    }
                    $this->channel_buffers[$channel][] = $data;
                    break;
                case NET_SSH2_MSG_CHANNEL_CLOSE:
                    $this->curTimeout = 0;

                    if ($this->bitmap & self::MASK_SHELL) {
                        $this->bitmap &= ~self::MASK_SHELL;
                    }
                    if ($this->channel_status[$channel] != NET_SSH2_MSG_CHANNEL_EOF) {
                        $this->_send_binary_packet(pack('CN', NET_SSH2_MSG_CHANNEL_CLOSE,
                            $this->server_channels[$channel]));
                    }

                    $this->channel_status[$channel] = NET_SSH2_MSG_CHANNEL_CLOSE;
                    if ($client_channel == $channel) {
                        return true;
                    }
                case NET_SSH2_MSG_CHANNEL_EOF:
                    break;
                default:
                    user_error('Error reading channel data');

                    return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
            }
        }
    }
}
