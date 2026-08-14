<?php

function get_db($CONFIG, $TEMPLATE=NULL)
{
    try {
	/* Set explicitly so behaviour does not depend on the PHP version.
	   ERRMODE defaulted to SILENT through 7.4 and to EXCEPTION from 8.0;
	   WARNING keeps the old control flow -- a failed call still returns
	   false, so the many unchecked call sites behave as before -- while
	   making failures visible in the log instead of silent.
	   STRINGIFY_FETCHES keeps the pre-8.1 types: mysqlnd now returns native
	   int/float for numeric columns, which changes api.php's JSON from
	   {"napproved":"12"} to {"napproved":12}. */
	$db = new PDO($CONFIG['phptype'].":host=localhost;dbname=".$CONFIG['database'],
		      $CONFIG['username'], $CONFIG['password'],
		      array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING,
			    PDO::ATTR_STRINGIFY_FETCHES => true));
	return $db;
    } catch (PDOException $dberror) {
	if ($TEMPLATE) $TEMPLATE->printheader('Error');
	print $dberror->getMessage();
	if ($TEMPLATE) $TEMPLATE->printfooter();
	exit;
    }
}

function check_db_res($res, $query=NULL)
{
    global $db;
    if (!$res) {
	if ($query) print '<p>Query: '.$query.'<p>';
	$err = $db->errorInfo();
	print '<p>SQLSTATE: '. $err[0];
	print '<p>Driver error code: '. $err[1];
	print '<p>'.$err[2];
	die();
    }
}

function db_query($sql)
{
    global $db;
    $args = array();
    for ($i = 1; $i < func_num_args(); $i++)
	$args[] = func_get_arg($i);
    $sth = $db->prepare($sql);
    check_db_res($sth, $sql);
    $sth->execute($args);
    return $sth;
}

function db_query_singlevalue($sql)
{
    global $db;
    $args = array();
    for ($i = 1; $i < func_num_args(); $i++)
	$args[] = func_get_arg($i);
    $sth = $db->prepare($sql);
    check_db_res($sth, $sql);
    $sth->execute($args);
    $tmp = $sth->fetch(PDO::FETCH_NUM);
    return $tmp[0];
}
