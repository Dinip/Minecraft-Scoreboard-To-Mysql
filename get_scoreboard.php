<?php
include ('config.php');
require_once ('rcon.php');
use Thedudeguy\Rcon;

date_default_timezone_set('Europe/Lisbon');
$time = date("Y-m-d H:i:s");
if($rconPing = fsockopen($rconHost,$rconPort,$errCode,$errStr,$waitTimeoutInSeconds)){
  echo "rcon successfully pinged \n";   
  if($mysqlPing = fsockopen($dbHost,$dbPort,$errCode,$errStr,$waitTimeoutInSeconds)){
    echo "mysq successfully pinged \n";
    fclose($rconPing);
    fclose($mysqlPing);
    $rcon = new Rcon($rconHost, $rconPort, $rconPassword, $rconTimeout);
    if ($rcon->connect()){
      //Executes the command to get player list
      $rcon->sendCommand("whitelist list");
      //Gets the name of all players (string)
      $whitelist = $rcon->getResponse();
    
      //Starts mysql connection
      $mysqlConnection = new mysqli($dbHost.':'.$dbPort, $dbUser, $dbPassword, $dbName);
      if ($mysqlConnection->connect_error) {
        die("Connection failed: " . $mysqlConnection->connect_error);
      }
      
      //Remove phrase from whitelist output
      $whitelist = preg_replace('/There are [0-9]+ whitelisted players: /', '', $whitelist);
      
      //Removes "," from the names string to make it an array
      $nomes = array_map('trim', explode(',', $whitelist));
      
      //Objectives to get
      $objectivesToGet = array('hc_playTime', 'ts_Deaths');
      
      foreach ($objectivesToGet as $objectiveToGet) {
        //Gets objetive data for every player
        foreach($nomes as $player){
          $rcon->sendCommand("scoreboard players get $player $objectiveToGet");
          $playerHasValue = $rcon->getResponse();
          //Verifies if player have any value in that objective and if not set value to 0
          if (strpos($playerHasValue, 'none is set') == true){
            $value = "0";
          } else {
            $value = preg_replace('/\w+ has /', '', $playerHasValue);
            $value = preg_replace('/\[(.*?)\]/', '', $value);
            $value = intval($value);
          }
        
          //Verifies is the table for that objective exists
          $tableExists = $mysqlConnection->query("SHOW TABLES LIKE '$objectiveToGet'");
          $firstRun = true;
          //If exists
          if($tableExists->num_rows == 1 ){
              //Inserts data into db
              $sql = "INSERT INTO $objectiveToGet (player, value, time)
              VALUES ('$player', '$value', '$time')";
              
              if ($mysqlConnection->query($sql) === TRUE) {
                  echo $objectiveToGet . ": " . $player . " " . $value . " created @ " . $time . " \n";
              } else {
                  echo "Error: " . $sql . " " . $mysqlConnection->error . "\n";
              }
          }else{
            if ($firstRun){
              // Creates the table if doesn't exist
              $sql = "CREATE TABLE $objectiveToGet (
              player text NOT NULL,
              value int(10) NOT NULL,
              time datetime NOT NULL)";
              $firstRun = false;
            }
            if ($mysqlConnection->query($sql) === TRUE) {
                echo "Table $objectiveToGet created successfully \n";
            } else {
                echo "Error creating table: " . $mysqlConnection->error . "\n";
            }
            //Inserts data into db
            $sql = "INSERT INTO $objectiveToGet (player, value, time)
            VALUES ('$player', '$value', '$time')";
            
            if ($mysqlConnection->query($sql) === TRUE) {
                echo $objectiveToGet . ": " . $player . " " . $value . " created @ " . $time . " \n";
            } else {
                echo "Error: " . $sql . " " . $mysqlConnection->error . "\n";
            }
          }
        }
      }
      $rcon->disconnect();
      $mysqlConnection->close();
    } else {
      echo "rcon server not found \n";
      fclose($rconPing);
    }
  } else {
    echo "mysql server not found \n";
    fclose($mysqlPing);
  }
} else {
  echo "rcon server not found \n";
} 