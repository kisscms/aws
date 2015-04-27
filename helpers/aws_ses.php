<?php
/*
	AWS: Simple Email Service for KISSCMS
*/
if( !trait_exists('SES') ){

// dependencies
//use Aws\Ses\SesClient;

trait SES {

	protected $client;

	function initSES() {
		//
		try {
			$this->client = $GLOBALS['api']['aws']->get('ses');
		} catch (Exception $e) {
			die('SES Connection failed: '.$e );
		}
		/*
		$this->client = SesClient::factory(array(
			'key'    => $GLOBALS['config']['aws']['key'],
			'secret' => $GLOBALS['config']['aws']['secret'],
			'region' => 'us-east-1'
		));
		*/
	}

	function sendEmail( $params ){
		// validate params?
		$options = array(
			// Source is required
			'Source' => $params['sender'],
			'Destination' => array(
				'ToAddresses' => array( $params['recipient'] )
			),
			'Message' => array(
				'Subject' => array(
					'Data' => $params['subject'],
					'Charset' => "UTF-8"
				),
				// Body is required
				'Body' => array(
					'Text' => array(
						'Data' => $params['text'],
						'Charset' => "UTF-8"
					),
					'Html' => array(
						'Data' => $params['html'],
						'Charset' => "UTF-8"
					)
				)
			)
		);
		// contact service
		$this->client->sendEmail( $options );
	}

}

}

?>