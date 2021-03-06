<?php
/**
 * @name Thread.php
 * @author Alex Snet ( me@alexsnet.ru )
 * @author Moisés Márquez (https://github.com/mmarquez)
 * @version 1.1.2
 * @copyright free for use
 * @category forking
 * @category system
 */
class Thread{
	public $child = false;
	private $pid = false;
	private $file;
	private $tmpdir;
	private $children = array();
	private $vars = array();
	
	public function __construct($argv,$argc){
		$GET = array();
		$this->pid = getmypid();

		if ($argc != null){
			$this->file = str_replace(' ','\ ',getcwd()).'/'.$argv[0];
		}else{
			$this->file = $argv;
		}

		for($i=1;$i<$argc;$i++){
			if(substr($argv[$i],0,1)=='-' && ( isset($argv[($i+1)]) && substr($argv[($i+1)],0,1)!=='-')){
				$GET[substr($argv[$i],1)] = $argv[($i+1)];
				$i++;
			}else{
				$GET[substr($argv[$i],1)] = true;
			}
		}
		$this->vars = $GET;
		if(isset($this->vars['tmpdir'])){
			$this->child = $this->vars['pid'];
			$this->tmpdir = $this->vars['tmpdir'];
		}else{
			$this->tmpdir = '/tmp/'.md5($this->file . time());
			mkdir($this->tmpdir);
			$limitTime = "60";
			$now = 0;
			while (!is_dir($this->tmpdir)){
				if ($now < $limitTime){
					sleep(1);
					$now++;
				}
			}
		}
		if (!is_dir("{$this->tmpdir}/vars")){
			mkdir($this->tmpdir.'/vars');
		}
	}
	
	public function __destruct(){
		if($this->child == false){
			if(file_exists($this->tmpdir.'/vars/')){
				$vars = scandir($this->tmpdir.'/vars/');
				foreach($vars as $var){
					if(strlen($var)>3){
						unlink($this->tmpdir.'/vars/'.$var);
					}
				}
				rmdir($this->tmpdir.'/vars/');
			}
			if(file_exists($this->tmpdir.'/threads/')){
				rmdir($this->tmpdir.'/threads/');
			}
			$outputs = scandir($this->tmpdir);
			foreach ($outputs as $output){
				if(strlen($output)>3){
					unlink("{$this->tmpdir}/{$output}");
				}
			}
			rmdir($this->tmpdir);
		}else{
			unlink("{$this->tmpdir}/threads/{$this->child}.pid");
		}
	}

	/**
	 * Allows the communication between process.
	 * @param String $var The name of the variable to read
	 * @return String The value of the variable
	 */
	public function __get($var){
		$val = false;
		if(file_exists($this->tmpdir.'/vars/'.md5($var))){
			$val = file_get_contents($this->tmpdir.'/vars/'.md5($var));
			$val = unserialize($val);
		}
		return $val;
	}

	/**
	 * Allows the communication between processes.
	 * Send the value of the specified variable.
	 * @param String $var The name of the variable to write
	 * @param void* $val A serializable value to write
	 */
	public function __set($var,$val){
		$vall = $this->{$var};
		if($vall!=$val){
			if ($fp = fopen($this->tmpdir.'/vars/'.md5($var), "w")){
				if (flock($fp, LOCK_EX)){
				    fwrite($fp, serialize($val));
				    flock($fp, LOCK_UN);
				}else{
					$this->{$var} = $val;
				}
				fclose($fp);
			}
		}
	}

	/**
	 * Init the child processes
	 */
	public function startThreads($count=0){
		mkdir($this->tmpdir.'/threads');
		$limitTime = "60";
			$now = 0;
			while (!is_dir($this->tmpdir.'/threads')){
				if ($now < $limitTime){
					sleep(1);
					$now++;
				}
			}
		for($i=1;$i<=$count;$i++){
			$pid = $this->tmpdir.'/threads/'.$i.'.pid';
			$this->children[$i]['pid'] = popen("php {$this->file} -tmpdir {$this->tmpdir} -pid {$i} > {$pid} 2> {$this->tmpdir}_{$i}.log &",'r');
		}
		sleep(1);
	}

	/**
	 * Get the number of actual childs
	 */
	public function childs(){
		$dd = 0;
		$darr = scandir($this->tmpdir.'/threads/');
		foreach($darr as $d){
			if($d!='.' && $d!='..'){
				$dd++;	
			}
		} 
		return $dd;
	}

	/**
	 * Write the messages to a file in the temporal dir with the id of the child.
	 * Only works if the process is a child.
	 */
	public function write($messages){
		if (is_object($messages)){
			if ($this->child){
				$str = json_encode($messages);
				
				$f = fopen($this->tmpdir.'/'.$this->child.'.pid','a');
				fwrite($f,$str);
				fclose($f);
			}
		}
	}

	/**
	 * Try to read the information written by the process.
	 * @param int $pid The pid of the process to read
	 * @return Object|String Return an object with the written information or false in error.
	 */
	public function read($pid){
		if ((!$this->child) && (is_readable("{$this->tmpdir}/{$pid}.pid"))){
			return json_decode(file_get_contents("{$this->tmpdir}/{$pid}.pid"));
		}else{
			return false;
		}
	}
}
