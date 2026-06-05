<?php
include_once("db.inc");

$userService = new UserService(); 
$candidateUser = $userService.getMigrationCandidate();




?>