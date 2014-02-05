<?php


//===============================================
// Configuration
//===============================================

if( class_exists('Config') && method_exists(new Config(),'register')){

	// Register variables
	Config::register("aws", "simpleDB_host", "sdb.amazonaws.com");
	Config::register("aws", "simpleDB_timestamps", "1");
	Config::register("aws", "simpleDB_soft_delete", "1");
	Config::register("aws", "s3_region", "s3.amazonaws.com");

}

?>