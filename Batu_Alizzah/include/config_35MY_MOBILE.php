<?php
//define koneksi variable
$host = "10.99.23.22";//192.168.1.22
$base = "farrelgantengsekali";//gerbang_uang
$user = "root";
$pawd = "Smartpay1ct";//Bismillah99

$dbhandle = mysql_connect($host, $user, $pawd) or die("Couldn't connect to SQL Server on $host"); 
  
//select a database to work with
$selected = mysql_select_db($base, $dbhandle)
  or die("Couldn't open database $dbhandle"); 
?>
