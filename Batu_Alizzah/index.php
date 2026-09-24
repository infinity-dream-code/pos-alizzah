<?php

require "include/jwt.php";

function parseHeaders( $headers )
{
    $head = array();
    foreach( $headers as $k=>$v )
    {
        $t = explode( ':', $v, 2 );
        if( isset( $t[1] ) )
            $head[ trim($t[0]) ] = trim( $t[1] );
        else
        {
            $head[] = $v;
            if( preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$v, $out ) )
                $head['reponse_code'] = intval($out[1]);
        }
    }
    return $head;
}

//=========================================
//class JWT
$transaksi = new JWT();
//SELECT MD5('FarrelGantengSekali')
$key = "27576a43cc88a0abbc5a6a509b51be9c";
$ResultArray = array();
$ResultArrayChild = array();
$decoded = $transaksi->decode($_GET['token'], $key, array('HS256'));

$decoded_array = (array) $decoded;

$METHOD = $decoded_array[METHOD];
$USERNAME = $decoded_array[USERNAME];
$PASSWORD = $decoded_array[PASSWORD];

if (!$_GET['token'] || !isset($_GET['token'])) {
    $datas = array(
        'KodeRespon' => 90,
        'PesanRespon' => 'Token Tidak Terdefinisi'
    );
    // echo no users JSON
    echo json_encode($datas);
} else {
    if (isset($METHOD)) {
        switch ($METHOD) {
            case 'LoginRequest' :
                // include db connect class
                require_once "include/config_39MY_MOBILE.php";
                //masukkan NIM/NIS dan pass saja, untuk akses di CUST dikasih 
                $PASSWORD = MD5($PASSWORD);
                $sql = "SELECT COUNT(urut) as Exist, NamaKantin as Username , case
                when kelompok_kantin = 'P1' then 'KANTIN' 
                when kelompok_kantin = 'L1' then 'LAUNDRY' 
                when kelompok_kantin = 'L2' then 'ATK' 
                when kelompok_kantin = 'L3' then 'KLINIK' 
                 end as KK FROM sm_kantin
                    WHERE username='$USERNAME' AND Password='$PASSWORD'
                    ";
                $requery = mysql_query($sql);
                $row = mysql_fetch_array($requery);
                $isExist = $row['Exist'];
                if( $isExist == '1' )
                {
                        $datas = array(
                            'Username' => $row['Username'],
                            'KK' => $row['KK'],
                            'KodeRespon' => 1
                        );
                        // echo no users JSON
                        echo json_encode($datas);
                }else{
                        $datas = array(
                        'KodeRespon' => 10,
                        'PesanRespon' => 'Akses Ditolak'
                        );
                        // echo no users JSON
                        echo json_encode($datas);
                }
                break;
            case 'PaymentBELANJAKantin' :
                // include db connect class
                require_once "include/config_39MY_MOBILE.php";

                    $PID =  $decoded_array[NOKARTU];
					$Amount =  $decoded_array[NOMINAL];
					$Username =  $decoded_array[NAMAKANTIN];
					
					$sql = "SELECT AndroidPaymentBUY('$PID','$Amount','$Username') as Result";
					$requery = mysql_query($sql);
					$row = mysql_fetch_array($requery);
					$isResult = $row['Result'];
                    $isResult = explode("|",$isResult);
                    $response = array();	
                    $CHECK = $isResult[0];
                    if( $CHECK == 'Insufficient_Balance'){
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'RESULT' => 'SALDO_TAK_MENCUKUPI',	
                            						
                        );
                    }elseif($CHECK == 'Daily_Transaction_Limit_Exceeded'){
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'RESULT' => 'TRANSAKSI_LIMIT',	
                            					
                        );
                    }elseif($CHECK == 'UNKNOWN_OR_BLOCKED_CARD'){
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'RESULT' => 'KARTU_TIDAK_TERDAFTAR',	
                            					
                        );
                    }elseif($CHECK == 'OK'){
                        $datas = array(
                            'STATUS' => 'OK',
                            'NAMA' => $isResult[1],	
                            'SALDO' => strval($isResult[2]),						
                        );

                    }else{
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'RESULT' => 'Koneksi_Error',
                            'SALDO' => '-',	
                            					
                        );
                    }
					
					
					array_push($response, $datas);
					echo json_encode($response);

            break;
            case 'PaymentBELANJAKantinPIN' :
                // include db connect class
                require_once "include/config_39MY_MOBILE.php";

                    $PID =  $decoded_array[NOKARTU];
					$Amount =  $decoded_array[NOMINAL];
					$Username =  $decoded_array[NAMAKANTIN];
                    $PIN =  $decoded_array[PIN];
					
					$sql = "SELECT AndroidPaymentBUYPIN('$PID','$Amount','$Username','$PIN') as Result";
					$requery = mysql_query($sql);
					$row = mysql_fetch_array($requery);
					$isResult = $row['Result'];
                    $isResult = explode("|",$isResult);
                    $response = array();	
                    $CHECK = $isResult[0];
                    if( $CHECK == 'Insufficient_Balance'){
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'RESULT' => 'SALDO_TAK_MENCUKUPI',	
                            						
                        );
                    }elseif($CHECK == 'Daily_Transaction_Limit_Exceeded'){
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'RESULT' => 'TRANSAKSI_LIMIT',	
                            					
                        );
                    }elseif($CHECK == 'UNKNOWN_OR_BLOCKED_CARD'){
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'RESULT' => 'KARTU_TIDAK_TERDAFTAR',	
                            					
                        );
                    }elseif($CHECK == 'OK'){
                        $datas = array(
                            'STATUS' => 'OK',
                            'NAMA' => $isResult[1],	
                            'SALDO' => strval($isResult[2]),						
                        );

                    }else{
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'RESULT' => 'Koneksi_Error',
                            'SALDO' => '-',	
                            					
                        );
                    }
					
					
					
					array_push($response, $datas);
					echo json_encode($response);

            break;
            case 'InquirySALDO' :
                // include db connect class
                require_once "include/config_39MY_MOBILE.php";

                    $PID =  $decoded_array[NOKARTU];
					
					
					$sql = "SELECT AndroidGetSaldoCard('$PID') as Result";
					$requery = mysql_query($sql);
					$row = mysql_fetch_array($requery);
					$isResult = $row['Result'];
                    $isResult = explode("|",$isResult);
                    $response = array();	
                    $CHECK = $isResult[0];
                    if( $CHECK == 'OK'){
                        $datas = array(
                            'STATUS' => 'OK',
                            'NAMA' => $isResult[3],	
                            'SALDO' => strval($isResult[2]),	
                            						
                        );
                    }else{
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'NAMA' => 'Kartu Tidak Terdaftar.',	
                            'SALDO' => '0',						
                        );
                    }
					
					
					array_push($response, $datas);
					echo json_encode($response);

            break;
            case 'LogTransaksiRequest' :
                
                // include db connect class
                require_once "include/config_39MY_MOBILE.php";
                
                $USERNAME = $decoded_array['USERNAME'];
                $sql = "SELECT * FROM v_AndroidLogTrans WHERE Teller='$USERNAME' ORDER BY urut DESC LIMIT 30 ";
                $requery = mysql_query($sql);
                $response["datas"] = array();

                while ($row = mysql_fetch_array($requery)){
                    $datas = array(
                        'NamaCust' => $row['NMCUST'],
                        'TRXDATE' => strval($row['TanggalKeluar']),
                        'KANTIN' => $row['Teller'],
                        'Nominal' => $row['BILLAM'],
                        
                    );
                    array_push($response["datas"], $datas);
                }

                echo json_encode($response);
                

            break;
            case 'RequestNewPassword' :
                // penting
                $response["datas"] = array();
                $PASSWORD = $decoded_array[PASSWORD];
                $NEWPASSWORD = $decoded_array[NEWPASSWORD];
                $NEWPASSWORD2 = $decoded_array[NEWPASSWORD2];

                require_once "include/config_39MY_MOBILE.php";
                if ($NEWPASSWORD == $NEWPASSWORD2) {
                    #$PASSWORD = MD5($PASSWORD);
                    #$NEWPASSWORD = MD5($NEWPASSWORD);
                    //$NEWPASSWORD2 = SHA1($NEWPASSWORD2);

                    $requery = mysql_query("CALL AndroidChangePassMerchant('$USERNAME', '$PASSWORD', '$NEWPASSWORD')");
                    $datas = array(
                        'KodeRespon' => 1,
                        'PesanRespon' => 'SUKSES GANTI PASSWORD'
                    );
                    // echo no users JSON
                    echo json_encode($datas);
                } else {
                    $datas = array(
                        'KodeRespon' => 0,
                        'PesanRespon' => 'PASSWORD KONFIRMASI TIDAK COCOK'
                    );
                    // echo no users JSON
                    echo json_encode($datas);
                }

                break;
        }
    } else {
        $datas = array(
            'KodeRespon' => 91,
            'PesanRespon' => 'Metode Request Tidak Benar'
        );
        // echo no users JSON
        echo json_encode($datas);
    }
}