<?php /** @noinspection PhpUndefinedVariableInspection */
/** @noinspection PhpParamsInspection */
/** @noinspection PhpMissingBreakStatementInspection */
/** @noinspection PhpInconsistentReturnPointsInspection */
/** @noinspection PhpUndefinedConstantInspection */

namespace Neskodi\SSHCommander\VendorOverrides\phpseclib\Net;

use Neskodi\SSHCommander\Traits\HasConnection;
use Neskodi\SSHCommander\Traits\Loggable;
use phpseclib\Net\SSH2 as PhpSecLibSSH2;

class SSH2 extends PhpSecLibSSH2
{
    use Loggable, HasConnection;

    /**
     * Introduce our own variable for last response time, since the inherited
     * one doesn't work for our purposes.
     *
     * @var float
     */
    protected $lastResponseTime;

    /**
     * Read cycle will repeat with this frequency (in seconds).
     *
     * @var float
     */
    protected $readCycleInterval = 0.5;

    /**
     * Set the read cycle interval in seconds (fraction values are possible by
     * passing in a float). Note that php manual does not recommended to run
     * stream_select with frequencies higher that 0.2 sec. By default, we use
     * 0.5 seconds which works great for our purpose.
     *
     * @param float $readCycleInterval
     *
     * @noinspection PhpUnused
     */
    public function setReadCycleInterval(float $readCycleInterval): void
    {
        $this->readCycleInterval = $readCycleInterval;
    }

    /**
     * Instead of just listening to stream until timeout, we break it down into
     * small iterations, and on each iteration we ask the cycle hooks whether
     * we should return.
     *
     * @param array $readStreams
     *
     * @return int
     */
    protected function streamSelectCycle(array &$readStreams)
    {
        $sec   = floor($this->readCycleInterval);
        $usec  = ($this->readCycleInterval - $sec) * 1000000;
        $write = $except = null;

        do {
            // stream_select modifies $read by reference; hence we need to reset
            // it on each iteration.
            $read        = $readStreams;
            $result      = @stream_select($read, $write, $except, $sec, $usec);
            $shouldBreak = $this->getConnection()->runReadCycleHooks();
        } while (0 === $result && !$shouldBreak);

        // caller should be notified in any change in read streams
        $readStreams = $read;

        return $result;
    }

    /**
     * Get the last response time
     *
     * @return float|null
     */
    public function getLastResponseTime(): ?float
    {
        return $this->lastResponseTime;
    }

    /**
     * Slightly modified version of phpseclib's _get_channel_packet. We replace
     * the blocking read operation with a cycle where we are able to inject our
     * hooks into each iteration.
     *
     * Returns the data as a string if it's available and false if not.
     *
     * @param      $client_channel
     * @param bool $skip_extended
     *
     * @return mixed
     * @access private
     */
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
                $read = [$this->fsock];

                if (!$this->timeout) {
                    $this->streamSelectCycle($read);
                } else {
                    if ($this->curTimeout < 0) {
                        $this->is_timeout = true;

                        return true;
                    }

                    $start = microtime(true);

                    $result = $this->streamSelectCycle($read);

                    if (!$result && !count($read)) {
                        $this->is_timeout = true;
                        if ($client_channel == self::CHANNEL_EXEC && !$this->request_pty) {
                            // $this->getConnection()->debug('closing exec channel...');
                            $this->_close_channel($client_channel);
                        }

                        return true;
                    }
                    $elapsed          = microtime(true) - $start;
                    $this->curTimeout -= $elapsed;
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
                        /*
                        if ($client_channel == self::CHANNEL_EXEC) {
                            $this->_send_channel_packet($client_channel, chr(0));
                        }
                        */
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
                            // $this->getConnection()->debug('_get_channel_packet returns (1): ' . $data);
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
                                $temp = unpack(
                                    'Npacket_size_client_to_server',
                                    $this->_string_shift($response, 4)
                                );

                                $this->packet_size_client_to_server[$channel] = $temp['packet_size_client_to_server'];

                                $result = $client_channel == $channel
                                    ? true
                                    : $this->_get_channel_packet(
                                        $client_channel,
                                        $skip_extended
                                    );

                                $this->_on_channel_open();

                                // $this->getConnection()->debug('_get_channel_packet returns (2): ' . $result);
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
                        return $type == NET_SSH2_MSG_CHANNEL_CLOSE
                            ? true
                            : $this->_get_channel_packet(
                                $client_channel,
                                $skip_extended
                            );
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
                        // $this->getConnection()->debug('_get_channel_packet returns (3): ' . $data);
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
