<?php
/**
 * Created by claudio on 2018-12-21
 */

namespace Catenis\WP\Notification;

use \Exception;

class CommPipe
{
    const SEND_COMM_MODE = 0x1;
    const RECEIVE_COMM_MODE = 0x2;
    const SEND_COMM_CTRL_MODE = 0x4;
    const RECEIVE_COMM_CTRL_MODE = 0x8;
    
    private $clientUID;
    private $isParent;
    private $commMode;
    private $inputFifoPath;
    private $outputFifoPath;
    private $inputCtrlFifoPath;
    private $outputCtrlFifoPath;
    private $inputFifo;
    private $outputFifo;
    private $inputCtrlFifo;
    private $outputCtrlFifo;
    private $inputFifoDidNotExist;
    private $outputFifoDidNotExist;
    private $inputCtrlFifoDidNotExist;
    private $outputCtrlFifoDidNotExist;

    /**
     * CommPipe constructor.
     * @param string $clientUID - Unique ID identifying a specific instance of a WordPress page using the
     *                             Catenis API client plugin
     * @param bool $isParent - Indicates whether this is being called from the plugin's process
     * @param int $commMode - Type of communication required
     * @param bool $createPipes - Indicates whether communication pipes should be created if they do not exist yet
     * @throws Exception
     */
    public function __construct(
        $clientUID,
        $isParent = true,
        $commMode = self::SEND_COMM_MODE | self::RECEIVE_COMM_MODE,
        $createPipes = false
    ) {
        $this->clientUID = $clientUID;
        $this->commMode = $commMode;

        // Make sure that directory used for interprocess communication exists
        $ipcDir = __DIR__ . '/../../io';
        
        if (!file_exists($ipcDir)) {
            if (!mkdir($ipcDir, 0700)) {
                // Make sure that it has not failed because directory already exists (errno = 17 (EEXIST))
                $lastError = posix_get_last_error();

                if ($lastError != 17) {
                    throw new Exception(sprintf(
                        'Error creating interprocess communication directory (%s): %s',
                        $ipcDir,
                        posix_strerror($lastError)
                    ));
                }
            }
        }
    
        $baseFifoPathName = $ipcDir . '/' . $clientUID;

        if ($isParent) {
            $this->inputFifoPath = $baseFifoPathName . '.down';
            $this->outputFifoPath = $baseFifoPathName . '.up';
            $this->inputCtrlFifoPath = $baseFifoPathName . '_ctl.down';
            $this->outputCtrlFifoPath = $baseFifoPathName . '_ctl.up';
        } else {
            $this->inputFifoPath = $baseFifoPathName . '.up';
            $this->outputFifoPath = $baseFifoPathName . '.down';
            $this->inputCtrlFifoPath = $baseFifoPathName . '_ctl.up';
            $this->outputCtrlFifoPath = $baseFifoPathName . '_ctl.down';
        }

        // Check if fifos exist and create them as required
        $this->inputFifoDidNotExist = true;

        if (!file_exists($this->inputFifoPath)) {
            if ($createPipes) {
                if (!posix_mkfifo($this->inputFifoPath, 0600)) {
                    // Make sure that it has not failed because file already exists (errno = 17 (EEXIST))
                    $lastError = posix_get_last_error();

                    if ($lastError != 17) {
                        throw new Exception(sprintf(
                            'Error creating communication input fifo (%s): %s',
                            $this->inputFifoPath,
                            posix_strerror($lastError)
                        ));
                    } else {
                        $this->inputFifoDidNotExist = false;
                    }
                }
            }
        } else {
            $this->inputFifoDidNotExist = false;
        }

        $this->outputFifoDidNotExist = true;

        if (!file_exists($this->outputFifoPath)) {
            if ($createPipes) {
                if (!posix_mkfifo($this->outputFifoPath, 0600)) {
                    // Make sure that it has not failed because file already exists (errno = 17 (EEXIST))
                    $lastError = posix_get_last_error();

                    if ($lastError != 17) {
                        // Delete other fifo to be consistent
                        @unlink($this->inputFifoPath);
                        throw new Exception(sprintf(
                            'Error creating communication output fifo (%s): %s',
                            $this->outputFifoPath,
                            posix_strerror($lastError)
                        ));
                    } else {
                        $this->outputFifoDidNotExist = false;
                    }
                }
            }
        } else {
            $this->outputFifoDidNotExist = false;
        }

        $this->inputCtrlFifoDidNotExist = true;

        if (!file_exists($this->inputCtrlFifoPath)) {
            if ($createPipes) {
                if (!posix_mkfifo($this->inputCtrlFifoPath, 0600)) {
                    // Make sure that it has not failed because file already exists (errno = 17 (EEXIST))
                    $lastError = posix_get_last_error();

                    if ($lastError != 17) {
                        // Delete other fifos to be consistent
                        @unlink($this->inputFifoPath);
                        @unlink($this->outputFifoPath);
                        throw new Exception(sprintf(
                            'Error creating communication control input fifo (%s): %s',
                            $this->inputCtrlFifoPath,
                            posix_strerror($lastError)
                        ));
                    } else {
                        $this->inputCtrlFifoDidNotExist = false;
                    }
                }
            }
        } else {
            $this->inputCtrlFifoDidNotExist = false;
        }

        $this->outputCtrlFifoDidNotExist = true;

        if (!file_exists($this->outputCtrlFifoPath)) {
            if ($createPipes) {
                if (!posix_mkfifo($this->outputCtrlFifoPath, 0600)) {
                    // Make sure that it has not failed because file already exists (errno = 17 (EEXIST))
                    $lastError = posix_get_last_error();

                    if ($lastError != 17) {
                        // Delete other fifos to be consistent
                        @unlink($this->inputFifoPath);
                        @unlink($this->outputFifoPath);
                        @unlink($this->inputCtrlFifoPath);
                        throw new Exception(sprintf(
                            'Error creating communication control output fifo (%s): %s',
                            $this->outputCtrlFifoPath,
                            posix_strerror($lastError)
                        ));
                    } else {
                        $this->outputCtrlFifoDidNotExist = false;
                    }
                }
            }
        } else {
            $this->outputCtrlFifoDidNotExist = false;
        }

        // Open fifos as required
        if (($this->commMode & self::RECEIVE_COMM_MODE) && file_exists($this->inputFifoPath)) {
            $this->inputFifo = fopen($this->inputFifoPath, 'r+');

            if ($this->inputFifo === false) {
                if ($createPipes && !$this->werePipesAlreadyCreated()) {
                    // Delete pipes to be consistent
                    $this->delete();
                }

                throw new Exception('Error opening communication input fifo: ' . error_get_last()['message']);
            }

            stream_set_blocking($this->inputFifo, false);
        }

        if (($this->commMode & self::SEND_COMM_MODE) && file_exists($this->outputFifoPath)) {
            $this->outputFifo = fopen($this->outputFifoPath, 'w+');

            if ($this->outputFifo === false) {
                if ($createPipes && !$this->werePipesAlreadyCreated()) {
                    // Delete pipes to be consistent
                    $this->delete();
                }

                throw new Exception('Error opening communication output fifo: ' . error_get_last()['message']);
            }

            stream_set_blocking($this->outputFifo, false);
        }

        if (($this->commMode & self::RECEIVE_COMM_CTRL_MODE) && file_exists($this->inputCtrlFifoPath)) {
            $this->inputCtrlFifo = fopen($this->inputCtrlFifoPath, 'r+');

            if ($this->inputCtrlFifo === false) {
                if ($createPipes && !$this->werePipesAlreadyCreated()) {
                    // Delete pipes to be consistent
                    $this->delete();
                }

                throw new Exception('Error opening communication control input fifo: ' . error_get_last()['message']);
            }

            stream_set_blocking($this->inputCtrlFifo, false);
        }

        if (($this->commMode & self::SEND_COMM_CTRL_MODE) && file_exists($this->outputCtrlFifoPath)) {
            $this->outputCtrlFifo = fopen($this->outputCtrlFifoPath, 'w+');

            if ($this->outputCtrlFifo === false) {
                if ($createPipes && !$this->werePipesAlreadyCreated()) {
                    // Delete pipes to be consistent
                    $this->delete();
                }

                throw new Exception('Error opening communication control output fifo: ' . error_get_last()['message']);
            }

            stream_set_blocking($this->outputCtrlFifo, false);
        }
    }
    
    public function __destruct()
    {
        $this->close();
    }

    public function getInputPipe()
    {
        return $this->inputFifo;
    }

    public function getInputCtrlPipe()
    {
        return $this->inputCtrlFifo;
    }

    public function pipesExist()
    {
        return file_exists($this->inputFifoPath) && file_exists($this->outputFifoPath)
            && file_exists($this->inputCtrlFifoPath) && file_exists($this->outputCtrlFifoPath);
    }

    public function werePipesAlreadyCreated()
    {
        return !$this->inputFifoDidNotExist || !$this->outputFifoDidNotExist || !$this->inputCtrlFifoDidNotExist
            || !$this->outputCtrlFifoDidNotExist;
    }

    public function close()
    {
        if (is_resource($this->inputFifo)) {
            @fclose($this->inputFifo);
            $this->inputFifo = null;
        }

        if (is_resource($this->outputFifo)) {
            @fclose($this->outputFifo);
            $this->outputFifo = null;
        }

        if (is_resource($this->inputCtrlFifo)) {
            @fclose($this->inputCtrlFifo);
            $this->inputCtrlFifo = null;
        }

        if (is_resource($this->outputCtrlFifo)) {
            @fclose($this->outputCtrlFifo);
            $this->outputCtrlFifo = null;
        }
    }

    public function delete()
    {
        $this->close();

        if (file_exists($this->inputFifoPath)) {
            @unlink($this->inputFifoPath);
        }

        if (file_exists($this->outputFifoPath)) {
            @unlink($this->outputFifoPath);
        }

        if (file_exists($this->inputCtrlFifoPath)) {
            @unlink($this->inputCtrlFifoPath);
        }

        if (file_exists($this->outputCtrlFifoPath)) {
            @unlink($this->outputCtrlFifoPath);
        }
    }

    /**
     * @param int $timeoutSec - Seconds component of timeout for waiting on data to receive
     * @param int $timeoutUSec - Microseconds component of timeout for waiting on data to receive
     * @return string - The data read
     * @throws Exception
     */
    public function receive($timeoutSec = 0, $timeoutUSec = 0)
    {
        if (!$this->inputFifo) {
            throw new Exception('Cannot receive data; input pipe not open');
        }

        $read = [$this->inputFifo];
        $write = null;
        $except = null;

        $result = stream_select($read, $write, $except, $timeoutSec, $timeoutUSec);

        if ($result === false) {
            throw new Exception('Error waiting on input pipe to receive data: ' . error_get_last()['message']);
        } else {
            $data = '';

            if ($result > 0) {
                // Data available. Read it
                do {
                    $dataRead = fread($this->inputFifo, 1024);
    
                    if ($dataRead === false) {
                        throw new Exception('Error reading data from pipe: ' . error_get_last()['message']);
                    }
    
                    $data .= $dataRead;
                } while (strlen($dataRead) > 0);
            }
        }
        
        return $data;
    }

    /**
     * @param string $data - Data to send
     * @param int $timeoutSec - Seconds component of timeout for waiting for pipe to be ready to send data
     * @param int $timeoutUSec - Microseconds component of timeout for waiting for pipe to be ready to send data
     * @throws Exception
     */
    public function send($data, $timeoutSec = 15, $timeoutUSec = 0)
    {
        if (!$this->outputFifo) {
            throw new Exception('Cannot send data; output pipe not open');
        }

        $dataLength = strlen($data);
        $bytesToSend = $dataLength;

        do {
            $read = null;
            $write = [$this->outputFifo];
            $except = null;

            $result = stream_select($read, $write, $except, $timeoutSec, $timeoutUSec);

            if ($result === false) {
                throw new Exception('Error waiting on output pipe to send data: ' . error_get_last()['message']);
            } elseif ($result > 0) {
                // Pipe ready. Send data
                $bytesSent = fwrite($this->outputFifo, $data);

                if ($bytesSent === false) {
                    throw new Exception('Error writing data to pipe: ' . error_get_last()['message']);
                }

                $bytesToSend -= $bytesSent;
            } else {
                // Pipe did not become available. Data not sent
                throw new Exception('Pipe not available; data not sent');
            }
        } while ($bytesToSend > 0);
    }

    /**
     * @param int $timeoutSec - Seconds component of timeout for waiting on data to receive
     * @param int $timeoutUSec - Microseconds component of timeout for waiting on data to receive
     * @return string - The data read
     * @throws Exception
     */
    public function receiveControl($timeoutSec = 0, $timeoutUSec = 0)
    {
        if (!$this->inputCtrlFifo) {
            throw new Exception('Cannot receive data; input control pipe not open');
        }

        $read = [$this->inputCtrlFifo];
        $write = null;
        $except = null;

        $result = stream_select($read, $write, $except, $timeoutSec, $timeoutUSec);

        if ($result === false) {
            throw new Exception('Error waiting on input control pipe to receive data: ' . error_get_last()['message']);
        } else {
            $data = '';

            if ($result > 0) {
                // Data available. Read it
                do {
                    $dataRead = fread($this->inputCtrlFifo, 1024);
    
                    if ($dataRead === false) {
                        throw new Exception('Error reading data from control pipe: ' . error_get_last()['message']);
                    }
    
                    $data .= $dataRead;
                } while (strlen($dataRead) > 0);
            }
        }
        
        return $data;
    }

    /**
     * @param string $data - Data to send
     * @param int $timeoutSec - Seconds component of timeout for waiting for pipe to be ready to send data
     * @param int $timeoutUSec - Microseconds component of timeout for waiting for pipe to be ready to send data
     * @throws Exception
     */
    public function sendControl($data, $timeoutSec = 15, $timeoutUSec = 0)
    {
        if (!$this->outputCtrlFifo) {
            throw new Exception('Cannot send data; output control pipe not open');
        }

        $dataLength = strlen($data);
        $bytesToSend = $dataLength;

        do {
            $read = null;
            $write = [$this->outputCtrlFifo];
            $except = null;

            $result = stream_select($read, $write, $except, $timeoutSec, $timeoutUSec);

            if ($result === false) {
                throw new Exception('Error waiting on output control pipe to send data: '
                    . error_get_last()['message']);
            } elseif ($result > 0) {
                // Pipe ready. Send data
                $bytesSent = fwrite($this->outputCtrlFifo, $data);

                if ($bytesSent === false) {
                    throw new Exception('Error writing data to control pipe: ' . error_get_last()['message']);
                }

                $bytesToSend -= $bytesSent;
            } else {
                // Pipe did not become available. Data not sent
                throw new Exception('Control pipe not available; data not sent');
            }
        } while ($bytesToSend > 0);
    }
}
