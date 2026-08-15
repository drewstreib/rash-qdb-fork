<?php

function get_db($CONFIG, $TEMPLATE=NULL)
{
    try {
	/* ERRMODE is set explicitly so it does not depend on the PHP version:
	   the default was ERRMODE_SILENT up to PHP 7.4 and became
	   ERRMODE_EXCEPTION in 8.0. WARNING is the deliberate middle choice --
	   control flow is unchanged (a failed call still returns false, so the
	   ~70 unchecked call sites behave exactly as before), but the failure
	   is no longer invisible: it raises E_WARNING, which index.php routes
	   to the container log. Silent failure is how IPv6 votes disappeared
	   for a year without anyone noticing. */
	$db = new PDO($CONFIG['phptype'].":host=".$CONFIG['hostspec'].";dbname=".$CONFIG['database'],
		      $CONFIG['username'], $CONFIG['password'],
		      array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING,
			    /* PHP 8.1 made mysqlnd return native int/float for
			       numeric columns instead of strings, which silently
			       changed api.php's JSON: {"napproved":"4591"} became
			       {"napproved":4591}, and "id":"4591" became
			       "id":4591. That is a published API with consumers we
			       cannot enumerate, so the 7.3 shape is preserved
			       deliberately. Drop this to modernise the API, as a
			       decision of its own -- not as a side effect of a
			       version bump. */
			    PDO::ATTR_STRINGIFY_FETCHES => true));
	return $db;
    } catch (PDOException $dberror) {
	/* The exception message names the host, the database and the
	   username. That belongs in the log, not in the response. */
	error_log('Database connection failed: '.$dberror->getMessage());
	if ($TEMPLATE) $TEMPLATE->printheader('Error');
	print '<p>The database is currently unavailable. Please try again later.';
	if ($TEMPLATE) $TEMPLATE->printfooter();
	exit;
    }
}

function check_db_res($res, $query=NULL)
{
    global $db;
    if (!$res) {
	/* The failing SQL and the driver message describe the schema, and on a
	   connection-level failure the host and username too. Log them; show
	   the visitor only that something went wrong. */
	$err = $db->errorInfo();
	error_log('Query failed: SQLSTATE '.$err[0].' ['.$err[1].'] '.$err[2]
		  .($query ? ' -- query: '.$query : ''));
	print '<p>A database error occurred. Please try again later.';
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
