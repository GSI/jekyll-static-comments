<?php

// commentsubmit.php -- Receive comments and e-mail them to someone
// Copyright (C) 2011 Matt Palmer <mpalmer@hezmatt.org>
//
//  This program is free software; you can redistribute it and/or modify it
//  under the terms of the GNU General Public License version 3, as
//  published by the Free Software Foundation.
//
//  This program is distributed in the hope that it will be useful, but
//  WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
//  General Public License for more details.
//
//  You should have received a copy of the GNU General Public License along
//  with this program; if not, see <http://www.gnu.org/licences/>


// Format of the date you want to use in your comments.  See
// http://php.net/manual/en/function.date.php for the insane details of this
// format.
$DATE_FORMAT = "Y-m-d H:i";

// Where the comment e-mails should be sent to.  This will also be used as
// the From: address.  Whilst you could, in theory, change this to take the
// address out of the form, it's *incredibly* highly recommended you don't,
// because that turns you into an open relay, and that's not cool.
$EMAIL_ADDRESS = "root";

// The contents of the following file (relative to this PHP file) will be
// displayed after the comment is received.  Customise it to your heart's
// content.
$COMMENT_RECEIVED = "comment_received.html";

// The contents of the following file (relative to this PHP file) will be
// displayed if the comment contains spam.  Customise it to your heart's
// content.
//$COMMENT_CONTAINS_SPAM = "comment_contains_spam.html";

// If the emails arrive in your client "garbled", you may need to change this
// line to "\n" instead.
$HEADER_LINE_ENDING = "\n";


/****************************************************************************
 * HERE BE CODE
 ****************************************************************************/

require_once 'mail.php';

function get_post_data_as_yaml()
{
	$yaml_data = "";
	
	foreach ($_POST as $key => $value) 
	{
		if (strstr($value, "\n") != "") 
		{
			// Value has newlines... need to indent them so the YAML
			// looks right
			$value = str_replace("\n", "\n  ", $value);
		}
		// It's easier just to single-quote everything than to try and work
		// out what might need quoting
		$value = "'" . str_replace("'", "''", $value) . "'";
		$yaml_data .= "$key: $value\n";
	}
	
	return $yaml_data;
}

/* NOTE the checkdnsrr function seems to be unreliable */
function get_warnings_for($name, $email, $url)
{
	$warnings = '';
	
	// http://php.net/manual/en/filter.filters.validate.php
	$name_is_a_url =			filter_var($name,   FILTER_VALIDATE_URL);
	$name_is_an_email_address =	filter_var($name,   FILTER_VALIDATE_EMAIL);
	$email_is_invalid =		!filter_var($email, FILTER_VALIDATE_EMAIL);
	$url_is_invalid =		!filter_var($url,   FILTER_VALIDATE_URL);
	$url_a_record_invalid =		false;
	$email_a_record_invalid =		false;
	$email_mx_record_invalid =	false;
	
	if (!$email_is_invalid) {
		// TODO only retrieve $domain
		list($user, $domain) =		explode('@', $email, 2);
		$email_a_record_invalid =		!checkdnsrr($domain, 'A');
		$email_mx_record_invalid =	!checkdnsrr($domain, 'MX');
	}
	
	if (!$url_is_invalid) {
		list($protocol, $domain) =	explode('/', str_replace('//', '/', $url));
		$url_a_record_invalid =		!checkdnsrr($domain, 'A');
	}
	
	$name_is_a_url ? 		$warnings .= "* Name:    Is a URL\n" : '';
	$name_is_an_email_address ?	$warnings .= "* Name:    Is an email address\n" : '';
	$email_is_invalid ?		$warnings .= "* Email:   Invalid address\n" : '';
	$email_a_record_invalid ?		$warnings .= "* Email:   Invalid Domain A record\n" : '';
	$email_mx_record_invalid ?	$warnings .= "* Email:   Invalid Domain MX record\n" : '';
	!empty($url) && $url_is_invalid ?	$warnings .= "* Website: Invalid URL\n" : '';
	$url_a_record_invalid ?		$warnings .= "* Website: Invalid Domain A record\n" : '';
	
	// This is of minor elegance and error prone, I know.
	$warnings_count =		substr_count($warnings, "\n");
	return strlen($warnings) > 0 ?	"\n$warnings_count WARNING/S:\n$warnings" : '';
}

$COMMENT_DATE =			date($DATE_FORMAT);

$COMMENTER_NAME =		filter_input(INPUT_POST, 'name');
$COMMENTER_EMAIL_ADDRESS =	filter_input(INPUT_POST, 'email');
$COMMENTER_WEBSITE =		filter_input(INPUT_POST, 'link');
$COMMENT_BODY =			filter_input(INPUT_POST, 'comment');

$POST_TITLE =			filter_input(INPUT_POST, 'post_title');
$POST_ID =			filter_input(INPUT_POST, 'post_id');
unset($_POST['post_id']);

$subject = "$COMMENTER_NAME on '$POST_TITLE'";

$message = "$COMMENT_BODY\n\n";
$message .= "----------------------\n";
$message .= "$COMMENTER_NAME\n";
$message .= "$COMMENTER_WEBSITE\n";

$message .= get_warnings_for($COMMENTER_NAME, $COMMENTER_EMAIL_ADDRESS, $COMMENTER_WEBSITE);

$mail = new Mail($subject, $message);
$mail->set_from($EMAIL_ADDRESS, $COMMENTER_NAME);
$mail->set_reply_to($COMMENTER_EMAIL_ADDRESS, $COMMENTER_NAME);

$yaml_data = "post_id: $POST_ID\n";
$yaml_data .= "date: $COMMENT_DATE\n";
$yaml_data .= get_post_data_as_yaml();

$attachment_date = date('Y-m-d-H-i-s');
$attachment_name = Mail::filter_filename($POST_ID, '-') . "-comment-$attachment_date.yaml";

$mail->header_line_ending = $HEADER_LINE_ENDING;
$mail->set_attachment($yaml_data, $attachment_name);


if ($mail->send($EMAIL_ADDRESS))
{
	include $COMMENT_RECEIVED;
}
else
{
	echo "There was a problem sending the comment. Please contact the site's owner.";
}
