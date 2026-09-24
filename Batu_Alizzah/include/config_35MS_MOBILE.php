<?php
//define koneksi variable
$host = "10.99.23.22";//192.168.1.22
$base = "gerbang_uang";//gerbang_uang
$user = "sa";
$pawd = "Bismillah99";//Bismillah99

$dbhandle = mssql_connect($host, $user, $pawd) or die("Couldn't connect to SQL Server on $host"); 
  
//select a database to work with
$selected = mssql_select_db($base, $dbhandle)
  or die("Couldn't open database $dbhandle"); 
?>
