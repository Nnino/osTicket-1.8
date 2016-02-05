<?php

function deleteCollaborator($id){
    $mysqli = new mysqli(constant("DBHOST1"), constant("DBUSER1"), constant("DBPASS1"), constant("DBNAME1"));

    if ($mysqli->connect_errno) {
        return "err5";
    }
    if(!$id)
    	return 'err1'; 
    $sql="DELETE FROM ost_ticket_collaborator WHERE id=".$id;
    $result = $mysqli->query($sql);
    if($result){
    	$mysqli->close();
        return "00";
    } else {
    	$mysqli->close();
        return "err5";
    }
}

require(__DIR__."/../connection-database.php");

$id = $_POST['id'];

$result = deleteCollaborator($id);

echo($result);
?>