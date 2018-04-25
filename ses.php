<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
require 'aws-autoloader.php';
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Aws\Sns\Exception\InvalidSnsMessageException;

// Instantiate the Message and Validator
$message = Message::fromRawPostData();
$validator = new MessageValidator();

// Validate the message and log errors if invalid.
try {
   $validator->validate($message);
} catch (InvalidSnsMessageException $e) {
   // Pretend we're not here if the message is invalid.
   http_response_code(404);
   error_log('SNS Message Validation Error: ' . $e->getMessage());
   die();
}

// Check the type of the message and handle the subscription.
if ($message['Type'] === 'SubscriptionConfirmation') {
   // Confirm the subscription by sending a GET request to the SubscribeURL
   file_get_contents($message['SubscribeURL']);
   die();
}

//MySql Creds
$servername = "localhost";
$username = ""; //username
$password = ""; //password
$dbname = ""; //dbname

//Hubspot configuration
$hubspot_app_eventid = 123; // Hubspot event ID

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

$body = json_decode($message['Message']);
$recipient_email = $body->mail->destination[0];
$timestamp = $body->mail->timestamp;
$campaign_subject = $body->mail->headers[4]->value;
$eventTypeSES = $body->eventType;

if (strpos($recipient_email , '<') !== FALSE)
{
$recipient_email = get_email_from_rfc_email($body->mail->destination[0]);
}
else
{
$recipient_email = $body->mail->destination[0];
}

function get_email_from_rfc_email($rfc_email_string) {
    // extract parts between the two parentheses
    $mailAddress = preg_match('/(?:<)(.+)(?:>)$/', $rfc_email_string, $matches);
    return $matches[1];
}


//if ($message['Type'] === 'Notification') {
//}

$query = "SELECT * FROM token";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$refresh_token=$row['refresh_token'];
$authorization="Authorization: Bearer ";
$authorization.=$row['access_token'];

$myObj = (object)[];
//$myObj->id = $timestamp;
$myObj->id = time().'-'.mt_rand();
$myObj->email = $recipient_email;
$myObj->eventTypeId = $hubspot_app_eventid; //this is hubpot default event app id
$myObj->eventType = $eventTypeSES;
$myObj->subject = $campaign_subject;

$data_json = json_encode($myObj);

//$testcontact = contactExists($recipient_email,$authorization);

//if($testcontact  == false){
//die();
//}
$response = set_timeline($data_json,$authorization);
if($response == false) {
$response_refresh_token = refresh_token($refresh_token);
$result2 = mysqli_query($conn, "SELECT * FROM token");
$row2 = mysqli_fetch_assoc($result2);
$authorization2="Authorization: Bearer ";
$authorization2.=$row2['access_token'];
$response2 = set_timeline($data_json,$authorization2);
}


$conn->close();

function contactExists($maildata,$auth) {
  $url='https://api.hubapi.com/contacts/v1/contact/email/'.$maildata.'/profile';
  $options = array(
    CURLOPT_URL => $url,
    CURLOPT_HTTPHEADER => array('Content-Type: application/json', $auth),
    CURLOPT_RETURNTRANSFER => true,   // return web page
  );
  $ch = curl_init($url);
  curl_setopt_array($ch, $options);
  $content  = curl_exec($ch);
  $info = curl_getinfo($ch);
  curl_close($ch);
  if($info["http_code"] == 200) {
  return true;
  }else{
  return false;
  }
}

function set_timeline($data,$auth) {
    $url="https://api.hubapi.com/integrations/v1/113168/timeline/event";
    $options = array(
    	CURLOPT_URL => $url,
    	CURLOPT_HTTPHEADER => array('Content-Type: application/json', $auth),
    	CURLOPT_CUSTOMREQUEST => "PUT",
    	CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,   // return web page
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);

    $content  = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    $resArr = array();
    $resArr = json_decode($content);
    echo "<pre>"; print_r($resArr); echo "</pre>";
    echo $info["http_code"];

    if($info["http_code"] == 204) {
    return true;
    }else{
    return false;
    }
}

function refresh_token($refreshtoken){
$url ="https://api.hubapi.com/oauth/v1/token";
$refreshdata = "grant_type=refresh_token&redirect_uri=http://www.hubspot.com/&refresh_token=";
$refreshdata .= $refreshtoken;

    $options = array(
    	CURLOPT_URL => $url,
    	CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded', 'charset=utf-8'),
    	CURLOPT_CUSTOMREQUEST => "POST",
    	CURLOPT_POSTFIELDS => $refreshdata,
        CURLOPT_RETURNTRANSFER => true,   // return web page
    );

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);

    $content  = curl_exec($ch);

    curl_close($ch);

$obj = json_decode($content);
$rtoken = $obj->{'refresh_token'};
$atoken = $obj->{'access_token'};
$expiry = $obj->{'expires_in'};


global $conn;
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "UPDATE token SET refresh_token = '$rtoken', access_token = '$atoken', expires_in ='$expiry' WHERE id=1";

if ($conn->query($sql) === TRUE) {
    echo "Record updated successfully";
} else {
    echo "Error updating record: " . $conn->error;
}



$resArr = array();
$resArr = json_decode($content);
echo "<pre>"; print_r($resArr); echo "</pre>";
    return true;

}

?>
