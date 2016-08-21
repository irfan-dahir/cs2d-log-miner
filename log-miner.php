<?php
/**
 * Log Miner 
 * @version 0.5.0.0 alpha
 * Copyright (c) 2016 @author Nighthawk/Nekomata (#116310)
 */

class LogMiner {

	const VALID_FILETYPE = "txt";
	const LOGGING = true;

	protected $directory = array();
	protected $log = null;

	/**
	* Construct is used for creating/handling the log file
	*/
	public function __construct() {
		if (self::LOGGING) {
			if (!file_exists("log-miner.log")) {
				$f = fopen("log-miner.log", "w+");
				fclose($f);
			}
			$this->log = fopen("log-miner.log", "a+");
		}
	}

	/**
	* Destruct closes the log handle if there's one
	*/
	public function __destruct() {
		if (self::LOGGING) {
			fclose($this->log);
		}
	}

	/**
	 * Extract statistical or string data from the logs
	 * @param string $fileOrDir	A file path or directory
	 * @param string $string an optional argument which triggers text based searching
	 * @return array
	 */
	public function Extract($fileOrDir, $string = null) {
		if($this->load($fileOrDir)){
			foreach ($this->directory as $key => $file) {
				$fileName = pathinfo($file, PATHINFO_FILENAME);
				$fileType = pathinfo($file, PATHINFO_EXTENSION);
				if (self::validFileType($fileType)) {
					list($date, $time, $timestampUNIX, $timestamp) = $this->parseLogTimeFormats($fileName);
					$log[ $timestampUNIX ] = array();
					$log[ $timestampUNIX ]["filesize"] = filesize($file);
					clearstatcache(); //no filesize caching
					$log[ $timestampUNIX ]["date"] = $date;
					$log[ $timestampUNIX ]["time"] = $time;
					$log[ $timestampUNIX ]["timestamp"] = $timestamp;
					$log[ $timestampUNIX ]["timestamp-unix"] = $timestampUNIX;
					$log[ $timestampUNIX ]["filepath"] = $file;
					$log[ $timestampUNIX ]["server"] = array(
						"server-list-update-requests"=>array(),
						"server-list-updates"=>array(),
						"map-changes"=>array(),
						"transfer-files"=>array(),
						"missing-transfer-files"=>array(),
						"stats-generated"=>array(),
					);
					$log[ $timestampUNIX ]["player"] = array();
					if(is_null($string)){
						$this->mineData($file, $log[ $timestampUNIX ]);
					}else{
						if(!empty($string)){
							$this->mineString($file, $log[ $timestampUNIX ], $string);
						}else{
							self::log("error: empty string");
						}
					}
				}else{
					self::log("error: invalid filetype");
					return false;
				}
			}
			return $log;
		}else{
			self::log("error: file not found");
			return false;
		}
	}

	/**
	* mine statistical cs2d log stuff
	* internal method triggered by @see self::Extract()
	* @param string $file filepath
	* @param array $array
	*/
	private function mineData($file, &$array){
		self::log("debug: parsing ".$array["filepath"]);
		$lines = file($file);
		if(preg_match("/Counter-Strike 2D ([a-z]{0,}|) [0-9].[0-9].[0-9].[0-9] Logfile - [0-9]{2} [A-Z][a-z]{2} [0-9]{4}, [0-9]{2}:[0-9]{2}:[0-9]{2}/", $lines[0])){
			$array["header"] = trim($lines[0]);
		}
		foreach ($lines as $lineNumber => $line) {
			if(preg_match("/U.S.G.N.: Sending serverlist UPDATE-request.../", $line)){
				$array["server"]["server-list-update-requests"][] = $this->toUnixTimestamp($array["date"],substr($line, 1, 8));
			}elseif(preg_match("/U.S.G.N.: Serverlist entry updated/", $line)){
				$array["server"]["server-list-updates"][] = $this->toUnixTimestamp($array["date"],substr($line, 1, 8));
			}elseif(preg_match("/(.*) is using IP (.*):(.*) and no U.S.G.N. ID/", $line) || preg_match("/(.*) is using IP (.*):(.*) and U.S.G.N. ID #(.*)/", $line)){

				$time = substr($line, 1, 8);
				$playerDataRaw = utf8_encode(substr($line, 11));
				$playerData = array();
				if (preg_match("/(.*) is using IP (.*):(.*) and U.S.G.N. ID #(.*)/", $playerDataRaw)) {
					preg_match("/(.*) is using IP (.*):(.*) and U.S.G.N. ID #(.*)/", $playerDataRaw, $playerData);
				}
				elseif (preg_match("/(.*) is using IP (.*):(.*) and no U.S.G.N. ID/", $playerDataRaw)) {
					preg_match("/(.*) is using IP (.*):(.*) and no U.S.G.N. ID/", $playerDataRaw, $playerData);
					$playerData[] = false;
				} else {
					$playerData = false;
				}
				$playerOS = false;
				for ($i=5;$i>=0;--$i) {
					$OSScan = $lines[$lineNumber-$i];
					if (preg_match("/ clientdata: (.*) /", $OSScan)) {
						$playerOSTrimmed = trim($OSScan,11);
						$playerOSRaw = false;
						preg_match("/ clientdata: (.*) /", $playerOSTrimmed, $playerOSRaw);
						$playerOS = $playerOSRaw[1];
						break;						
					}
				}

				$playerName = $playerData[1];
				$playerIP = $playerData[2];
				$playerPort = (int) $playerData[3];
				$playerUSGN = ($playerData[4] === false) ? false : (int) $playerData[4];

				//check if a player exists
				if(array_key_exists($playerName, $array["player"])){
					//if player exists, check if it's player data array type 1 or 2
					//if it's a type 1 then update or convert to type 2
					//echo $playerName." EXISTS <br>";
					if(array_key_exists("ip", $array["player"][$playerName])){
						//echo $player." IS TYPE 1 <br>";
						//if it's the same ip under the same name
						if($array["player"][$playerName]["ip"] == $playerIP){
							//echo $player." TYPE 1 IS SAME PLAYER ".$playerIP."<br>";
							$array["player"][$playerName]["connect"][] = (int)$this->toUnixTimestamp($array["date"], $time);
						}else{
							//echo $playerName." TYPE 1 IS DIFFERENT PLAYER ".$playerIP."<br>";
						//if it's a different ip under the same name
						//then convert to data type 2 & update
							//backup previous type 1
							$tempPlayerArray = $array["player"][$playerName];
							//dissolve the data array
							$array["player"][$playerName] = null;
							//recreate data array
							$array["player"][$playerName] = array();
							//register new data
							$array["player"][$playerName][] = array(
								"name"=>$playerName,
								"usgn"=>$playerUSGN,
								"ip"=>$playerIP,
								"port"=>$playerPort,
								"os"=>$playerOS,
								"connect"=>array(
										(int)$this->toUnixTimestamp($array["date"], $time),
									),
								"disconnect"=>array(),
								"team"=>array("counter-terrorist"=>array(),"terrorist"=>array(),"spectator"=>array(),"all"=>array()),
								"kills"=>array(),
								"deaths"=>array(),
								//"unparsed"=>$playerDataRaw,
							);
							//restore previous as type 2
							$array["player"][$playerName][] = $tempPlayerArray;
						}
					}else{
						//echo $player." IS TYPE 2<br>";
						//if it's a type 2 then check if player is registered in type 2 & update
						//else register a new one
						$exists = false;
						foreach ($array["player"][$playerName] as $key => $data) {
							if($data["ip"] == $playerIP){
								//player IP exists // update
								//echo $player." TYPE 2 IS SAME PLAYER ".$playerIP."<br>";
								$array["player"][$playerName][$key]["connect"][] = (int)$this->toUnixTimestamp($array["date"], $time);
								$exists = true;
								break;
							}
						}
						if(!$exists){
							//register new player
							//echo $player." TYPE 2 IS DIFFERENT PLAYER ".$playerIP."<br>";
							$array["player"][$playerName][] = array(
								"name"=>$playerName,
								"usgn"=>$playerUSGN,
								"ip"=>$playerIP,
								"port"=>$playerPort,
								"os"=>$playerOS,
								"connect"=>array(
										(int)$this->toUnixTimestamp($array["date"], $time),
									),
								"disconnect"=>array(),
								"team"=>array("counter-terrorist"=>array(),"terrorist"=>array(),"spectator"=>array(),"all"=>array()),
								"kills"=>array(),
								"deaths"=>array(),
								//"unparsed"=>$playerDataRaw,
							);
						}
					}
				}else{
					//if player does not exist, then register new type 1
					//echo $player." DOESNT EXIST ".$playerIP."<br>";
					$array["player"][$playerName] = array(
						"name"=>$playerName,
						"usgn"=>$playerUSGN,
						"ip"=>$playerIP,
						"port"=>$playerPort,
						"os"=>$playerOS,
						"connect"=>array(
								(int)$this->toUnixTimestamp($array["date"], $time),
							),
						"disconnect"=>array(),
						"team"=>array("counter-terrorist"=>array(),"terrorist"=>array(),"spectator"=>array(),"all"=>array()),
						"kills"=>array(),
						"deaths"=>array(),
						//"unparsed"=>$playerDataRaw,
					);
				}

			}elseif(preg_match("/(.*) has left the game/", $line)){
				$playerRaw = utf8_encode(trim(substr($line,11)));
				$playerRawArr = array();
				$reason = false;
				if (preg_match("/(.*) has left the game \(/", $playerRaw)) {
					preg_match("/^(.*) has left the game \((.*)\)/", $playerRaw, $playerRawArr);
					if(!empty($playerRawArr[2])){
						$reason = $playerRawArr[2];
					}
				}
				elseif (preg_match("/^(.*) has left the game$/", $playerRaw)) {
					preg_match("/^(.*) has left the game/", $playerRaw, $playerRawArr);
				}
				$player = $playerRawArr[1];
				//make sure it's not a type-2
				if(isset($array["player"][$player]["usgn"])){
					$array["player"][$player]["disconnect"][ (int)$this->toUnixTimestamp($array["date"],substr($line, 1, strpos($line, $playerRaw)-3)) ] = $reason;
				}
			}elseif(preg_match("/----- Mapchange -----/", $line)){
				if (preg_match("/load map '(.*)'/", $lines[$lineNumber+2])) {
					$mapRaw = array();
					preg_match("/load map '(.*)'/", $lines[$lineNumber+2], $mapRaw);
					$array["server"]["map-changes"][$this->toUnixTimestamp($array["date"],substr($line, 1, 8))] = $mapRaw[1];
				}
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
			}elseif(preg_match("/stats generated in (.*) ms!/", $line)){
				$array["server"]["stats-generated"][$this->toUnixTimestamp($array["date"],substr($line, 1, 8))] = substr($line, 11);
			}elseif(preg_match("/(.*) joins the (.*) Forces/", $line)){
				$playerDataRaw = utf8_encode(substr($line, 11));
				$playerData = array();
				preg_match("/(.*) joins the (.*) Forces/", $playerDataRaw, $playerData);
				$playerName = trim($playerData[1]);
				$playerTeam = strtolower(trim($playerData[2]));
				if (array_key_exists($playerName, $array["player"]) && !array_key_exists(0, $array["player"][$playerName])) {
					$array["player"][$playerName]["team"]["all"][$this->toUnixTimestamp($array["date"],substr($line, 1, 8))] = $playerTeam;
					$array["player"][$playerName]["team"][$playerTeam][] = $this->toUnixTimestamp($array["date"],substr($line, 1, 8));
					//echo $playerName." exists and is now team ".$playerTeam."<br>";
				}				
			}elseif(preg_match("/(.*) is now a spectator/", $line)){
				$playerDataRaw = utf8_encode(substr($line, 11));
				$playerData = array();
				preg_match("/(.*) is now a spectator/", $playerDataRaw, $playerData);
				$playerName = trim($playerData[1]);
				if (array_key_exists($playerName, $array["player"]) && !array_key_exists(0, $array["player"][$playerName])) {
					$array["player"][$playerName]["team"]["all"][$this->toUnixTimestamp($array["date"],substr($line, 1, 8))] = $playerTeam;
					$array["player"][$playerName]["team"]["spectator"][] = $this->toUnixTimestamp($array["date"],substr($line, 1, 8));
				}
			}elseif(preg_match("/(.*) killed (.*) with (.*)/", $line)) {
				$playerDataRaw = utf8_encode(substr($line, 11));
				$playerData = array();
				preg_match("/(.*) killed (.*) with (.*)/", $playerDataRaw, $playerData);
				$playerName = trim($playerData[1]);
				$playerKilled = trim($playerData[2]);
				$playerWeapon = trim($playerData[3]);
				if (array_key_exists($playerName, $array["player"]) && !array_key_exists(0, $array["player"][$playerName])) {
					$array["player"][$playerName]["kills"][$this->toUnixTimestamp($array["date"],substr($line, 1, 8))] = array("victim"=>$playerKilled, "weapon"=>$playerWeapon);
				}				
			}elseif(preg_match("/(.*) died/", $line)) {
				$playerDataRaw = utf8_encode(substr($line, 11));
				$playerData = array();
				preg_match("/(.*) died/", $playerDataRaw, $playerData);
				$playerName = trim($playerData[1]);
				if (array_key_exists($playerName, $array["player"]) && !array_key_exists(0, $array["player"][$playerName])) {
					$array["player"][$playerName]["deaths"][] = $this->toUnixTimestamp($array["date"],substr($line, 1, 8));
				}				
			}
		}
		//return $array;
	}

	protected function mineString($file, &$array, $string) {
		$lines = file($file);
		foreach ($lines as $lineNumber => $line) {
		}
	}

	/**
	 * Convert the log's filename into a proper timestamp
	 */
	public function parseLogTimeFormats($filename) {
		$div = explode("_", $filename);
		$date = date("d-m-Y", strtotime($div[0]));
		$time = preg_replace("/-/", ":", $div[1]);

		return array(
			$date, $time, $this->toUnixTimestamp($date,$time), ($date." ".$time)
			);
	}
	/**
	 * Convert CS2D's timestamp to unix/epoch format
	 */
	public function toUnixTimestamp($date, $time) {
		return (int)strtotime($date." ".$time);
	}

	/*
	 * Queue the given file/directory files
	 */
	private function load($fileOrDir) {
		if (is_array($fileOrDir)) {
			foreach ($fileOrDir as $key => $file) {
				array_push($this->directory, $file);
			}
			return true;
		} elseif (is_string($fileOrDir)) {
			if (is_dir($fileOrDir)) {
				$this->directory = $this->scanDirectory($fileOrDir);
				return (count($this->directory) > 0) ? true : false;
			} elseif (file_exists($fileOrDir)) {
				array_push($this->directory, $fileOrDir);
				return true;
			}
		} return false;
	}

	/*
	 * Scan the given directory for valid cs2d log files
	 */
	private function scanDirectory($directory) {
		$files = array_diff(scandir($directory), array(".", ".."));
		foreach ($files as $key => $value) {
			$filepath = $directory."/".$value;
			if (!self::validateLogHeader($filepath)) {
				unset($files[$key]);
			}else{
				$files[$key] = $filepath;
			}
		}
		return $files;
	}

	private function log($text) {
		if (self::LOGGING) {
			fwrite($this->log, "[".date("d-m-Y H.i.s") . substr((string)microtime(), 1, 8)."]".$text."\n");
		}
	}

	private function validateLogHeader($file) {
		if (preg_match("/Counter-Strike 2D( b| a|) [0-9].[0-9].[0-9].[0-9] Logfile - [0-9]{2} [A-Z][a-z]{2} [0-9]{4}, [0-9]{2}:[0-9]{2}:[0-9]{2}/", fgets(fopen($file, 'r')))) {
			return true;
		}
		return false;
	}
	
	private function validFileType($ext){
		if (preg_match("/".self::VALID_FILETYPE."/", $ext)) {
			return true;
		}
		return false;
	}

	public function DataToJSON($dataArr, $save=false){
		if (is_array($dataArr) && !empty($dataArr) ) {
			if($save){
				$f = fopen(date("d-M-Y_H-i-s",$dataArr["timestamp-unix"]).".json", "w+");
				fwrite($f, json_encode($dataArr));
				fclose($f);
			}else{
				return json_encode($dataArr);
			}
		}else{
			self::log("error: not array/empty array");
			return false;
		}
	}

	public function ExtractedToJSON($extracted, $save=false){
		foreach ($extracted as $key => $value) {
			self::DataToJSON($value, $save);
		}
	}

	// some player names are not utf8 encoded, so we take care of that for json
	//utf8ize - stackoverflow.com/questions/19361282/why-would-json-encode-returns-an-empty-string
	public function utf8ize($d){
		if (is_array($d)) {
			foreach ($d as $k => $v) {
				$d[$k] = self::utf8ize($v);
			}
		} else {
			return utf8_encode($d);
		}
		return $d;
	}
}

?>