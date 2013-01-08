<?php
declare(ticks = 1);

/**
 * Class that wraps PHP POSIX and PCNTL functions to easily implement
 * process forking and pseudo-threading
 *
 * @author Don Bauer <lordgnu@me.com>
 * @link https://github.com/lordgnu/PowerSpawn
 * @version 1.0
 */
class PowerSpawn 
{
	private $myChildren;
	private $parentPID;
	private $shutdownCallback = null;
	private $killCallback = null;

	public	$maxChildren	=	10; // Max number of children allowed to Spawn
	public	$timeLimit		=	0; // Time limit in seconds (0 to disable)
	public	$sleepCount		=	1; // Number of seconds to sleep on Tick()

	public	$childData; // Variable for storage of data to be passed to the next spawned child
	public	$complete;

	public function __construct() {
		if (function_exists('pcntl_fork') && function_exists('posix_getpid')) {
			// Everything is good
			$this->parentPID = $this->myPID();
			$this->myChildren = array();
			$this->complete = false;

			// Install the signal handler
			pcntl_signal(SIGCHLD, array($this, 'sigHandler'));
		} else {
			die("You must have POSIX and PCNTL functions to use PowerSpawn\n");
		}
	}

	public function __destruct() {

	}

	public function sigHandler($signo) {
		switch ($signo) {
			case SIGCHLD:
				$this->checkChildren();
				break;
		}
	}

	public function checkChildren() {
		foreach ($this->myChildren as $i => $child) {
			// Check for time running and if still running
			if ($this->pidDead($child['pid']) != 0) {
				// Child is dead
				unset($this->myChildren[$i]);
			} elseif ($this->timeLimit > 0) {
				// Check the time limit
				if (time() - $child['time'] >= $this->timeLimit) {
					// Child had exceeded time limit
					$this->killChild($child['pid']);
					unset($this->myChildren[$i]);
				}
			}
		}
	}

	public function myPID() {
		return posix_getpid();
	}

	public function myParent() {
		return posix_getppid();
	}

	public function spawnChild() {
		$time = time();
		$pid = pcntl_fork();
		if ($pid) $this->myChildren[] = array('time'=>$time,'pid'=>$pid);
	}

	public function killChild($pid = 0) {
		if ($pid > 0) {
			posix_kill($pid, SIGTERM);
			if ($this->killCallback !== null) call_user_func($this->killCallback);
		}
	}

	public function parentCheck() {
		if ($this->myPID() == $this->parentPID) {
			return true;
		} else {
			return false;
		}
	}

	public function pidDead($pid = 0) {
		if ($pid > 0) {
			return pcntl_waitpid($pid, $status, WUNTRACED OR WNOHANG);
		} else {
			return 0;
		}
	}

	public function setCallback($callback = null) {
		$this->shutdownCallback = $callback;
	}
	
	public function setKillCallback($callback = null) {
		$this->killCallback = $callback;
	}

	public function childCount() {
		return count($this->myChildren);
	}

	public function runParentCode() {
		if (!$this->complete) {
			return $this->parentCheck();
		} else {
			if ($this->shutdownCallback !== null)
				call_user_func($this->shutdownCallback);
			return false;
		}
	}

	public function runChildCode() {
		return !$this->parentCheck();
	}

	public function spawnReady() {
		if (count($this->myChildren) < $this->maxChildren) {
			return true;
		} else {
			return false;
		}
	}

	public function shutdown() {
		while($this->childCount()) {
			$this->checkChildren();
			$this->tick();
		}
		$this->complete = true;
	}

	public function tick() {
		sleep($this->sleepCount);
	}
}


/*
Permission is hereby granted, free of charge, to any person obtaining a
copy of this software and associated documentation files (the "Software"),
to deal in the Software without restriction, including without limitation
the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
DEALINGS IN THE SOFTWARE.

https://github.com/lordgnu/PowerSpawn for usage examples
*/
