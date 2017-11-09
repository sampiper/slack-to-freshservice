<?php
######## SLACK ###########
# Variables required:

# $slack_api_token          < Generate: https://api.slack.com/custom-integrations/legacy-tokens
# $slack_verification_token < Get from Slash Command page
# $freshservice_url         < "https://<your domain>.freshservice.com/helpdesk/tickets.json"
# $freshservice_api_key     < Located on your Freshservice profile page

$command = $_POST['command'];           // Slash command - should be /request
$request = $_POST['text'];              // Text after Slash command
$token = $_POST['token'];               // Slash command verification token
$user_id = $_POST['user_id'];           // Slack user ID
$slack_username = $_POST['user_name'];  // Slack username

#Verify request came from your Slack team
if($token != $slack_verification_token){
    $msg = "Oops. The token for the slash command doesn't match. Check your script.";
    die($msg);
    echo $msg;
}

# Use Slack web API to get the user details of the requester
$slack_url = "https://slack.com/api/users.info?token=".$slack_api_token."&user=".$user_id."&pretty=1";
$ch = curl_init($slack_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$ch_response = curl_exec($ch);
curl_close($ch);
$ch_response = stripslashes($ch_response);
$response_array = json_decode($ch_response,true); #convert to array

$user_email = $response_array['user']['profile']['email']; #get email address
$user_first_name = $response_array['user']['profile']['first_name']; #get first name

########## FRESHSERVICE #############
# Set ticket fields
$ticket_data = json_encode(array("helpdesk_ticket" => array(
	"description" => "Ticket generated from Slack",
	"subject" => $request,
	"email" => $user_email,
	"priority" => 1,     #Medium
	"status" => 2,       #Open
	"source" => 10       #Slack
	)));

# POST to Freshservice
$ch = curl_init($freshservice_url);
$header[] = "Content-type: application/json";
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$freshservice_api_key:x");
curl_setopt($ch, CURLOPT_POSTFIELDS, $ticket_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$server_output = curl_exec($ch);
$info = curl_getinfo($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($server_output, 0, $header_size);
$response = substr($server_output, $header_size);

# Extract data from response
$new_ticket_array = json_decode($response, true);
$ticket_id = $new_ticket_array['item']['helpdesk_ticket']['display_id'];
curl_close($ch);

# Notify Slack user that new ticket has been created
echo "Hi ".$user_first_name.". We've created a new ticket for your request *".$request."*. Your ticket ID is ".$ticket_id." and the request was: ".$request;

?>
