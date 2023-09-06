<?php

namespace TomCan\SshTasks;

class SshTask
{
    private SshConnection $sshConnection;
    private string $command;

    /**
     * @param SshConnection $sshConnection
     * @param string $command
     */
    public function __construct(SshConnection $sshConnection, string $command)
    {
        $this->sshConnection = $sshConnection;
        $this->command = $command;
    }

    public function execute(bool $getExitCode = false, string $outputMode = 'log'): array
    {
        if (!$this->sshConnection->isConnected()) {
            $this->sshConnection->connect();
        }

        $mustWrap = false;
        $wrapOut = '';
        $wrapExit = '';
        if ('combined' == $outputMode) {
            $mustWrap = true;
            $wrapOut = ' 2>&1'; // will send stderr to stdout of wrapped command
        }
        if ($getExitCode) {
            $mustWrap = true;
            $wrapExit = ';echo -en "\n$?"'; // will add echo of exit code after command wrapper
        }

        $command = $this->command;
        if ($mustWrap) {
            // wrapping the command in brackets will prevent it from bailing out without executing any following
            //  commands like the exit code echo
            $command = '('.$command.')'.$wrapOut.$wrapExit;
        }

        $stream = ssh2_exec($this->sshConnection->getConnection(), $command);
        if ($stream === false) {
            throw new \Exception('Unable to execute command');
        }
        $errStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        if ($errStream === false) {
            throw new \Exception('Unable to get error stream');
        }

        $output = [];
        if ('combined' == $outputMode || 'split' == $outputMode) {
            $output = ['',''];
        }
        while (!(feof($stream) && feof($errStream))) {
            if (!feof($stream)) {
                $input = fgets($stream);
                if ($input !== false) {
                    switch ($outputMode) {
                        case 'log':
                            $output[] = [time(), 'out', $input];
                            break;
                        case 'combined':
                        case 'split':
                            $output[0] .= $input;
                            break;
                        case 'callback':
                            // TODO: callback function
                    }
                }
            }
            if (!(feof($errStream))) {
                $input = fgets($errStream);
                if ($input !== false) {
                    switch ($outputMode) {
                        case 'log':
                            $output[] = [time(), 'err', $input];
                            break;
                        case 'combined':
                        case 'split':
                            $output[1] .= $input;
                            break;
                        case 'callback':
                            // TODO: callback function
                    }
                }
            }
        }
        fclose($stream);
        fclose($errStream);

        if ($getExitCode) {
            // exit code *should* be in the last 'out' element
            if ('log' == $outputMode) {
                for ($i = count($output) - 1; $i > 0; $i--) {
                    if ('out' == $output[$i][1]) {
                        if (count($output) == $i + 1) {
                            // last element, just change type
                            $output[$i][1] = 'exit';
                            $output[$i][2] = (int) $output[$i][2];
                        } else {
                            // remove and re-insert at the back
                            $output[] = [$output[$i][0], 'exit', (int) $output[$i][2]];
                            unset($output[$i]);
                        }
                        break;
                    }
                }
            } else {
                // get last line of output and store in $output[2]
                $rp = strrpos($output[0], "\n");
                if ($rp !== false) {
                    // get exit code
                    $output[2] = (int) substr($output[0], $rp + 1);
                    // strip from output
                    $output[0] = substr($output[0], 0, $rp);
                }
            }
        }

        return $output;
    }
}
