<?php

function geocode($address){
	$address = urlencode($address);
	$url = "https://maps.googleapis.com/maps/api/geocode/json?address=".$address."&key=AIzaSyD64knRCOQVHjMOkp86vuBO_njh_mhWHw0";
	// get the json response
    $resp_json = file_get_contents($url);
     
    // decode the json
    $resp = json_decode($resp_json, true);
	//print_r($resp);
	//print "\n";
 
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
?>