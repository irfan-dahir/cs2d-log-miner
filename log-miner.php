<?php
/*
 *	Log Miner 0.1.0 not-even-alpha
 *	Copyright (c) 2016 Nighthawk/Nekomata (#116310)
 */


const VALID_FILETYPE = "txt";
const LOGGING = true;

class LogMiner{
	

	protected $directory = array();
	protected $log = null;
	private $workingArrayPtr = null;

	public function __construct(){
		if(LOGGING){
			if(!file_exists("log-miner.log")){$f = fopen("log-miner.log", "w+");fclose($f);}
			$this->log = fopen("log-miner.log", "a+");
		}
	}
	public function __destruct(){
		if(LOGGING){
			fclose($this->log);
		}
	}
	/**
	 * Extract statistical or string data from the logs
	 * @param string $fileOrDir	A filename or directory
	 * @return array
	 */
	public function Extract($fileOrDir, $string = null){
		if($this->load($fileOrDir)){
			foreach ($this->directory as $key => $file) {
				$fileName = pathinfo($file, PATHINFO_FILENAME);
				$fileType = pathinfo($file, PATHINFO_EXTENSION);
				if($fileType === VALID_FILETYPE){
					list($date, $time, $timestampUNIX, $timestamp) = $this->parseLogTimeFormats($fileName);
					$log[ $timestampUNIX ] = array();
					$log[ $timestampUNIX ]["size"] = filesize($file);
					$log[ $timestampUNIX ]["date"] = $date;
					$log[ $timestampUNIX ]["time"] = $time;
					$log[ $timestampUNIX ]["timestamp"] = $timestamp;
					$log[ $timestampUNIX ]["timestamp-unix"] = $timestampUNIX;
					$log[ $timestampUNIX ]["player"] = array();
					$log[ $timestampUNIX ]["server"] = array(
						"server-list-update-requests"=>array(),
						"server-list-updates"=>array(),
						"map-changes"=>array(),
						"transfer-files"=>array(),
						"missing-transfer-files"=>array(),
					);
					if(is_null($string)){
						$this->mineData($file, $log[ $timestampUNIX ]);
					}else{
						$this->mineString($file, $log[ $timestampUNIX ], $string);
					}
				}else{
					//error not valid
					return false;
				}
			}
			return $log;
		}else{
			//error not found
			return false;
		}
	}

	public function DataToJSON($dataArr, $save=false){
		if(isset($dataArr["timestamp"])){
			if($save){
				$f = fopen($dataArr["timestamp"]."json", "w+");
				fwrite($f, json_encode($dataArr));
				fclose($f);
			}else{
				return json_encode($dataArr);
			}
		}else{
			//error
		}
	}

	protected function mineData($file, &$array){
		$lines = file($file);
		if(preg_match("/Counter-Strike 2D [0-9].[0-9].[0-9].[0-9] Logfile - [0-9]{2} [A-Z][a-z]{2} [0-9]{4}, [0-9]{2}:[0-9]{2}:[0-9]{2}/", $lines[0])){
			$array["header"] = trim($lines[0]);
		}
		foreach ($lines as $lineNumber => $line) {
			if(preg_match("/U.S.G.N.: Sending serverlist UPDATE-request.../", $line)){
				$array["server"]["server-list-update-requests"][] = $this->toUnixTimestamp($array["date"],substr($line, 1, -48));
			}elseif(preg_match("/U.S.G.N.: Serverlist entry updated/", $line)){
				$array["server"]["server-list-updates"][] = $this->toUnixTimestamp($array["date"],substr($line, 1, -36));
			}elseif(preg_match("/ connected$/", $line)){
				$playerRaw = substr($line, 11, -11);
				$player = substr($line, 11, -11);
				$playerOS = false;
				for($i=1;$i<=5;$i++){
					$OSScan = $lines[$lineNumber-$i];
					if(preg_match("/ clientdata: (.*) /", $OSScan)){
						$playerOSTrimmed = trim($OSScan,11);
						$playerOSRaw = false;
						preg_match("/ clientdata: (.*) /", $playerOSTrimmed, $playerOSRaw);
						$playerOS = $playerOSRaw[1];
						break;
					}
				}
				$playerData = $lines[$lineNumber+1];
				$ipUnfiltered = array();
				$usgnUnfiltered = array();
				preg_match("/is using IP (.*) and/", $playerData, $ipUnfiltered);
				$playerIPRawDiv = explode(":", $ipUnfiltered[1]);
				$playerIP = $playerIPRawDiv[0];
				$playerPort = $playerIPRawDiv[1];
				$playerUSGN = (preg_match("/and U.S.G.N. ID/", $playerData)) ? (int)trim(substr($playerData, strpos($playerData, "and U.S.G.N. ID")+17)) : false;
				//check if a player exists
				if(array_key_exists($player, $array["player"])){
					//if player exists, check if it's player data array type 1 or 2
					//if it's a type 1 then update or convert to type 2
					echo $player." EXISTS <br>";
					if(array_key_exists("ip", $array["player"][$player])){
						echo $player." IS TYPE 1 <br>";
						//if it's the same ip under the same name
						if($array["player"][$player]["ip"] == $playerIP){
							echo $player." TYPE 1 IS SAME PLAYER ".$playerIP."<br>";
							$array["player"][$player]["connect"][] = (int)$this->toUnixTimestamp($array["date"],substr($line, 1, strpos($line, $playerRaw)-3));
						}else{
							echo $player." TYPE 1 IS DIFFERENT PLAYER ".$playerIP."<br>";
						//if it's a different ip under the same name
						//then convert to data type 2 & update
							//backup previous type 1
							$tempPlayerArray = $array["player"][$player];
							//dissolve the data array
							$array["player"][$player] = null;
							//recreate data array
							$array["player"][$player] = array();
							//register new data
							$array["player"][$player][] = array(
								//"name"=>$player,
								"usgn"=>$playerUSGN,
								"ip"=>$playerIP,
								"port"=>$playerPort,
								"os"=>$playerOS,
								"connect"=>array(
										(int)$this->toUnixTimestamp($array["date"],substr($line, 1, strpos($line, $playerRaw)-3))
									),
								"disconnect"=>array(),
								"unparsed"=>trim(substr($playerData, 11)),
							);
							//restore previous as type 2
							$array["player"][$player][] = $tempPlayerArray;
						}
					}else{
						echo $player." IS TYPE 2<br>";
						//if it's a type 2 then check if player is registered in type 2 & update
						//else register a new one
						$exists = false;
						foreach ($array["player"][$player] as $key => $data) {
							if($data["ip"] == $playerIP){
								//player IP exists // update
								echo $player." TYPE 2 IS SAME PLAYER ".$playerIP."<br>";
								$array["player"][$player][$key]["connect"][] = (int)$this->toUnixTimestamp($array["date"],substr($line, 1, strpos($line, $playerRaw)-3));
								$exists = true;
								break;
							}
						}
						if(!$exists){
							//register new player
							echo $player." TYPE 2 IS DIFFERENT PLAYER ".$playerIP."<br>";
							$array["player"][$player][] = array(
								//"name"=>$player,
								"usgn"=>$playerUSGN,
								"ip"=>$playerIP,
								"port"=>$playerPort,
								"os"=>$playerOS,
								"connect"=>array(
										(int)$this->toUnixTimestamp($array["date"],substr($line, 1, strpos($line, $playerRaw)-3))
									),
								"disconnect"=>array(),
								"unparsed"=>trim(substr($playerData, 11)),
							);
						}
					}
				}else{
					//if player does not exist, then register new type 1
					echo $player." DOESNT EXIST ".$playerIP."<br>";
					$array["player"][$player] = array(
						//"name"=>$player,
						"usgn"=>$playerUSGN,
						"ip"=>$playerIP,
						"port"=>$playerPort,
						"os"=>$playerOS,
						"connect"=>array(
								(int)$this->toUnixTimestamp($array["date"],substr($line, 1, strpos($line, $playerRaw)-3))
							),
						"disconnect"=>array(),
						"unparsed"=>trim(substr($playerData, 11)),
					);
				}
			}elseif(preg_match("/has left the game/", $line)){
				$playerRaw = substr($line, 11, -11);
				$reason = substr($playerRaw, (strpos($line, "has left the game")+17));
				$array["player"]["disconnect"][] = (int)$this->toUnixTimestamp($array["date"],substr($line, 1, strpos($line, $playerRaw)-3));
				$array["player"]["disconnect-reason"][] = $reason;
			}elseif(preg_match("/----- Mapchange -----/", $line)){
				$array["server"]["map-changes"][] = $this->toUnixTimestamp($array["date"],substr($line, 1, -24));
				//$array["server"]["map-changes"][] = substr($line, 1, -24); in hh:mm:ss format
			}elseif(preg_match("/adding transfer file: /", $line)){
				$filtered = (string) trim(substr($line, 33));
				if(!in_array($filtered, $array["server"]["transfer-files"])){
					$array["server"]["transfer-files"][] = $filtered;
				}
			}elseif(preg_match("/ERROR: Can't add '(gfx|sfx)\/.{1,}' to transfer list: file does not exist!/", $line)){
				// refactor: to pregmatch parsing
				$filtered = trim(substr($line, 11));
				$filtered = preg_replace("/ERROR: Can't add '/", "", $filtered);
				$filtered = preg_replace("/' to transfer list: file does not exist!/", "", $filtered);
				if(!in_array($filtered, $array["server"]["missing-transfer-files"])){
					$array["server"]["missing-transfer-files"][] = $filtered;
				}
			}
		}
		//return $array;
	}
	protected function mineString($file, &$array, $string){
		$lines = file($file);
		foreach ($lines as $lineNumber => $line) {
		}
	}
	/*
	 * Convert the log's filename into a proper timestamp
	 */
	protected function parseLogTimeFormats($filename){
		$div = explode("_", $filename);
		//$date = explode("-", $div[0]);
		$date = date("d-m-Y", strtotime($div[0]));
		$time = preg_replace("/-/", ":", $div[1]);
		return array(
			$date, $time, $this->toUnixTimestamp($date,$time), ($date." ".$time)
			);
	}
	/*
	 * Convert CS2D's timestamp to unix/epoch format
	 */
	protected function toUnixTimestamp($date, $time){
		return (int)strtotime($date." ".$time);
	}

	/*
	 * Queue the given file/directory files
	 */
	protected function load($fileOrDir){
		if(is_dir($fileOrDir)){
			$this->directory = $this->scanDirectory($fileOrDir);
			return (count($this->directory) > 0) ? true : false; 
		}else{
			if(file_exists($fileOrDir)){
				array_push($this->directory, $fileOrDir);
			}else{
				return false;
			}
		}
	}

	/*
	 * Scan the given directory
	 */
	protected function scanDirectory($directory){
		$files = array_diff(scandir($directory), array(".", ".."));
		foreach ($files as $key => $value) {
			$files[$key] = $directory."/".$value;
		}
		return $files;
	}
	private function log($text){
		if(LOGGING){
			fwrite($this->log, $text);
		}
	}
}

require 'Ubench.php';
$ubench = new Ubench;
$ubench->start();
$miner = new LogMiner;
//var_dump($miner->extract("logs")[1462042832]["player"]);
$extracted = $miner->Extract("logs");
var_dump($extracted[1462042832]["player"]);
$ubench->end();
echo "Processed ".count($extracted)." log(s) in ".$ubench->getTime()."<br>";
echo "Memory Usage: ".$ubench->getMemoryUsage()."<br>";
echo "Memory Peak: ".$ubench->getMemoryPeak()."<br>";
?>