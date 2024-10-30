<?php
/**
 * Created by claudio on 2018-12-20
 */

require_once __DIR__ . '/../../thirdparty/autoload.php';

use Catenis\WP\Notification\NotificationCtrl;

$LOGGING = true;
$LOG_LEVEL = 'INFO';
$MAX_LOG_SIZE = 10 * 1024 * 1024;  // 10 MB

if ($LOGGING) {
    // Open log file
    if (!file_exists(__DIR__ . '/../../log')) {
        if (!mkdir(__DIR__ . '/../../log', 0700)) {
            // Make sure that it has not failed because directory already exists (errno = 17 (EEXIST))
            if (posix_get_last_error() != 17) {
                exit(-3);
            }
        }
    }

    $logFilePath = __DIR__ . '/../../log/catenis_notify_proc.log';

    if (file_exists($logFilePath)) {
        if (stat($logFilePath)['size'] > $MAX_LOG_SIZE) {
            // Log file exceeded its maximum size. Move it to backup log
            $backupLogFilePath = __DIR__ . '/../../log/catenis_notify_proc.bak.log';

            if (file_exists($backupLogFilePath)) {
                // If backup log file already exists, delete it
                unlink($backupLogFilePath);
            }

            rename($logFilePath, $backupLogFilePath);
        }
    }

    $LOG = fopen($logFilePath, 'a');
}

NotificationCtrl::logInfo('====================================================');
NotificationCtrl::logInfo('Catenis Notification Process started');

if ($argc <= 1) {
    NotificationCtrl::logError('Missing required parameter (client UID)');
    exit(-1);
}

// Set up handler for PHP errors
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        // This error should not be reported, so let it be processed by the standard PHP error handler
        return false;
    }

    // Fatal error; throw an Error exception
    if (in_array($errno, array(E_USER_ERROR, E_RECOVERABLE_ERROR))) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    NotificationCtrl::logError("PHP error ($errno): $errstr\n  at file: $errfile, line: $errline");
});

$loop;

try {
    $notifyCtrl = new NotificationCtrl($argv[1], $loop);
} catch (Exception $ex) {
    NotificationCtrl::logError('Error instantiating notification control object: ' . $ex->getMessage());
    exit(-2);
}

$EXIT_CODE = 0;

try {
    $loop->run();
    NotificationCtrl::logTrace('Event loop has finished run');
} catch (Exception $ex) {
    if (isset($notifyCtrl)) {
        $notifyCtrl->terminate('Exception while running process: ' . $ex->getMessage(), -10);
    }
}

if ($LOGGING) {
    fclose($LOG);
}
exit($EXIT_CODE);
