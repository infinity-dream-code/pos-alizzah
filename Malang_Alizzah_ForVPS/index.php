<?php

require "include/jwt.php";
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

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

function safe_str($value)
{
    return trim((string)($value ?? ''));
}

function sanitize_for_json($value)
{
    if (is_array($value)) {
        $sanitized = array();
        foreach ($value as $key => $item) {
            $sanitized[$key] = sanitize_for_json($item);
        }
        return $sanitized;
    }
    if (is_string($value)) {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if ($converted !== false) {
                return $converted;
            }
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
    }
    return $value;
}

function echo_json_response($data)
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($data, $flags);
    if ($json === false) {
        $json = json_encode(sanitize_for_json($data), $flags);
    }
    if ($json === false) {
        $json = json_encode(array(
            'KodeRespon' => 99,
            'PesanRespon' => 'JSON encode error: ' . json_last_error_msg()
        ));
    }

    echo $json;
}

//=========================================
//class JWT
$transaksi = new JWT();
//SELECT MD5('FarrelGantengSekali')
$key = "4ecfd4c24aee85b4b485f9d828aa1b7d";
$ResultArray = array();
$ResultArrayChild = array();
$decoded = $transaksi->decode($_GET['token'], $key, array('HS256'));

$decoded_array = (array) $decoded;

$METHOD = $decoded_array['METHOD'];
$USERNAME = $decoded_array['USERNAME'];
$PASSWORD = $decoded_array['PASSWORD'];

if (!$_GET['token'] || !isset($_GET['token'])) {
    $datas = array(
        'KodeRespon' => 90,
        'PesanRespon' => 'Token Tidak Terdefinisi'
    );
    // echo no users JSON
    echo_json_response($datas);
} else {
    if (isset($METHOD)) {
        switch ($METHOD) {
             case 'LoginRequest' :
                // include db connect class
                require_once "include/config_39MY_MOBILE.php";
                //masukkan NIM/NIS dan pass saja, untuk akses di CUST dikasih 
                $PASSWORD = MD5($PASSWORD);
                $sql = "SELECT COUNT(urut) as Exist, NamaKantin as Username FROM sm_kantin
                    WHERE username='$USERNAME' AND Password='$PASSWORD'
                    ";
                $requery = mysqli_query($dbhandle,$sql);
                $row = mysqli_fetch_array($requery);
                $isExist = $row['Exist'];
                if( $isExist == '1' )
                {
                        $datas = array(
                            'Username' => safe_str($row['Username']),
                            'KodeRespon' => 1
                        );
                        // echo no users JSON
                        echo_json_response($datas);
                }else{
                        $datas = array(
                        'KodeRespon' => 10,
                        'PesanRespon' => 'Akses Ditolak'
                        );
                        // echo no users JSON
                        echo_json_response($datas);
                }
                break;
            case 'PaymentBELANJAKantin' :
                // include db connect class
                require_once "include/config_39MY_MOBILE.php";

                    $PID =  $decoded_array['NOKARTU'];
					$Amount =  $decoded_array['NOMINAL'];
					$Username =  $decoded_array['NAMAKANTIN'];
					
					$sql = "SELECT VPSPaymentBUY('$PID','$Amount','$Username') as Result";
					$requery = mysqli_query($dbhandle,$sql);
					$row = mysqli_fetch_array($requery,MYSQLI_BOTH);
					$isResult = $row['Result'];
                    $isResult = explode("|",$isResult);
                    $response = array();	
                    $CHECK = $isResult[0];
                    if( $CHECK == 'Insufficient_Balance'){
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'RESULT' => 'SALDO_TAK_MENCUKUPI',
                            'SALDO' => strval($isResult[1]),	
                            						
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
                            'NAMA' => safe_str($isResult[1] ?? ''),
                            'SALDO' => strval($isResult[2] ?? ''),						
                        );

                    }else{
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'RESULT' => 'Koneksi_Error',
                            'SALDO' => '-',	
                            					
                        );
                    }
					
					
					
					array_push($response, $datas);
					echo_json_response($response);

            break;

            case 'PaymentBELANJAKantinWithKeterangan' :
                // include db connect class
                require_once "include/config_39MY_MOBILE.php";

                    $PID =  $decoded_array['NOKARTU'];
					$Amount =  $decoded_array['NOMINAL'];
					$Username =  $decoded_array['NAMAKANTIN'];
                    $KET =  $decoded_array['KET'];
					
					$sql = "SELECT VPSPaymentBUY_Ket('$PID','$Amount','$Username','$KET') as Result";
					$requery = mysqli_query($dbhandle,$sql);
					$row = mysqli_fetch_array($requery,MYSQLI_BOTH);
					$isResult = $row['Result'];
                    $isResult = explode("|",$isResult);
                    $response = array();	
                    $CHECK = $isResult[0];
                    if( $CHECK == 'Insufficient_Balance'){
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'RESULT' => 'SALDO_TAK_MENCUKUPI',
                            'SALDO' => strval($isResult[1]),	
                            						
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
                            'NAMA' => safe_str($isResult[1] ?? ''),
                            'SALDO' => strval($isResult[2] ?? ''),						
                        );

                    }else{
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'RESULT' => 'Koneksi_Error',
                            'SALDO' => '-',	
                            					
                        );
                    }
					
					
					
					array_push($response, $datas);
					echo_json_response($response);

            break;
           
            case 'InquirySALDO' :
                // include db connect class
                require_once "include/config_39MY_MOBILE.php";

                    $PID =  $decoded_array['NOKARTU'];
					
					
					$sql = "SELECT VPSGetSaldoCard('$PID') as Result";
					$requery = mysqli_query($dbhandle,$sql);
					$row = mysqli_fetch_array($requery,MYSQLI_BOTH);
					$isResult = $row['Result'];
                    $isResult = explode("|",$isResult);
                    $response = array();	
                    $CHECK = $isResult[0];
                    if( $CHECK == 'OK'){
                        $datas = array(
                            'STATUS' => 'OK',
                            'NAMA' => safe_str($isResult[3] ?? ''),
                            'SALDO' => strval($isResult[2] ?? ''),	
                            						
                        );
                    }else{
                        $datas = array(
                            'STATUS' => 'NOTOK',
                            'NAMA' => 'Kartu Tidak Terdaftar.',	
                            'SALDO' => '0',						
                        );
                    }
					
					
					array_push($response, $datas);
					echo_json_response($response);

            break;
            case 'LogTransaksiRequest' :
                
                // include db connect class
                require_once "include/config_39MY_MOBILE.php";
                
                $USERNAME = $decoded_array['USERNAME'];
                $sql = "SELECT * FROM v_vpslogtrans WHERE Teller='$USERNAME' ORDER BY urut DESC LIMIT 30 ";
                $requery = mysqli_query($dbhandle,$sql);
                $response["datas"] = array();

                while ($row = mysqli_fetch_array($requery,MYSQLI_BOTH)){
                    $datas = array(
                        'NamaCust' => safe_str($row['NMCUST']),
                        'TRXDATE' => strval($row['TanggalKeluar'] ?? ''),
                        'KANTIN' => safe_str($row['Teller']),
                        'Nominal' => safe_str($row['BILLAM']),
                        'KET' => safe_str($row['KET']),
                        
                    );
                    array_push($response["datas"], $datas);
                }

                echo_json_response($response);
                

            break;
            case 'StudentRequest' :
                
                // include db connect class
                require_once "include/config_39MY_MOBILE.php";
                
               
                $sql = "SELECT 
                    NOCUST,
                    NMCUST,
                    NUM2ND,
                    STCUST,
                    CODE01,
                    DESC01,
                    CODE02,
                    DESC02,
                    CODE03,
                    DESC03,
                    CODE04,
                    DESC04,
                    CODE05,
                    DESC05,
                    TOTPAY,
                    GENUS
            
                FROM SCCTCUST ";
                $requery = mysqli_query($dbhandle,$sql);
                $response["datas"] = array();

                while ($row = mysqli_fetch_array($requery,MYSQLI_BOTH)){
                    $datas = array(
                        'NIS' => safe_str($row['NOCUST']),
                        'NamaCust' => safe_str($row['NMCUST']),
                        'NUM2ND' => safe_str($row['NUM2ND']),
                        'STCUST' => safe_str($row['STCUST']),
                        'CODE01' => safe_str($row['CODE01']),
                        'DESC01' => safe_str($row['DESC01']),
                        'CODE02' => safe_str($row['CODE02']),
                        'DESC02' => safe_str($row['DESC02']),
                        'CODE03' => safe_str($row['CODE03']),
                        'DESC03' => safe_str($row['DESC03']),
                        'CODE04' => safe_str($row['CODE04']),
                        'DESC04' => safe_str($row['DESC04']),
                        'CODE05' => safe_str($row['CODE05']),
                        'DESC05' => safe_str($row['DESC05']),
                        'TOTPAY' => safe_str($row['TOTPAY']),
                        'GENUS' => safe_str($row['GENUS']),
    
                        
                    );
                    array_push($response["datas"], $datas);
                }

                echo_json_response($response);
                

            break;
            case 'RequestNewPassword' :
                // penting
                $response["datas"] = array();
                $PASSWORD = $decoded_array['PASSWORD'];
                $NEWPASSWORD = $decoded_array['NEWPASSWORD'];
                $NEWPASSWORD2 = $decoded_array['NEWPASSWORD2'];

                require_once "include/config_39MY_MOBILE.php";
                if ($NEWPASSWORD == $NEWPASSWORD2) {
                    #$PASSWORD = MD5($PASSWORD);
                    #$NEWPASSWORD = MD5($NEWPASSWORD);
                    //$NEWPASSWORD2 = SHA1($NEWPASSWORD2);

                    $requery = mysqli_query($dbhandle,"CALL AndroidChangePassMerchant('$USERNAME', '$PASSWORD', '$NEWPASSWORD')");
                    $datas = array(
                        'KodeRespon' => 1,
                        'PesanRespon' => 'SUKSES GANTI PASSWORD'
                    );
                    // echo no users JSON
                    echo_json_response($datas);
                } else {
                    $datas = array(
                        'KodeRespon' => 0,
                        'PesanRespon' => 'PASSWORD KONFIRMASI TIDAK COCOK'
                    );
                    // echo no users JSON
                    echo_json_response($datas);
                }

                break;
        }
    } else {
        $datas = array(
            'KodeRespon' => 91,
            'PesanRespon' => 'Metode Request Tidak Benar'
        );
        // echo no users JSON
        echo_json_response($datas);
    }
}