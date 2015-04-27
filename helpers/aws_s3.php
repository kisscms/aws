<?php
/*
	AWS ORM for KISSCMS
	Provides a seamless interface to connect KISSCMS with a SimpleDB backend
	Source: http://github.com/kisscms/aws
	Created by Makis Tracend (@tracend)
*/
if(!class_exists('AWS_S3') ){

//use Aws\S3\S3Client;
//use Aws\S3\Exception\S3Exception;

class AWS_S3 extends Model {

//===============================================
// Database Connection
//===============================================
	protected function getdbh() {
		// generate the name prefix
		$name = 's3_'. $this->tablename;
		//precaution...
		if( !array_key_exists('db', $GLOBALS) ) $GLOBALS['db'] = array();
		//
		if ( !isset($GLOBALS['db'][$name]) && isset($GLOBALS['api']['aws']) ) {
			try {
				// Legacy: Instantiate the AmazonSDB class
				//$GLOBALS['db'][$name] = new AmazonS3();
				//$GLOBALS['db'][$name]->set_region( $GLOBALS['config']["aws"]["s3_region"] );
				$GLOBALS['db'][$name] = $GLOBALS['api']['aws']->get('s3');
				// FIX: cURL error (SSL certificate mismatch) for S3 bucket names with multiple dots
				$GLOBALS['db'][$name]->path_style = true;
			} catch (Exception $e) {
				die('{ "error": '. json_encode($e->getMessage()) .'}');
			}
		}
		return ( isset($GLOBALS['db'][$name]) ) ? $GLOBALS['db'][$name] : null;
	}


	function create($key, $params=array()) {
		// trigger the AWS service
		try{
			//$response = $this->db->create_object( $this->tablename, $key, $params);
			// use putObject instead?
			$response = $this->db->createMultipartUpload(array(
				//'ACL' => 'string',
				'Bucket' => $this->tablename,
				//'CacheControl' => 'string',
				//'ContentDisposition' => 'string',
				//'ContentEncoding' => 'string',
				//'ContentLanguage' => 'string',
				//'ContentType' => 'string',
				//'Expires' => 'mixed type: string (date format)|int (unix timestamp)|\DateTime',
				//'GrantFullControl' => 'string',
				//'GrantRead' => 'string',
				//'GrantReadACP' => 'string',
				//'GrantWriteACP' => 'string',
				// Key is required
				'Key' => $key,
				//'Metadata' => array(
					// Associative array of custom 'string' key names
				//	'string' => 'string',
				//),
				//'ServerSideEncryption' => 'string',
				//'StorageClass' => 'string',
				//'WebsiteRedirectLocation' => 'string',
			));
		} catch (Exception $e) {
				die('{ "error": '. json_encode($e->getMessage()) .'}');
		}
		// Success?
		return ( isset($response) ) ? true : false;
	}

	function read( $key ) {
		// trigger the AWS service
		try{
			//$response = $this->db->get_object( $this->tablename, $key);
			$response = $this->db->getObject(array(
				'Bucket' => $this->tablename,
				'Key'    => $key
			));
		} catch (Exception $e) {
				die('{ "error": '. json_encode($e->getMessage()) .'}');
		}
		// save the object
		$this->set("body", $response->body);
		// Success?
		return ( isset($response) ) ? true : false;
	}

	function update() {

	}

	function delete( $key=false ) {
		$id = (!$key) ? $this->rs[$this->pkname] : $key;
		// trigger the AWS service
		try{
			//$response = $this->db->delete_object( $this->tablename, $id );
			$response = $this->db->deleteObject(array(
				'Bucket' => $this->tablename,
				'Key'    => $id
			));
		} catch (Exception $e) {
				die('{ "error": '. json_encode($e->getMessage()) .'}');
		}
		// Success?
		return ( isset($response) ) ?  true : false;
	}


}
}

?>