<?php 

function addCollaborator($idUser, $idTicket, $idOwner) {
    $mysqli = new mysqli(constant("DBHOST1"), constant("DBUSER1"), constant("DBPASS1"), constant("DBNAME1"));

    if ($mysqli->connect_errno) {
        return "err5";
    }
	if (!$idTicket || !$idOwner || !$idUser) 
		return "err1" ;
    if (!$idUser || $idUser==$idOwner)
        return "err2" ;
    $role = "M";
    $updated = date("Y-m-d H:i:s");
    $isActive = "1";
   
    $sql="INSERT INTO ost_ticket_collaborator (isactive, ticket_id, user_id, role, updated)"
    ." VALUES ('".$isActive
    ."','".$idTicket
    ."','".$idUser
    ."','".$role
    ."','".$updated."')";
	if($mysqli->query($sql) && ($id=$mysqli->insert_id)){
        $sql2= "SELECT usemail.address as user_address FROM ost_user_email usemail WHERE usemail.user_id =".$idUser;
        $result = $mysqli->query($sql2);
        if($result){
            $row = $result->fetch_array(MYSQLI_ASSOC);
            $mysqli->close();
            $email = $row['user_address'];
            
            $nombre = $email.' '.$id;
            return $nombre;
        } else {
            $mysqli->close();
            return "err3";
        }
        
        
    } else {
        $mysqli->close();
    	return "err4";
    }
}

require(__DIR__."/../connection-database.php");

$idUser = $_POST["idUser"];
$idTicket = $_POST["idTicket"];
$idOwner = $_POST["idOwner"];

$result = addCollaborator($idUser, $idTicket, $idOwner);

echo($result);
?>