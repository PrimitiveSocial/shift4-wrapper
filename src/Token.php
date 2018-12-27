<?php

namespace PrimitiveSocial\Shift4Wrapper;

use GuzzleHttp\Client;
use Carbon\Carbon;
use PrimitiveSocial\Shift4Wrapper\Shift4Wrapper;

class Token extends Shift4Wrapper
{

	public function __construct($accessToken = null, $clientUrl = null, $clientGuid = null, $clientAuthToken = null, $additionalHeaders = array()) {

		// Stop the authorization process, since we're using i4go
		// $this->shouldLogin = FALSE;

		parent::__construct($accessToken, $clientUrl, $clientGuid, $clientAuthToken, $additionalHeaders);

		// I4GO specific stuff
		// Set URL
		$this->clientUrl = config('shift4wrapper.i4go_api');
		$this->versionUri = '';
		$this->callMethod = 'POST';
		$this->uri = 'index.cfm';

		if(!$this->clientUrl || !$this->isValid('clientUrl', $this->clientUrl)) throw Shift4WrapperException::noApiUrl();

		// Set up Guzzle client
		$this->client = new Client(array(
			'base_uri' => $this->clientUrl,
			'handler' => $this->stack,
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			)
		));

		// Set metaToken type
		$this->sendData['i4go_metatoken'] = 'IL';

	}

	// Methods
	public function post() {

		$this->authorizeClient();

		$this->sendData['fuseaction'] = 'api.jsonPostCardEntry';

		unset($this->sendData['i4go_clientip'], $this->sendData['i4go_metatoken'], $this->sendData['i4go_server'], $this->sendData['i4go_accesstoken']);

		$this->send();

	}

	// Setters
	public function ip($ip) {

		$this->sendData['i4go_clientip'] = $ip;

		return $this;

	}

	public function cardType($cardtype) {

		$this->sendData['i4go_cardtype'] = $cardtype;

		return $this;

	}

	/**
	 * Use the i4go_cardnumber parameter to post the payment card number, as entered by the end user, to i4Go.
	 * @param  [type] $cardnumber [description]
	 * @return [type]             [description]
	 */
	public function cardNumber($cardnumber) {

		$this->sendData['i4go_cardnumber'] = $cardnumber;

		return $this;

	}

	/**
	 * Use the i4go_expirationmonth parameter to post the expiration month of the payment card, as entered by the end user, to i4Go. Choose between the following formats; for example, April would be 4 or 04. 
	 * @param  [type] $expirationmonth [description]
	 * @return [type]                  [description]
	 */
	public function expirationMonth($expirationmonth) {

		$this->sendData['i4go_expirationmonth'] = $expirationmonth;

		return $this;

	}

	/**	
	 * Use the i4go_expirationyear parameter to post the expiration year of the payment card, as entered by the end user, to i4Go. Choose between the following formats; for example, the year would be 17 or 2017. 
	 * @param  [type] $expirationyear [description]
	 * @return [type]                 [description]
	 */
	public function expirationYear($expirationyear) {

		$this->sendData['i4go_expirationyear'] = $expirationyear;

		return $this;

	}

	public function cvv($cvv2code) {

		$this->sendData['i4go_cvv2code'] = $cvv2code;
		$this->sendData['i4go_cvv2indicator '] = 1;

		return $this;

	}

	public function name($name) {

		$this->sendData['i4go_cardholdername'] = $name;

		return $this;

	}

	public function zip($postalCode) {

		$this->sendData['i4go_postalcode'] = $postalCode;

		return $this;

	}

	public function address($addressLine1) {

		$this->sendData['i4go_streetaddress'] = $addressLine1;

		return $this;

	}

	public function getToken() {

		if(!array_key_exists('i4go_uniqueid', $this->output)) return false;

		return $this->output['i4go_uniqueid'] ?: false;

	}


	// Private Functions
		/**
	 * The application will need to modify the payment information form to include the access block (which includes the merchant’s Access Token).
	 * @param  [type] $accessblock [description]
	 * @return [type]              [description]
	 */
	public function authorizeClient() {

		// Parse sendData
		$tempSendData = $this->sendData;

		$this->sendData = array(
			'fuseaction' => 'account.authorizeClient',
			'i4go_accesstoken' => $this->accessToken,
			'i4go_clientip' => $this->sendData['i4go_clientip'],
			'i4go_metatoken' => 'IL',
		);

		try {

			$client = new Client(array(
						'base_uri' => $this->clientUrl,
						'handler' => $this->stack,
						'headers' => array(
							'Content-Type' => 'application/x-www-form-urlencoded',
						)
					));

			$response = $client->request(
				$this->callMethod,
				$this->versionUri . $this->uri,
				array(
					'form_params' => $this->sendData
				)
			);

			$this->output = json_decode($response->getBody(), TRUE);

		} catch (GuzzleHttp\Exception\ClientException $e) {

			throw Shift4WrapperException::guzzleError($e->getMessage(), $this->getBody(), $this->sendData, $this->versionUri . $this->uri);

		} catch (\Exception $e) {

			throw Shift4WrapperException::guzzleError($e->getMessage(), $this->getBody(), $this->sendData, $this->versionUri . $this->uri);

		}

		if($this->output['i4go_responsecode'] && $this->output['i4go_responsecode'] != 1) {
			throw Shift4WrapperException::guzzleError($this->output, $this->getBody(), $this->sendData, $this->versionUri . $this->uri);
		}

		$this->sendData = $tempSendData;
		// SAMPLE OUTPUT
		// array:7 [
		//   "i4go_f73c" => "i4go_491f"
		//   "i4go_i4m_url" => "https://i4m.shift4test.com"
		//   "i4go_countrycode" => "US"
		//   "i4go_server" => "https://i4go-payment.shift4test.com"
		//   "i4go_accessblock" => "A00005A"
		//   "i4go_response" => "SUCCESS"
		//   "i4go_responsecode" => 1
		// ]

		$this->sendData['i4go_accessblock'] = $this->output['i4go_accessblock'];
		$this->sendData['i4go_server'] = $this->output['i4go_server'];
		$this->sendData['i4go_accesstoken'] = $this->accessToken;
		$this->clientUrl = $this->output['i4go_server'];

		return $this;

	}

	protected function send() {

		$this->client = new Client(array(
			'base_uri' => $this->clientUrl,
			'handler' => $this->stack,
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			)
		));

		try {

			$response = $this->client->request(
				'POST',
				$this->uri,
				array(
					'form_params' => $this->sendData
				)
			);

			$this->output = json_decode($response->getBody(), TRUE);

			return $this;

		} catch (GuzzleHttp\Exception\ClientException $e) {

			throw Shift4WrapperException::guzzleError($e->getMessage(), $this->getBody(), $this->sendData, $this->versionUri . $this->uri);

		} catch (\Exception $e) {

			throw Shift4WrapperException::guzzleError($e->getMessage(), $this->getBody(), $this->sendData, $this->versionUri . $this->uri);

		}

	}

}