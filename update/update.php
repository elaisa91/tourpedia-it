<?php 
ini_set('MAX_EXECUTION_TIME', -1);
require_once 'libraries/excel/reader.php';
require 'libraries/simple_html_dom.php';
ini_set('auto_detect_line_endings', TRUE);
$date = date("d/m/y H:i:s");
$ini_array = parse_ini_file("config.ini", true);
$connection = new MongoClient('mongodb://localhost:27017');
$dbname = $connection->selectDB('Strutture');
$nuovo = $dbname->NUOVO;
$vecchio = $dbname->VECCHIO;
$temp = $dbname->TEMP;
$log = $dbname->LOG;
$document["date"] = $date;
$log->save($document);
$drop = $temp->drop();
CopiaCollezione($vecchio, $temp);
$drop = $vecchio->drop();
CopiaCollezione($nuovo, $vecchio);
$drop = $nuovo->drop();

//Abruzzo 			*NON DISPONIBILE*
Basilicata($date, $ini_array, $nuovo, $vecchio);
//Calabria 			*NON DISPONIBILE*
//Campania			*NON DISPONIBILE*
EmiliaRomagna($date, $ini_array, $nuovo, $vecchio);
Friuli($date, $ini_array, $nuovo, $vecchio);
//Lazio				*NON DISPONIBILE*
Liguria($date, $ini_array, $nuovo, $vecchio);	
Lombardia($date, $ini_array, $nuovo, $vecchio);
Marche($date, $ini_array, $nuovo, $vecchio);
//Molise 			*NON DISPONIBILE*
Piemonte($date, $ini_array, $nuovo, $vecchio);
Puglia($date, $ini_array, $nuovo, $vecchio);
//Sardegna			*FILE PDF*
//Sicilia			*NON SCARICABILE*
Toscana($date, $ini_array, $nuovo, $vecchio);
Trentino($date, $ini_array, $nuovo, $vecchio);
Umbria($date, $ini_array, $nuovo, $vecchio);
//VdAosta			*NON DISPONIBILE*
Veneto($date, $ini_array, $nuovo, $vecchio);

function geocode($address){
	$address = urlencode($address);
	$url = "https://maps.googleapis.com/maps/api/geocode/json?address=".$address."&key=AIzaSyD64knRCOQVHjMOkp86vuBO_njh_mhWHw0";
	// get the json response
    $resp_json = file_get_contents($url);
     
    // decode the json
    $resp = json_decode($resp_json, true);
	print_r($resp);
	print "\n";
 
    // response status will be 'OK', if able to geocode given address 
    if($resp['status']=='OK'){
 
        // get the important data
        $lati = $resp['results'][0]['geometry']['location']['lat'];
        $longi = $resp['results'][0]['geometry']['location']['lng'];
         
        // verify if data is complete
        if($lati && $longi){
         
            // put the data in the array
            $data_arr = array();            
             
            array_push(
                $data_arr, 
                    $lati, 
                    $longi 
                );
             
            return $data_arr;
             
        }else{
            return false;
        }
         
    }else{
        return false;
    }	
}

function TrovaCoordinate($document, $vecchio){
	$product_array = array(
		'address' => $document['address']
		);
	if($found = $vecchio->findOne($product_array)){
		if($found['latitude']!==NULL){
			$document['latitude']=$found['latitude'];
			$document['longitude']=$found['longitude'];
		}
		else{
			$document['latitude']=NULL;
			$document['longitude']=NULL;
		}
	}
	else{
		$add=$document['address'].", ".$document['city'];
		if($geo=geocode($add)){
			$document['latitude']=round(floatval($geo[0]),6);
			$document['longitude']=round(floatval($geo[1]),6);
			$document['enrichment']="latitude, longitude";
		}
		else{
			$document['latitude']=NULL;
			$document['longitude']=NULL;
		}
	}
	return $document;
}

function CopiaCollezione($collPartenza, $collArrivo) {
	$cursor = $collPartenza->find();
	$num_docs = $cursor->count();
	if ($num_docs>0) {
		foreach ($cursor as $obj)
		{
			$collArrivo->save($obj);
		}
	}
}

function UpdateLog($regione, $date, $row, $lastmodified){
	$connection = new MongoClient('mongodb://localhost:27017');
	$dbname = $connection->selectDB('Strutture');
	$log = $dbname->LOG;
	$product_array = array(
		'date' => $date
		);
	$document = $log->findOne($product_array);
	if($row==NULL){
		$giorno = date("d");
		$giorno = $giorno-1;
		if ($giorno<10){
			$giorno = "0".$giorno;
		}
		$ieri = date("/m/y");
		// INSERIRE ORARIO DI ESECUZIONE DEL FILE
		$ieri = $giorno.$ieri." 03:00:00";
		$array_ieri = array(
		'date' => $ieri
		);
		$vecchiolog = $log->findOne($array_ieri);
		$vecchiadata = $vecchiolog[$regione." last modify"];
		$document[$regione." last modify"] = $vecchiadata;
	}
	else{
		if($regione!=="Trentino" AND $regione!=="Liguria" AND $regione!=="Umbria"){
			$lastmodified = substr($lastmodified, strlen("Last-Modified: "));
		}
		$document[$regione." last modify"] = $lastmodified;
	}
	$document[$regione." structures"] = $row;
	$log->save($document);
}

function Basilicata($date, $ini_array, $nuovo, $vecchio){
	$lastmodified = NULL;
	$ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $ini_array["Basilicata"]["url"]); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	// dico al server che sono un browser
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)');
    if (($output = curl_exec($ch))!=FALSE){ 
		$tmpFileName = 'tmpBasilicata.xls';
		// close curl resource to free up system resources 
		$metadata = stream_get_meta_data(fopen($ini_array["Basilicata"]["url"],"r"));
		$lastmodified = $metadata["wrapper_data"][3];
		curl_close($ch);
		$handle = fopen($tmpFileName, 'w');
		fwrite($handle, $output);
		$data = new Spreadsheet_Excel_Reader();
		$data->setOutputEncoding('CPa25a');
		$data->read($tmpFileName);
		$row=0;
		for($i=2; $i<$data->sheets[0]['numRows']; $i++){
				$row++;
				if(isset($data->sheets[0]['cells'][$i][2])) {$document['name']=utf8_encode($data->sheets[0]['cells'][$i][2]);} else {$document['name']=NULL;}
				if(isset($data->sheets[0]['cells'][$i][3])) {$document['description']=utf8_encode($data->sheets[0]['cells'][$i][3]);} else {$document['description']=NULL;}
				if(isset($data->sheets[0]['cells'][$i][5])) {$document['address']=utf8_encode($data->sheets[0]['cells'][$i][5]);} else {continue;}
				if(isset($data->sheets[0]['cells'][$i][4])) {$document['number of stars']=utf8_encode($data->sheets[0]['cells'][$i][4]);} else {$document['number of stars']=NULL;}
				if(isset($data->sheets[0]['cells'][$i][7])) {$document['telephone']=utf8_encode($data->sheets[0]['cells'][$i][7]);} else {$document['telephone']=NULL;}
				if(isset($data->sheets[0]['cells'][$i][8])) {$document['cellular phone']=utf8_encode($data->sheets[0]['cells'][$i][8]);} else {$document['cellular phone']=NULL;}
				if(isset($data->sheets[0]['cells'][$i][9])) {$document['fax']=utf8_encode($data->sheets[0]['cells'][$i][9]);} else {$document['fax']=NULL;}
				if(isset($data->sheets[0]['cells'][$i][10])) {$document['web site']=utf8_encode($data->sheets[0]['cells'][$i][10]);} else {$document['web site']=NULL;}
				if(isset($data->sheets[0]['cells'][$i][11])) {$document['email']=utf8_encode($data->sheets[0]['cells'][$i][11]);} else {$document['email']=NULL;}
				if(isset($data->sheets[0]['cells'][$i][13])) {$document['beds']=intval($data->sheets[0]['cells'][$i][13]);} else {$document['beds']=NULL;}
				if(isset($data->sheets[0]['cells'][$i][6])) {
					$pcp=explode(" ", $data->sheets[0]['cells'][$i][6], 2);
					$postal=$pcp[0];
					$cp=explode("(", $pcp[1]);
					$city = substr($cp[0], 1);
					if($cp[1]=="Mt)" OR $cp[1]=="MT)") $prov="MT";
					if($cp[1]=="Pz)" OR $cp[1]=="PZ)") $prov="PZ";
				} 
				else {
					$postal=NULL;
					$city=NULL;
					$prov=NULL;
				}
				$document['postal-code']=$postal;
				$document['city']=$city;
				$document['province']=$prov;
				$document['_id']='BAS'.$row;
				$document['region'] = 'Basilicata';
				$document=TrovaCoordinate($document, $vecchio);
				$nuovo->save($document);
		}
		print "BASILICATA: ".$row."\n";
	}
	else{
		$connection = new MongoClient('mongodb://localhost:27017');
		$cursor = $vecchio->find();
		$row = 0;
		foreach ($cursor as $obj){
			if($obj['region']=='Basilicata'){
				$nuovo->save($obj);
				$row++;
			}
		}
		print "BASILICATA: Problems reading url. Recovered ".$row." records from the old database\n";
		$row = NULL;
	}
	UpdateLog("Basilicata", $date, $row, $lastmodified);
	unlink($tmpFileName);
}

function EmiliaRomagna($date, $ini_array, $nuovo, $vecchio){
	$zip = new ZipArchive;
	$tmpZipFileName = "Tmpfile.zip";
	$lastmodified = NULL;
	if(file_put_contents($tmpZipFileName, fopen($ini_array["EmiliaRomagna"]["url"], 'r'))){
		if($zip->open($tmpZipFileName)!==FALSE){
			$metadata = stream_get_meta_data(fopen($ini_array["EmiliaRomagna"]["url"],"r"));
			$lastmodified = $metadata["wrapper_data"][7];
			$row=-1;
			for ($i=0; $i<9; $i++){
				$filename = $zip->getNameIndex($i);
				$zip->extractTo('.', $filename);
				$file=fopen($filename, "r");
				while(($arr=fgetcsv($file,10000,";"))!==FALSE){
					$row++;
					if($row==0)continue;
					if($arr[6]=='denominazione')continue;
					$document['_id']="EMI".$row;
					$document['name']=$arr[6];
					$document['description']=$arr[5];
					$document['address']=$arr[8];
					$document['city']=$arr[2];
					$document['province']=$arr[1];
					$document['locality']=$arr[3];
					$document['region']='Emilia-Romagna';
					$document['postal-code']=intval($arr[9]);
					$document['number of stars']=$arr[7];
					$document['email']=$arr[14];
					$document['web site']=$arr[13];
					$document['telephone']=$arr[10];
					$document['telephone2']=$arr[11];
					$document['fax']=$arr[12];
					$document['latitude']=round(floatval($arr[16]),6);
					$document['longitude']=round(floatval($arr[15]),6);
					$nuovo->save($document);
				}
			}
			print "EMILIA-ROMAGNA: ".$row."\n";
		}
	 }
	else{
		$connection = new MongoClient('mongodb://localhost:27017');
		$cursor = $vecchio->find();
		$row = 0;
		foreach ($cursor as $obj){
			if($obj['region']=='Emilia-Romagna'){
				$nuovo->save($obj);
				$row++;
			}
		}
		print "EMILIA-ROMAGNA: Problems reading url. Recovered ".$row." records from the old database\n";
		$row = NULL;
	}
	UpdateLog('Emilia-Romagna', $date, $row, $lastmodified);
	// cancello i file temporanei
	unlink($tmpZipFileName);
	array_map('unlink', glob( "*.csv"));
}

function Friuli($date, $ini_array, $nuovo, $vecchio){
	if(($handle=fopen($ini_array["Friuli"]["url"], "r"))!==FALSE){
		$metadata = stream_get_meta_data($handle);
		$lastmodified = $metadata["wrapper_data"][8];
		if (preg_match('/Last-Modified:(.*?)/i', $lastmodified, $matches))
		$row=-1;
		$prov=NULL;
		while(($arr=fgetcsv($handle,10000,","))!==FALSE){
			$row++;
			if($row==0)continue;
			switch($arr[2]){
				case "GORIZIA":
					$prov = "GO";
					break;
				case "UDINE":
					$prov = "UD";
					break;
				case "PORDENONE":
					$prov = "PN";
					break;
				case "TRIESTE":
					$prov = "TS";
					break;
			}
			$document['_id']=				"FRI".$row;
			$document['name']=				$arr[6];
			$document['description']=		$arr[1];
			$document['address']=			$arr[9].$arr[10];
			$document['city']=				$arr[3];
			$document['province']=			$prov;
			$document['locality']=			$arr[7];
			$document['hamlet']=			$arr[8];
			$document['region']=			'Friuli-Venezia Giulia';
			$document['postal-code']=		intval($arr[10]);
			$document['number of stars']=	$arr[4];
			$document['email']=				$arr[14];
			$document['web site']=			$arr[15];
			$document['telephone']=			$arr[11];
			$document['fax']=				$arr[13];
			$document['cellular phone']=	$arr[12];
			$document['rooms']=				intval($arr[16]);
			$document['beds']=				intval($arr[17]);
			$document['toilets']=			intval($arr[18]);
			$document=TrovaCoordinate($document, $vecchio);
			$nuovo->save($document);
		}
		print "FRIULI-VENEZIA GIULIA: ".$row."\n";
	}
	else{
		$connection = new MongoClient('mongodb://localhost:27017');
		$cursor = $vecchio->find();
		$row = 0;
		foreach ($cursor as $obj){
			if($obj['region']=='Friuli-Venezia Giulia'){
				$nuovo->save($obj);
				$row++;
			}
		}
		print "FRIULI-VENEZIA GIULIA: Problems reading url. Recovered ".$row." records from the old database\n";
		$row = NULL;
	}
	UpdateLog('Friuli-Venezia Giulia', $date, $row, $lastmodified);
}

function Liguria($date, $ini_array, $nuovo, $vecchio){
	$ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $ini_array["Liguria"]["url"]); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	// dico al server che sono un browser
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)');
    if (($output = curl_exec($ch))!=FALSE){ 
		$tmpFileName = 'tmpLiguria.csv';
		// close curl resource to free up system resources 
		curl_close($ch);
		$handle = fopen($tmpFileName, 'w');
		fwrite($handle, $output);
		fclose($handle);
		if(($handle=fopen($tmpFileName, "r"))!==FALSE){
			$metadata = stream_get_meta_data($handle);
			$row=-1;
			while(($arr=fgetcsv($handle,10000,";"))!==FALSE){
				$row++;
				if($row==0)continue;
				switch($arr[3]){
					case "Genova":
						$prov = "GE";
						break;
					case "Imperia":
						$prov = "IM";
						break;
					case "La Spezia":
						$prov = "SP";
						break;
					case "Savona":
						$prov = "SV";
						break;
				}
				$document['_id']=				"LIG".$row;
				$document['name']=				$arr[6];
				$document['description']=		$arr[2];
				$document['address']=			$arr[7];
				$document['city']=				$arr[4];
				$document['province']=			$prov;
				$document['locality']=			$arr[9];
				$document['hamlet']=			$arr[10];
				$document['region']=			'Liguria';
				$document['postal-code']=		intval($arr[8]);
				$document['number of stars']=	$arr[5];
				$document['email']=				$arr[13];
				$document['web site']=			$arr[14];
				$document['telephone']=			$arr[11];
				$document['fax']=				$arr[12];
				$document['rooms']=				intval($arr[15]);
				$document['beds']=				intval($arr[16]);
				$document=TrovaCoordinate($document, $vecchio);
				$nuovo->save($document);
			}
			print "LIGURIA: ".$row."\n";
		}
	}
	else{
		$connection = new MongoClient('mongodb://localhost:27017');
		$cursor = $vecchio->find();
		$row = 0;
		foreach ($cursor as $obj){
			if($obj['region']=='Liguria'){
				$nuovo->save($obj);
				$row++;
			}
		}
		print "LIGURIA: Problems reading url. Recovered ".$row." records from the old database\n";
		$row = NULL;
	}
	$ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, "http://www.regione.liguria.it/sep-servizi-online/catalogo-servizi-online/opendata/item/6883-strutture-ricettive-alberghiere.html"); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	// dico al server che sono un browser
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)');
    if (($output = curl_exec($ch))!=FALSE){ 
		$tmpFileName = 'tmpLiguria.html';
		// close curl resource to free up system resources 
		curl_close($ch);
		$handle = fopen($tmpFileName, 'w');
		fwrite($handle, $output);
		fclose($handle);
		$html = file_get_html('tmpLiguria.html');
		$lastmodified=$html->find('div[class=flexi value field_data_ultimo_aggiornamento]',0);
		$lastmodified=substr($lastmodified,strlen("<div class='flexi value field_data_ultimo_aggiornamento'>"),-6);
	}
	UpdateLog('Liguria', $date, $row, $lastmodified);
	unlink($tmpFileName);
}

function Lombardia($date, $ini_array, $nuovo, $vecchio){
	if(($handle=fopen($ini_array["Lombardia"]["url"], "r"))!==FALSE){
		$metadata = stream_get_meta_data($handle);
		$lastmodified = $metadata["wrapper_data"][8];
		$row=-1;
		while(($arr=fgetcsv($handle,10000,","))!==FALSE){
			$row++;
			if($row==0)continue;
			$document['_id']=				"LOM".$row;
			$document['name']=				utf8_encode($arr[3]);
			$document['category']=			utf8_encode($arr[4]);
			$document['description']=		utf8_encode($arr[5]);
			$document['address']=			utf8_encode($arr[6]);
			$document['city']=				utf8_encode($arr[2]);
			$document['province']=			utf8_encode($arr[1]);
			$document['locality']=			utf8_encode($arr[9]);
			$document['hamlet']=			utf8_encode($arr[8]);
			$document['region']=			'Lombardia';
			$document['postal-code']=		intval($arr[7]);
			$document['email']=				utf8_encode($arr[10]);
			$document['web site']=			utf8_encode($arr[13]);
			$document['telephone']=			$arr[11];
			$document['fax']=				$arr[12];
			$document['rooms']=				intval($arr[14]);
			$document['suites']=			intval($arr[15]);
			$document['beds']=				intval($arr[16]);
			$document['latitude']=			round(floatval($arr[32]),6);
			$document['longitude']=			round(floatval($arr[33]),6);
			$nuovo->save($document);
		}
		print "LOMBARDIA: ".$row."\n";
	}
	else{
		$connection = new MongoClient('mongodb://localhost:27017');
		$cursor = $vecchio->find();
		$row = 0;
		foreach ($cursor as $obj){
			if($obj['region']=='Lombardia'){
				$nuovo->save($obj);
				$row++;
			}
		}
		print "LOMBARDIA: Problems reading url. Recovered ".$row." records from the old database\n";
		$row = NULL;
	}
	UpdateLog('Lombardia', $date, $row, $lastmodified);
}

function Marche($date, $ini_array, $nuovo, $vecchio){
	$arr_tot=array();
	if(($handle=fopen($ini_array["Marche"]["url"], "r"))!==FALSE){
		$metadata = stream_get_meta_data($handle);
		$lastmodified = $metadata["wrapper_data"][10];
		$row=-1;
		while(($arr=fgetcsv($handle,10000,";"))!==FALSE){
			$row++;
			if($row==0)continue;
			if(isset($arr[3])==FALSE){
				$row--;
				continue;
			}
			$document['_id']=				"MAR".$row;
			$document['name']=				utf8_encode($arr[3]);
			$document['description']=		utf8_encode($arr[1]);
			$document['address']=			utf8_encode($arr[7]);
			$document['city']=				utf8_encode($arr[9]);
			$document['locality']=			utf8_encode($arr[10]);
			$document['region']=			'Marche';
			$document['postal-code']=		intval($arr[6]);
			$document['email']=				utf8_encode($arr[15]);
			$document['web site']=			utf8_encode($arr[14]);
			$document['telephone']=			$arr[11];
			$document['cellular phone']=	$arr[13];
			$document['fax']=				$arr[12];
			$document['latitude']=			round(floatval($arr[17]),6);
			$document['longitude']=			round(floatval($arr[16]),6);
			$nuovo->save($document);
		}
		print "MARCHE: ".$row."\n";
	}
	else{
		$connection = new MongoClient('mongodb://localhost:27017');
		$cursor = $vecchio->find();
		$row = 0;
		foreach ($cursor as $obj){
			if($obj['region']=='Marche'){
				$nuovo->save($obj);
				$row++;
			}
		}
		print "MARCHE: Problems reading url. Recovered ".$row." records from the old database\n";
		$row = NULL;
	}
	UpdateLog('Marche', $date, $row, $lastmodified);
}

function Piemonte($date, $ini_array, $nuovo, $vecchio){
	$ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $ini_array["Piemonte"]["url"]); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $output = curl_exec($ch); 
    // close curl resource to free up system resources 
    curl_close($ch);
	if(!empty($output)){
		$row=-1;
		$arr=explode('";"', $output);
		for($i=18; $i<count($arr)-1; $i=$i+18){
			$row++;
			if($row==0)continue;
			switch($arr[$i+1]){
				case "ALESSANDRIA":
					$prov = "AL";
					break;
				case "ASTI":
					$prov = "AT";
					break;
				case "BIELLA":
					$prov = "BI";
					break;
				case "CUNEO":
					$prov = "CN";
					break;
				case "TORINO":
					$prov = "TO";
					break;
				case "VERBANO-CUSIO-OSSOLA":
					$prov = "VB";
					break;
				case "VERCELLI":
					$prov = "VC";
					break;
			}
			$document['_id']=				"PIE".$row;
			$document['name']=				$arr[$i+2];
			$document['description']=		$arr[$i+7];
			$document['address']=			$arr[$i+3];
			$document['city']=				$arr[$i+5];
			$document['province']=			$prov;
			$document['region']=			'Piemonte';
			$document['postal-code']=		intval($arr[$i+4]);
			$document['number of stars']=	$arr[$i+8];
			$document['email']=				$arr[$i+11];
			$document['telephone']=			$arr[$i+9];
			$document['fax']=				$arr[$i+10];
			$document['rooms']=				intval($arr[$i+13]);
			$document['beds']=				intval($arr[$i+14]);
			$document['toilets']=			intval($arr[$i+15]);
			$document=TrovaCoordinate($document, $vecchio);
			$nuovo->save($document);
		}
		print "PIEMONTE: ".$row."\n";
	}
	else{
		$connection = new MongoClient('mongodb://localhost:27017');
		$cursor = $vecchio->find();
		$row = 0;
		foreach ($cursor as $obj){
			if($obj['region']=='Piemonte'){
				$nuovo->save($obj);
				$row++;
			}	
		}
		print "PIEMONTE: Problems reading url. Recovered ".$row." records from the old database\n";
		$row = NULL;
	}
	$html = file_get_html('http://www.dati.piemonte.it/catalogodati/dato/100966-.html');
	$lastmodified=$html->find('table[class=tabella_item]',1)->find('td',1);
	$lastmodified=substr($lastmodified,80,-10);
	UpdateLog('Piemonte', $date, $row, $lastmodified);
}

function Puglia($date, $ini_array, $nuovo, $vecchio){
	if(($handle=fopen($ini_array["Puglia"]["url"], "r"))!==FALSE){
		$metadata = stream_get_meta_data($handle);
		$lastmodified = $metadata["wrapper_data"][11];
		$row=-1;
		while(($arr=fgetcsv($handle,10000,","))!==FALSE){
			$row++;
			if($row==0)continue;
			switch($arr[13]){
				case "Bari":
					$prov = "BA";
					break;
				case "Barletta-Andria-Trani":
					$prov = "BT";
					break;
				case "Brindisi":
					$prov = "BR";
					break;
				case "Foggia":
					$prov = "FG";
					break;
				case "Lecce":
					$prov = "LE";
					break;
				case "Taranto":
					$prov = "TA";
					break;
			}
			$document['_id']=				"PUG".$row;
			$document['name']=				$arr[2];
			$document['description']=		$arr[3];
			$document['address']=			$arr[8];
			$document['city']=				$arr[12];
			$document['province']=			$arr[13];
			$document['hamlet']=			$arr[9];
			$document['region']=			'Puglia';
			$document['postal-code']=		intval($arr[10]);
			$document['number of stars']=	$arr[4];
			$document['email']=				$arr[19];
			$document['web site']=			$arr[18];
			$document['telephone']=			$arr[16];
			$document['fax']=				$arr[17];
			$document['rooms']=				intval($arr[5]);
			$document['beds']=				intval($arr[7]);
			$document['toilets']=			intval($arr[6]);
			$document['latitude']=			round(floatval($arr[14]),6);
			$document['longitude']=			round(floatval($arr[15]),6);
			$nuovo->save($document);
		}
		print "PUGLIA: ".$row."\n";
	}
	else{
		$connection = new MongoClient('mongodb://localhost:27017');
		$cursor = $vecchio->find();
		$row = 0;
		foreach ($cursor as $obj){
			if($obj['region']=='Puglia'){
				$nuovo->save($obj);
				$row++;
			}
		}
		print "PUGLIA: Problems reading url. Recovered ".$row." records from the old database\n";
		$row = NULL;
	}
	UpdateLog('Puglia', $date, $row, $lastmodified);
}

function Toscana($date, $ini_array, $nuovo, $vecchio){
	if(($handle=fopen($ini_array["Toscana"]["url"], "r"))!==FALSE){
		$metadata = stream_get_meta_data($handle);
		$lastmodified = $metadata["wrapper_data"][9];
		$row=-1;
		while(($arr=fgetcsv($handle,10000,"|"))!==FALSE){
			$row++;
			if($row==0)continue;
			$document['_id']=				"TOS".$row;
			$document['name']=				utf8_encode($arr[3]);
			$document['description']=		utf8_encode($arr[2]);
			$document['address']=			utf8_encode($arr[4]);
			$document['city']=				utf8_encode($arr[6]);
			$document['province']=			utf8_encode($arr[7]);
			$document['region']=			'Toscana';
			$document['postal-code']=		intval($arr[5]);
			$document['number of stars']=	utf8_encode($arr[8]);
			$document['email']=				utf8_encode($arr[9]);
			$document['web site']=			utf8_encode($arr[10]);
			$document['telephone']=			utf8_encode($arr[13]);
			$document['latitude']=			round(floatval($arr[11]),6);
			$document['longitude']=			round(floatval($arr[12]),6);
			$nuovo->save($document);
		}
		print "TOSCANA: ".$row."\n";
	}
	else{
		$connection = new MongoClient('mongodb://localhost:27017');
		$cursor = $vecchio->find();
		$row = 0;
		foreach ($cursor as $obj){
			if($obj['region']=='Toscana'){
				$nuovo->save($obj);
				$row++;
			}
		}
		print "TOSCANA: Problems reading url. Recovered ".$row." records from the old database\n";
		$row = NULL;
	}
	UpdateLog('Toscana', $date, $row, $lastmodified);
}

function Trentino($date, $ini_array, $nuovo, $vecchio){
	if ($xml = simplexml_load_file($ini_array["Trentino"]["url"])){
		$lastmodified = (string)($xml->attributes()->{'data-inizio-validita'});
		$row=0;
		foreach($xml->{'prezzi-localita-turistica'} as $strut){
			$row++;
			$document['_id']				 =	"TRE".$row;
			$document['name']            = (string)($strut[0]->attributes()->denominazione);
			$document['description']     = (string)($strut[0]->{'prezzi-localita'}->{'prezzi-albergo'}->attributes()->{'tipologia-alberghiera'}); 
			$document['address']         = (string)($strut[0]->{'prezzi-localita'}->{'prezzi-albergo'}->{'prezzi-saa'}->attributes()->indirizzo);
			$document['city']            = (string)($strut[0]->{'prezzi-localita'}->{'prezzi-albergo'}->{'prezzi-saa'}->attributes()->comune);
			$document['hamlet']          = (string)($strut[0]->{'prezzi-localita'}->{'prezzi-albergo'}->{'prezzi-saa'}->attributes()->frazione);
			$document['province']        = 'TN';
			$document['region']          = 'Trentino';
			$document['postal-code']     = (string)($strut[0]->{'prezzi-localita'}->attributes()->cap);
			$document['number of stars'] = (string)($strut[0]->{'prezzi-localita'}->{'prezzi-albergo'}->{'prezzi-saa'}->attributes()->{'livello-classifica'});
			$document['email']           = (string)($strut[0]->{'prezzi-localita'}->{'prezzi-albergo'}->attributes()->{'recapito-email'}); 
			$document['web site']        = (string)($strut[0]->{'prezzi-localita'}->{'prezzi-albergo'}->attributes()->{'recapito-www'});
			$document['telephone']       = (string)($strut[0]->{'prezzi-localita'}->{'prezzi-albergo'}->attributes()->{'recapito-telefono'});
			$document['fax']             = (string)($strut[0]->{'prezzi-localita'}->{'prezzi-albergo'}->attributes()->{'recapito-fax'});
			$document['rooms']           = (string)($strut[0]->{'prezzi-localita'}->{'prezzi-albergo'}->attributes()->{'numero-unita'});
			$document['beds']            = (string)($strut[0]->{'prezzi-localita'}->{'prezzi-albergo'}->attributes()->{'numero-posti-letto'});
			$document=TrovaCoordinate($document, $vecchio);
			$nuovo->save($document);
		}
		print "TRENTINO: ".$row."\n";
	}
	else{
		$connection = new MongoClient('mongodb://localhost:27017');
		$cursor = $vecchio->find();
		$row = 0;
		foreach ($cursor as $obj){
			if($obj['region']=='Trentino'){
				$nuovo->save($obj);
				$row++;
			}
		}
		print "TRENTINO: Problems reading url. Recovered ".$row." records from the old database\n";
		$row = NULL;
	}
	UpdateLog('Trentino', $date, $row, $lastmodified);
}

function Umbria($date, $ini_array, $nuovo, $vecchio){
	if(($handle=fopen($ini_array["Umbria"]["url"], "r"))!==FALSE){
		$metadata = stream_get_meta_data($handle);
		$row=-1;
		while(($arr=fgetcsv($handle,10000,","))!==FALSE){
			$row++;
			if($row==0)continue;
			$document['_id']=				"UMB".$row;
			$document['name']=				$arr[5];
			$document['description']=		$arr[6];
			$document['address']=			$arr[8];
			$document['city']=				$arr[4];
			$document['province']=			$arr[11];
			$document['hamlet']=			$arr[9];
			$document['region']=			'Umbria';
			$document['postal-code']=		intval($arr[10]);
			$document['number of stars']=	$arr[7];
			$document['email']=				$arr[16];
			$document['web site']=			$arr[15];
			$document['telephone']=			$arr[12];
			$document['telephone2']=		$arr[13];
			$document['fax']=				$arr[14];
			$document['rooms']=				intval($arr[19]);
			$document['beds']=				intval($arr[20]);
			$document['toilets']=			intval($arr[21]);
			$document=TrovaCoordinate($document, $vecchio);
			$nuovo->save($document);
		}
		print "UMBRIA: ".$row."\n";
	}
	else{
		$connection = new MongoClient('mongodb://localhost:27017');
		$cursor = $vecchio->find();
		$row = 0;
		foreach ($cursor as $obj){
			if($obj['region']=='Umbria'){
				$nuovo->save($obj);
				$row++;
			}
		}
		print "UMBRIA: Problems reading url. Recovered ".$row." records from the old database\n";
		$row = NULL;
	}
	$html = file_get_html('http://dati.umbria.it/dataset/strutture-ricettive/resource/062d7bd6-f9c6-424e-9003-0b7cb3744cab');
	$lastmodified=$html->find('table',0)->find('td',0);
	$lastmodified=substr($lastmodified,4,-5);
	UpdateLog('Umbria', $date, $row, $lastmodified);
}

function Veneto($date, $ini_array, $nuovo, $vecchio){
	if(($handle=fopen($ini_array["Veneto"]["url"], "r"))!==FALSE){
		$metadata = stream_get_meta_data($handle);
		$lastmodified = $metadata["wrapper_data"][3];
		$row=-1;
		$counter_geo=0;
		while(($arr=fgetcsv($handle,10000,","))!==FALSE){
			$row++;
			if($row==0)continue;
			switch($arr[0]){
				case "BELLUNO":
					$prov = "BL";
					break;
				case "PADOVA":
					$prov = "PD";
					break;
				case "ROVIGO":
					$prov = "RO";
					break;
				case "TREVISO":
					$prov = "TV";
					break;
				case "VENEZIA":
					$prov = "VE";
					break;
				case "VERONA":
					$prov = "VE";
					break;
				case "VICENZA":
					$prov = "VI";
					break;
			}
			$document['_id']=				"VEN".$row;
			$document['name']=				$arr[7];
			$document['description']=		$arr[3];
			$document['address']=			$arr[8]." ".$arr[9];
			$document['city']=				$arr[1];
			$document['province']=			$prov;
			$document['locality']=			$arr[2];
			$document['region']=			'Veneto';
			$document['postal-code']=		intval($arr[11]);
			$document['number of stars']=	$arr[6];
			$document['email']=				$arr[14];
			$document['web site']=			$arr[15];
			$document['telephone']=			$arr[12];
			$document['fax']=				$arr[13];
			$document=TrovaCoordinate($document, $vecchio);
			$nuovo->save($document);
		}
		print "VENETO: ".$row."\n";
	}
	else{
		$connection = new MongoClient('mongodb://localhost:27017');
		$cursor = $vecchio->find();
		$row = 0;
		foreach ($cursor as $obj){
			if($obj['region']=='Veneto'){
				$nuovo->save($obj);
				$row++;
			}
		}
		print "VENETO: Problems reading url. Recovered ".$row." records from the old database\n";
		$row = NULL;
	}
	UpdateLog('Veneto', $date, $row, $lastmodified);
}
?> 