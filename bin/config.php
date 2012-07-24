<?php


//===============================================
// Configuration
//===============================================

if( class_exists('Config') && method_exists(new Config(),'register')){ 

	// Register variables
	Config::register("aws", "simpleDB_host", "us-west-1.amazonaws.com");
	
}

?>