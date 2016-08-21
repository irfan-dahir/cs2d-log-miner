<?php
require 'Ubench.php';
require 'log-miner.php';
$ubench = new Ubench;
$ubench->start();
$miner = new LogMiner;
$extracted = $miner->Extract("logs");
$miner->ExtractedToJSON($extracted, true);
$ubench->end();
var_dump($extracted);
echo "Processed ".count($extracted)." log(s) in ".$ubench->getTime()."<br>";
echo "Memory Usage: ".$ubench->getMemoryUsage()."<br>";
echo "Memory Peak: ".$ubench->getMemoryPeak()."<br>";
echo "<br><br><h3>Extracted Data</h3>";
foreach ($extracted as $log => $data) {
	echo "<h4>".$log."</h4>";
	echo "Serverlist update requests: ".count($data["server"]["server-list-update-requests"])."<br>";
	echo "Serverlist updates: ".count($data["server"]["server-list-updates"])."<br>";
	echo "Map changes: ".count($data["server"]["map-changes"])."<br>";
	echo "Transfer Files: ".count($data["server"]["transfer-files"])."<br>";
	echo "Missing Transfer Files: ".count($data["server"]["missing-transfer-files"])."<br>";
	echo "Unique Player(s) registered: ".count($data["player"])."<br>";
	var_dump($data["player"]);
}
?>