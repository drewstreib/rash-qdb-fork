<?php

function get_db($CONFIG, $TEMPLATE=NULL)
{
    try {
	$db = new PDO($CONFIG['phptype'].":host=localhost;dbname=".$CONFIG['database'], $CONFIG['username'], $CONFIG['password']);
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
