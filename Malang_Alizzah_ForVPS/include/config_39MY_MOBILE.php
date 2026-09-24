<?php
//define koneksi variable
$host = "10.99.23.18";//192.168.1.22
$base = "Malang_Alizzah";//gerbang_uang
$user = "root";
$pawd = "Smartpay1ct";//Bismillah99

// $dbhandle = mysql_connect($host, $user, $pawd) or die("Couldn't connect to SQL Server on $host"); 
  
// //select a database to work with
// $selected = mysql_select_db($base, $dbhandle)
//   or die("Couldn't open database $dbhandle"); 

$dbhandle = mysqli_connect($host, $user, $pawd,$base) or die("Couldn't connect to SQL Server on $host"); 
mysqli_set_charset($dbhandle, 'utf8mb4');
?>
