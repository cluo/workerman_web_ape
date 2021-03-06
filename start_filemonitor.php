<?php
if(!defined("RUN_DIR")){
    define('RUN_DIR', __DIR__ . "/");
}
require_once '_lib/ape/helper.php';
require_once '_lib/ape/constant.php';
require_once '_lib/Autoloader.php';
require_once '_lib/ape/http/Http.php';

use Workerman\Worker;
use Workerman\Lib\Timer;

// 日志
Worker::$logFile = __DIR__ . "/log/" . APE['config'] ["logFile"];
// 访问日志
Worker::$stdoutFile = __DIR__ . "/log/" . APE['config'] ["stdoutFile"];

// watch Applications catalogue
$monitor_dir = realpath ( RUN_DIR );
$last_time_arr = array();
// worker
$worker = new Worker ();
$worker->name = 'FileMonitor';
$worker->reloadable = false;
$last_mtime = time ();

$worker->onWorkerStart = function () {
	global $monitor_dir;
	// watch files only in daemon mode
	if (! Worker::$daemonize) {
		// chek mtime of files per second
		Timer::add ( 1, 'check_files_change', array (
				$monitor_dir
		) );
	}
};

// check files func
function check_files_change($monitor_dir) {
	global $last_time_arr;
	// recursive traversal directory
	$dir_iterator = new RecursiveDirectoryIterator ( $monitor_dir );
	$iterator = new RecursiveIteratorIterator ( $dir_iterator );
	foreach ( $iterator as $file ) {
		// only check php files
		if (pathinfo ( $file, PATHINFO_EXTENSION ) != 'php') {
			continue;
		}
		if(!array_key_exists((string)$file,$last_time_arr)){
			$last_time_arr[(string)$file] = $file->getMTime ();
		}
		if ($last_time_arr[(string)$file] < $file->getMTime ()) {
			echo $file . " update and reload\n";
			// send SIGUSR1 signal to master process for reload
			posix_kill ( posix_getppid (), SIGUSR1 );
			$last_time_arr[(string)$file] = $file->getMTime ();
			break;
		}
	}
}

if(APE["WORKERMAN"] ==  "Workerman_win"){
    Worker::runAll();
}
