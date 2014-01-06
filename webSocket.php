<?php 
/**
 * 
 * @author chengjun <cgjp123@163.com>
 * @date 2014.1.4
 * 参考：
 * http://blog.csdn.net/binyao02123202/article/details/17577051
 * http://www.zendstudio.net/tag/websocket/
 * http://code.google.com/p/phpwebsocket/source/browse/#svn/trunk/%20phpwebsocket
 * http://tools.ietf.org/html/rfc6455
 */
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush(true);

$socket = new webSocket();
$socket->run();

class webSocket {
	
	public $host = '127.0.0.1';
	
	public $port = '12345';
	
	public $socket = null;
	
	public $users = null;
	
	public $master = null;
	
	public $debug = true;
	
	public function __construct(){
		$this->master = $this->socketConnect();
		$this->socket[] = $this->master;
		$this->users = array();
	}
	
	/**
	 * 监听客户端请求
	 */
	public function run(){
		while (true){
			$changed = $this->socket;
			$write = null;
			$except = null;
			socket_select($changed, $write, $except, 0);
			foreach($changed as $socket){
				if($socket == $this->master){
					$client = socket_accept($socket);
					if($client < 0){
						$this->out('socket_accept() faild');
						continue;
					}else{
						$this->connect($client);
					}
				}else{
					$bytes = socket_recv($socket, $data, 2048, 0);
					if($bytes){
						$user = $this->getUserBySocket($socket);
						if(!$user->handle){
							$this->handShake($user,$data);
						}else{
							$this->process($user,$data);
							//$user->heart = 1;
						}
					}
					
					//执行心跳检测，检查客户端是否已经断开
					//$user->heart += 2;
					//if($user->heart == 1000){
						//发送信息到客户端
					//}
				}
			}
			//释放cpu
			sleep(1);
		}
	}
	
	/**
	 * 处理客户端发送的数据
	 * @param unknown_type $user
	 * @param unknown_type $data
	 */
	private function process($user,$data){
		$msg = $this->deCode($data);
		$this->send($user,$msg);
	}
	
	/**
	 * 发送数据到客户端
	 * @param unknown_type $socket
	 * @param unknown_type $data
	 */
	private function send($user,$data){
		/* if($data=='start'){
			$user->ssh->sshInfo = 'test';
		} */
		$this->out($data);
		$msg = $this->enCode($user->ssh->sshInfo);
		socket_write($user->socket, $msg,strlen($msg));
	}
	
	/**
	 * 获取当前socket链接的用户对象
	 * @param unknown_type $socket
	 */
	private function getUserBySocket($socket){
		$found = null;
		foreach($this->users as $user){
			if($user->socket == $socket){
				$found = $user;
				break;
			}
		}
		return $found;
	}
	
	/**
	 * 创建当前客户端用户链接
	 * @param unknown_type $socket
	 */
	private function connect($socket){
		$users = new users();
		$users->userId = uniqid();
		$users->socket = $socket;
		$users->handle = false;
		$users->heart = 1;
		$ssh = new ssh();
		$ssh->sshInfo = 'ssh ceshi\r\nssh ceshi\r\nssh ceshi\r\nssh ceshi\r\nssh ceshi\r\nssh ceshi\r\nssh ceshi\r\nssh ceshi\r\nssh ceshi\r\nceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshiceshi';
		$users->ssh = $ssh;
		
		array_push($this->users, $users);
		array_push($this->socket, $socket);
		$this->out($socket.'connected');
	}
	
	private function close($user){
		
	}
	
	/**
	 * 建立socket链接
	 * 这里只是建立socket套接字，并未进行真实链接
	 */
	private function socketConnect(){
		//创建套接字
		$master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($master, SOL_SOCKET, SO_REUSEADDR, 1);
		socket_bind($master, $this->host,$this->port);
		//最多监听20条请求
		socket_listen($master,20);
		echo "Server Starter : " . date('Y-m-d H:i:s')."\r\n";
		echo "Listening on : " . $this->host .":".$this->port."\r\n";
		return $master;
	}
	
	/**
	 * 与客户端握手
	 */
	private function handShake($user,$data){
		$key = $this->getClientHeader($data);
		$this->out('key='.$key);
		$mask = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
		//加密key
		$newKey = base64_encode( sha1($key.$mask,true) );
		$responseHeader = "HTTP/1.1 101 Switching Protocols\r\n";
		$responseHeader .= "Upgrade: websocket\r\n";
		$responseHeader .= "Connection: Upgrade\r\n";
		$responseHeader .= "Sec-WebSocket-Version: 13\r\n";
		$responseHeader .= "Sec-WebSocket-Accept: {$newKey}\r\n";
		$responseHeader .= "\r\n";
		socket_write($user->socket, $responseHeader,strlen($responseHeader));
		$user->handle = true;
		$this->out('handShake success');
	}
	
	/**
	 * 获取客户端请求头信息
	 */
	private function getClientHeader($data){
		preg_match('/Sec-WebSocket-Key: (.*)\r\n/',$data,$match);
		$key = $match[1];
		return $key;
	}
	
	/**
	 * 编码
	 */
	private function enCode($msg){
		$msg = preg_replace(array('/\r$/','/\n$/','/\r\n$/'), '<br>', $msg);
		$frame = array();
		$frame[0] = '81';//FIN位必须是1，opcode必须是1（表示文本），所以必须以0x81开头
		$len = strlen($msg);
		if($len<127){//数据长度小于127（0x7F）,因为mask位必须0，所以第3数据位最大为7，第四数据位最大为F
			if($len<16){
				$frame[1] = '0'.dechex($len);
			}else{
				$frame[1] = dechex($len);
			}
		}elseif($len<hexdec('FFFF')){//数据长度大于127（0x7F）,小于FFFF，则属于16位负载长度，长度数据位指定为0x7E(126)，然后2字节（4个数据位）的长度最大是FFFF
			$frame[1] = dechex(126);
			$hexNumber = dechex($len);
			//填充数位到指定长度
			//方法1
			//$frame[1] .= sprintf('%04s',$hexNumber);
			//方法2
			$frame[1] .= str_pad($hexNumber, 4,'0',STR_PAD_LEFT );
		}else{//数据长度大于FFFF，则属于64位负载长度，长度数据位指定为0x7F(127)
			$frame[1] = dechex(127);
			$frame[1] .= str_pad($hexNumber, 16,'0',STR_PAD_LEFT );
		}
		//$frame[1] = $len<16?'0'.dechex($len):dechex($len);
		$frame[2] = $this->ord_hex($msg);
		$this->out($len);
		$this->out($frame);
		$data = implode('',$frame);
		return pack('H*',$data);
	}
	
	/**
	 * 解码
	 */
	private function deCode($str){
		$mask = array();
		$data = '';
		$msg = unpack('H*', $str);
		$head = substr($msg[1],0,2);
		$this->out($msg);
		if(hexdec($head{1})===8){
			$data = false;
		}else if(hexdec($head{1})===1){
			$payLoadLen = hexdec(substr($msg[1],2,2))-128;
			$this->out($payLoadLen);
			if($payLoadLen<126){//当数据长度小于126
				$mask[] = hexdec(substr($msg[1],4,2));
				$mask[] = hexdec(substr($msg[1],6,2));
				$mask[] = hexdec(substr($msg[1],8,2));
				$mask[] = hexdec(substr($msg[1],10,2));
				$s = 12;
				$e = strlen($msg[1])-2;
				$n = 0;
				$this->out($mask);
			}elseif($payLoadLen==126){//当数据长度等于126，后面2个字节（4个数位）表示数据长度，之后8位是掩码
				$mask[] = hexdec(substr($msg[1],8,2));
				$mask[] = hexdec(substr($msg[1],10,2));
				$mask[] = hexdec(substr($msg[1],12,2));
				$mask[] = hexdec(substr($msg[1],14,2));
				$s = 16;
				$e = strlen($msg[1])-2;
				$n = 0;
			}elseif($payLoadLen==127){//当数据长度等于126，后面8个字节（16个数位）表示数据长度，之后8位是掩码
				$mask[] = hexdec(substr($msg[1],24,2));
				$mask[] = hexdec(substr($msg[1],26,2));
				$mask[] = hexdec(substr($msg[1],28,2));
				$mask[] = hexdec(substr($msg[1],30,2));
				$s = 32;
				$e = strlen($msg[1])-2;
				$n = 0;
			}
			
			for($i=$s;$i<=$e;$i+=2){
				$data .= chr($mask[$n%4]^hexdec(substr($msg[1],$i,2)));
				$n++;
			}			
		}
		return $data;
	}
	
	private function ord_hex($data){
		$msg = '';
		$l = strlen($data);
		for($i=0;$i<$l;$i++){
			$msg .= dechex(ord($data{$i}));
		}
		return $msg;
	}
	
	/**
	 * 输出测试信息
	 * @param unknown_type $msg
	 */
	private function out($msg){
		if($this->debug){
			echo "wpInfo>>";
			print_r($msg);
			echo "\r\n";
		}
	}
}

class users {
	public $userId;
	public $socket;
	public $handle;
	public $ssh;
	public $heart;
}

class ssh{
	public $sshInfo;
}