<?php
// Name:    notification.php
// Purpose: This PHP script utilises the SiteHost API to alert customers 
//          when mailboxes under their control exceed or get close to their 
//          quoted limits. 
// Author:  SiteHost

// API Key
// Can be sourced from the SiteHost Control Panel
// 
// Instructions for generating a SiteHost API Key
// https://kb.sitehost.nz/developers/api#creating-an-api-key
//
$API_KEY = "";
// Visible in the SiteHost Control Panel next to Client name with a # prefix.
$client_id = "123456";
// Name of the mailserver responsible for sending and recieving emails. Our Shared Mail Service is 'sth-mail-air'
$server_name = "sth-mail-air";
// The email address where mail quota reports are delivered.
$mail_dest = "bob@example.org";
// Boolean flag for whether there should be a output via stdout.
$term_output = True;

$over_mailboxes = [];
$close_mailboxes = [];
$no_quota_mailboxes = [];

// Querries the SiteHost API and returns a the JSON dictionary.
function getAPI_JSON($uri) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$result = curl_exec($ch);
	curl_close($ch);
	return json_decode($result, true);
}

// Simple api.sitehost.nz request generator for list_accounts and list_domains
function genURI($request, $api_key, $client_id, $srv_name, $domain='') {
	if($domain == '') {
		return("https://api.sitehost.nz/1.1/mail/{$request}.json?apikey={$api_key}&client_id={$client_id}&server_name={$srv_name}");
	}
	else {
		return("https://api.sitehost.nz/1.1/mail/{$request}.json?apikey={$api_key}&client_id={$client_id}&server_name={$srv_name}&domain={$domain}");
	}
}

// Iterate through every domain in the account 
$domains = getAPI_JSON(genURI('list_domains', $API_KEY, $client_id, $server_name)); 
if($domains["status"]) {
	foreach ($domains['return'] as $dm_resp) {

		// Iterate through each mailbox on the domain 
		$mailboxes = getAPI_JSON(genURI('list_accounts', $API_KEY, $client_id, $server_name, $dm_resp['domain'])); 
		if($mailboxes["status"]) {
			foreach ($mailboxes["return"] as $mxb) {
				// Generate a quota percent. This is a variable out of 100.
				// quota_used is in Bytes while quota is in MegaBytes
				if ($mxb['quota'] > 0) {
					$quota_percent = ($mxb['quota_used']/1000)/$mxb['quota'] * 100;
					// This is where the over-quota mailboxes are separated.
					if($quota_percent >= 100) {
						$over_mailboxes[] = $mxb['emailaddr'];
					}
					elseif ($quota_percent >= 90) {
						$close_mailboxes[] = $mxb['emailaddr'];
					}
				} elseif ($mxb['quota'] == 0) {
					$no_quota_mailboxes[] = $mxb['emailaddr'];
				}
			}
		}
		// Sleep to avoid rate-limiting
		usleep(100000);
	}
}

if ($term_output) {
	echo("Over mailboxes:" . PHP_EOL);
	foreach ($over_mailboxes as $mxb) {
		echo("- {$mxb}" . PHP_EOL);
	}
	echo("Close mailboxes:" . PHP_EOL);
	foreach ($close_mailboxes as $mxb) {
		echo("- {$mxb}" . PHP_EOL);
	}
        echo("Mailboxes with no quota set:" . PHP_EOL);
        foreach ($no_quota_mailboxes as $mxb) {
                echo("- {$mxb}" . PHP_EOL);
        }

}

// The email subject and contents controls. Can be customised to suit requirements.
$email_subject = "Mailbox quota report for " . date("d/m/y");
$email_contents = "Mailbox quota report:" . PHP_EOL  . PHP_EOL . "Mailboxes found over quota:" . PHP_EOL . implode(PHP_EOL,$over_mailboxes) .
	          PHP_EOL . "Mailboxes near their quota (over 90% of quota):" . PHP_EOL . implode(PHP_EOL,$close_mailboxes) .
	          PHP_EOL . "Mailboxes with no quota set:" . PHP_EOL . implode(PHP_EOL,$no_quota_mailboxes);

$deliveryResult = mail($mail_dest, $email_subject, $email_contents);
if($deliveryResult && $term_output) {
	echo("Mail successfully sent to {$mail_dest}");
}

?>
