<?php


//===============================================
// Configuration
//===============================================

if( class_exists('Config') && method_exists(new Config(),'register')){ 

	// Register variables
	Config::register("aws", "simpleDB_host", "sdb.us-west-1.amazonaws.com");
	Config::register("aws", "s3_region", "s3-us-west-1.amazonaws.com");
	
}

?>