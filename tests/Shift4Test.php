<?php

if ( !isset( $_SESSION ) ) $_SESSION = array();

use PrimitiveSocial\Shift4Wrapper\Shift4Wrapper;
use PrimitiveSocial\Shift4Wrapper\Token;
use PrimitiveSocial\Shift4Wrapper\Transaction;

class Shift4Test extends PHPUnit_Framework_TestCase
{

	protected $backupGlobalsBlacklist = array( '_SESSION' );

	protected $accessToken;

	protected $token;

	protected $secondToken;

	protected $allGood = true;

	public function testShift4GetsToken() {

		$client = new Shift4Wrapper();

		$client->login();

		$_SESSION['accessToken'] = $client->accessToken;

		$this->assertNotNull($client->accessToken);

	}

	public function testShift4FailsWithoutClientUrl() {

		$this->expectException(\PrimitiveSocial\Shift4Wrapper\Shift4WrapperException::class);

		$client = new Shift4Wrapper($_SESSION['accessToken'], 'poop', env('SHIFT4_CLIENT_GUID'), env('SHIFT4_AUTH_TOKEN'));

	}

	public function testShift4FailsWithoutClientGuid() {

		$this->expectException(\PrimitiveSocial\Shift4Wrapper\Shift4WrapperException::class);

		$client = new Shift4Wrapper($_SESSION['accessToken'], env('SHIFT4_API_URL'), 'poop', env('SHIFT4_AUTH_TOKEN'));

	}

	public function testShift4FailsWithoutAuthToken() {

		$this->expectException(\PrimitiveSocial\Shift4Wrapper\Shift4WrapperException::class);

		$client = new Shift4Wrapper($_SESSION['accessToken'], env('SHIFT4_API_URL'), env('SHIFT4_CLIENT_GUID'), 'poop');

	}

	public function testShift4CanTokenize() {

		$client = new Token($_SESSION['accessToken']);

		$client->ip('173.49.87.94')
				->expirationMonth(12)
				->expirationYear(30)
				->cardNumber('4321000000001119')
				->cvv('333')
				->cardType('VS')
				->name('John Smith')
				->zip('65000')
				->address('65 Main Street')
				->post();

		$output = $client->output();

		// Set card value to preserve through tests
		$_SESSION['token'] = $client->getToken();

		$this->assertNotNull($client->getToken());

	}

	public function testShift4CanPostSale() {

		$randomInvoice = rand();

		$tokenizer = new Token($_SESSION['accessToken']);

		$tokenizer->ip('173.49.87.94')
				->expirationMonth(12)
				->expirationYear(30)
				->cardNumber('4321000000001119')
				->cvv('333')
				->cardType('VS')
				->name('John Smith')
				->zip('65000')
				->address('65 Main Street')
				->post();

		$transaction = new Transaction($_SESSION['accessToken']);

		$transaction->tax(11.14)
				->total(111.45)
				->clerk('24')
				// ->addressLine1('65 Main Street')
				->invoiceNumber($randomInvoice)
				->tokenValue($tokenizer->getToken())
				->firstName('John')
				->lastName('Smith')
				->postalCode('65000')
				->addressLine1('65 Main Street')
				->sale();

		$output = $transaction->output();

		$this->assertNotNull($output['result'][0]['transaction']);

		// Set transaction
		$_SESSION['transaction'] = $output['result'][0]['transaction']['invoice'];
		$_SESSION['tokenForTest5'] = $tokenizer->getToken();

	}

	public function testBasicTimeout() {

		$randomInvoice = rand();

		$tokenizer = new Token($_SESSION['accessToken']);

		$tokenizer->ip('173.49.87.94')
				->expirationMonth(12)
				->expirationYear(30)
				->cardNumber('4321000000001119')
				->cvv('333')
				->cardType('VS')
				->name('John Smith')
				->zip('65000')
				->address('65 Main Street')
				->post();

		try {

			$transaction = new Transaction($_SESSION['accessToken']);

			$transaction->total(111.61)
					->tokenValue($tokenizer->getToken())
					->invoiceNumber($randomInvoice)
					->sale();

		} catch (\PrimitiveSocial\Shift4Wrapper\Shift4WrapperException $e) {

			// Then Invoice Timeout
			try {
				$transaction = new Transaction($_SESSION['accessToken']);

				$transaction->invoiceNumber($randomInvoice)
						->invoice();
			} catch (\PrimitiveSocial\Shift4Wrapper\Shift4WrapperException $e) {

				$this->assertTrue(true);

			}

		}

	}

	public function testInvoiceTimeout() {

		$this->expectException(\PrimitiveSocial\Shift4Wrapper\Shift4WrapperException::class);

		$transaction = new Transaction($_SESSION['accessToken']);

		$transaction->total(200.00)
				->invoice();

	}

	public function testRefund() {

		$tokenizer = new Token($_SESSION['accessToken']);

		$tokenizer->ip('173.49.87.94')
				->expirationMonth(12)
				->expirationYear(30)
				->cardNumber('4321000000001119')
				->cvv('333')
				->cardType('VS')
				->name('John Smith')
				->zip('65000')
				->address('65 Main Street')
				->post();

		$transaction = new Transaction($_SESSION['accessToken']);

		$transaction->total(200.00)
				->tokenValue($tokenizer->getToken())
				->invoiceNumber($_SESSION['transaction'])
				->clerk('5188')
				->firstName('John')
				->lastName('Smith')
				->postalCode('89144')
				->addressLine1('123 Easy St')
				->refund();

		$result = $transaction->output();

		$this->assertNotNull($result['result'][0]['transaction']['invoice']);

	}

	public function testDeleteInvoice() {

		// Deleting an invoice requires the transaction be sent in the header
		$transaction = new Transaction($_SESSION['accessToken'], null, null, null, array('Invoice' => '12345'));

		$transaction->deleteInvoice('12345');

		$result = $transaction->output();

		// Laravel Logging
		$this->assertNotNull($result);

	}

	public function testPartialAuthorization() {

		// Ok, we need a random invoice number
		$randomInvoice = rand();

		$tokenizer = new Token($_SESSION['accessToken']);

		$tokenizer->ip('173.49.87.94')
				->expirationMonth(12)
				->expirationYear(30)
				->cardNumber('4321000000001119')
				->cvv('333')
				->cardType('VS')
				->name('John Smith')
				->zip('65000')
				->address('65 Main Street')
				->post();

		$transaction = new Transaction($_SESSION['accessToken']);

		$startingAmount = 219;

		// First sale will be partial
		$transaction->total($startingAmount)
				// ->tokenValue($_SESSION['token'])
				->tokenValue($tokenizer->getToken())
				->invoiceNumber($randomInvoice)
				->clerk(1)
				->firstName('John')
				->lastName('Smith')
				->postalCode('65000')
				->addressLine1('65 Main Street')
				->sale();

		$result1 = $transaction->output();

		$invoice = $result1['result'][0]['transaction']['invoice'];

		// This will be the remainder
		$transaction = new Transaction($_SESSION['accessToken']);

		$tokenizer = new Token($_SESSION['accessToken']);

		$tokenizer->ip('173.49.87.94')
				->expirationMonth(12)
				->expirationYear(30)
				->cardNumber('4616222222222257')
				->cvv('333')
				->cardType('VS')
				->name('John Smith')
				->zip('65000')
				->address('65 Main Street')
				->post();

		$newAmount = $startingAmount - (float) $result1['result'][0]['amount']['total'];

		$transaction->entryMode('M')
				->tokenValue($tokenizer->getToken())
				->total($newAmount)
				->invoiceNumber($randomInvoice)
				->clerk(1)
				->firstName('John')
				->lastName('Smith')
				->postalCode('65000')
				->addressLine1('65 Main Street')
				->sale();

		$result2 = $transaction->output();

		$this->assertNotNull($result2['result'][0]['transaction']);

	}

	public function testPartialAuthorizationVoided() {

		$tokenizer = new Token($_SESSION['accessToken']);

		$tokenizer->ip('173.49.87.94')
				->expirationMonth(12)
				->expirationYear(30)
				->cardNumber('4321000000001119')
				->cvv('333')
				->cardType('VS')
				->name('John Smith')
				->zip('65000')
				->address('65 Main Street')
				->post();

		$transaction = new Transaction($_SESSION['accessToken']);

		$startingAmount = 219;

		// First sale will be partial
		$transaction->total($startingAmount)
				->tokenValue($tokenizer->getToken())
				->firstName('John')
				->lastName('Smith')
				->clerk('1')
				->invoiceNumber('34567123')
				->postalCode('65000')
				->addressLine1('65 Main Street')
				->sale();

		$result = $transaction->output();
		
		$amountAuthorized = $result['result'][0]['amount']['total'];

		$invoice = $result['result'][0]['transaction']['invoice'];

		$this->assertNotNull($invoice);

		$transaction = new Transaction($_SESSION['accessToken']);

		$transaction->deleteInvoice('34567123');

		$result = $transaction->output();

		$this->assertNotNull($result);

	}

	public function testSplitTender() {

		$tokenizer = new Token($_SESSION['accessToken']);

		$tokenizer->ip('173.49.87.94')
				->expirationMonth(12)
				->expirationYear(30)
				->cardNumber('4321000000001119')
				->cvv('333')
				->cardType('VS')
				->name('John Smith')
				->zip('65000')
				->address('65 Main Street')
				->post();

		$transaction = new Transaction($_SESSION['accessToken']);

		$transaction->total(300)
				->tokenValue($tokenizer->getToken())
				// ->entryMode('M')
				// ->expirationDate(1230)
				// ->number('4321000000001119')
				->invoiceNumber('45678')
				// ->securityCode('333')
				->firstName('John')
				->lastName('Smith')
				->postalCode('89144')
				->addressLine1('123 Easy St')
				->sale();

		$output = $transaction->output();

		$this->assertNotNull($output['result'][0]['transaction']['invoice']);

		// Set transaction
		$_SESSION['transaction'] = $output['result'][0]['transaction']['invoice'];

		$transaction->total(200)
				->tokenValue($this->getToken())
				->firstName('John')
				->invoiceNumber('45678')
				->lastName('Smith')
				->postalCode('89144')
				->addressLine1('123 Easy St')
				->sale();

		$output = $transaction->output();

		$this->assertNotNull($output['result'][0]['transaction']['invoice']);

	}

	public function testAutoVoid() {

		$transaction = new Transaction($_SESSION['accessToken']);

		$transaction->total(1200)
				->tax(0)
				->entryMode('M')
				->expirationDate(1230)
				->number('4321000000001119')
				->securityCode('333')
				->clerk('5188')
				->invoiceNumber('56789')
				->firstName('John')
				->lastName('Smith')
				->postalCode('65000')
				->addressLine1('65 Main Street')
				->sale();

		$result = $transaction->output();

		$this->assertNotNull($result['result'][0]['transaction']['invoice']);

	}

	public function testDemoHostError() {

		$transaction = new Transaction($_SESSION['accessToken']);

		$transaction->total(1200)
				->entryMode('M')
				->expirationDate(1230)
				->number('4321000000001119')
				->securityCode('333')
				->invoiceNumber('56789')
				->clerk('5188')
				->firstName('John')
				->lastName('Smith')
				->postalCode('65000')
				->addressLine1('65 Main Street')
				->sale();

		$result = $transaction->output();

		$this->assertNotNull($transaction->error());

	}

	public function testAVSAndCSCValidation() {


		$transaction = new Transaction($_SESSION['accessToken']);

		$transaction->total(130)
				->tokenValue($this->getToken())
				// ->entryMode('M')
				// ->expirationDate(1230)
				// ->number('4321000000001119')
				// ->securityCode('444')
				->clerk('5188')
				->invoiceNumber('56789')
				->firstName('John')
				->lastName('Smith')
				->postalCode('78000')
				->addressLine1('78 Main Street')
				->sale();

		$result = $transaction->output();

		$this->assertEquals('f', strtolower($result['result'][0]['transaction']['responseCode']));

	}

	public function testExtendedTimeout2() {

		$this->expectException(\PrimitiveSocial\Shift4Wrapper\Shift4WrapperException::class);

		$transaction = new Transaction($_SESSION['accessToken']);

		$transaction->total(511.61)
				->tokenValue($this->secondToken)
				// ->entryMode('M')
				// ->expirationDate(1230)
				// ->number('4321000000001119')
				// ->securityCode('333')
				->firstName('John')
				->lastName('Smith')
				->postalCode('65000')
				->addressLine1('65 Main Street')
				->sale();

	}

	public function testExtendedTimeout3() {

		$this->expectException(\PrimitiveSocial\Shift4Wrapper\Shift4WrapperException::class);

		$transaction = new Transaction($_SESSION['accessToken']);

		$transaction->total(1111.61)
				->tokenValue($this->secondToken)
				// ->entryMode('M')
				// ->expirationDate(1230)
				// ->number('4321000000001119')
				// ->securityCode('333')
				->firstName('John')
				->lastName('Smith')
				->postalCode('65000')
				->addressLine1('65 Main Street')
				->sale();

	}

	public function testExtendedTimeout4() {

		$this->expectException(\PrimitiveSocial\Shift4Wrapper\Shift4WrapperException::class);

		$transaction = new Transaction($_SESSION['accessToken']);

		$transaction->total(112.61)
				->tokenValue($this->secondToken)
				// ->entryMode('M')
				// ->expirationDate(1230)
				// ->number('4321000000001119')
				// ->securityCode('333')
				->firstName('John')
				->lastName('Smith')
				->postalCode('65000')
				->addressLine1('65 Main Street')
				->sale();

	}

	public function testPikaPika() {

		$pika = <<<PIKA

        ,@@@@@@@@@@,,@@@@@@@%  .#&@@@&&.,@@@@@@@@@@,      %@@@@@@%*   ,@@@%     .#&@@@&&.  *&@@@@&(  ,@@@@@@@%  %@@@@@,     ,@@,          
            ,@@,    ,@@,      ,@@/   ./.    ,@@,          %@%   ,&@# .&@&@@(   .@@/   ./. #@&.  .,/  ,@@,       %@%  *&@&.  ,@@,          
            ,@@,    ,@@&%%%%. .&@@/,        ,@@,          %@%   ,&@# %@& /@@,  .&@@/,     (@@&%(*.   ,@@&%%%%.  %@%    &@#  ,@@,          
            ,@@,    ,@@/,,,,    ./#&@@@(    ,@@,          %@@@@@@%* /@@,  #@&.   ./#&@@@(   *(%&@@&. ,@@/,,,,   %@%    &@#  .&&.          
            ,@@,    ,@@,      ./,   .&@#    ,@@,          %@%      ,@@@@@@@@@% ./.   .&@# /*.   /@@. ,@@,       %@%  *&@&.   ,,           
            ,@@,    ,@@@@@@@% .#&@@@@&/     ,@@,          %@%     .&@#     ,@@/.#&@@@@&/   /%&@@@@.  ,@@@@@@@%  %@@@@@.     ,@@,          
,*************,,*/(((((//,,*(#%%%%%%%%%%%%%%%#(*,,,****************************************************,*/(((((((((/((((////****/((##%%%%%%
,*************,,//((((((//,,*(%%%%%%%%%%%%%%%%%##/*****************************************************,,*/(///(//////****//((##%%%%%%%%%%%
,************,,*/(((((((//***/#%%%%%%%%%%%%%%%%%%%#(/***************************************************,*//////////*//((#%%%%%%%%%%%%%%%%%
,***********,,*////////////***/##%%%%%%%%%%%%%%%%%%%##(*,***********************************************,,*////////(###%%%%%%%%%%%%%%%%%%%%
,**********,,,*/*******//////**/(#%%%%%%%%%%%%%%%%%%%%%#(/**********************************************,,,***/(##%%%%%%%%%%%%%%%%%%%%%%%%%
,*********,,,,*************///***/(#%%%%%%%%%%%%%%%%%%%%%%#(/***********************************,****,****/((#%%%%%%%%%%%%%%%%%%%%%%%%%%%%#
,*********,,,***************//****/(##%%%%%%%%%%%%%%%%%%%%%%##//**************//////////////////////((#####%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#(
,********,,,,***********************/(#%%%%%%%%%%%%%%%%%%%%%%%##################%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%##(/
,*******,..,***********************,,*/##%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%###((//
,*******,.,,***********************,,,,*(#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%##(//**//
,******,.,,,************************,,,,*/(#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#(//*******
,*****,,,,,********,***,,,,,,,,,,,,*,,,,,,*/(######%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%##(/**********
,*****,..,*******,,,,,,,,,,,,,,,,,,,,,,*,,,,*///((#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%###(/************
,*****,,,*******,,,,,*,,,,,,,,,,,,,,,,,****,,,*/(#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#######(//**************
,****,.,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,**,,,/(%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#((//******************
,***,..,,,,,,,,,,,,,,,,,,,,,,,,,,,,,..,,,,,,,*(#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#(/*******************
,**,,.,,,,,,,,,,,,,,,,,,,,,,,,,,.......,,,,,,/#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#####%%%%%%%%%%%%%%%%#(/******************
,**,..,,,,,,,,,,,,,,,,,,,,,,,,,......,,,*,,,*(#%%%%%%%%##(((/(##%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%##(((/*/((#%%%%%%%%%%%%%%#(/*****************
,*,..,,,,,,,,,,,,,,,,,,,,,,,,,,,.....,,**,,*/#%%%%%%%##((((*,**/#%%%%%%%%%%%%%%%%%%%%%%%%%%%%##((##/,,,*(#%%%%%%%%%%%%%%#(*****************
.*,.,,,**,,,,,,,,,,,,,,,,,,,,,,,,,,*****,,,/(%%%%%%%%#(//(#/,..*/#%%%%%%%%%%%%%%%%%%%%%%%%%%%#(//(#/,..,/(#%%%%%%%%%%%%%%#/*****///////////
.,..,,,,,,,,,,,,,,,,,,,,,,,,,,*,,*******,,,(#%%%%%%%%#(*,,,....,/#%%%%%%%%%%%%%%%%%%%%%%%%%%%#(*,,,....,/(#%%%%%%%%%%%%%%#(*,**////////////
.,..,,,,,,,,,...........,,,,,,*,********,,*(#%%%%%%%%%#(/*,,...,/#%%%%%%%%%%%%%%%%%%%%%%%%%%%%#(/*,,..,*/##%%%%%%%%%%%%%%%#(***////////////
...,,,,,,,................,,*,**********,,/#%%%%%%%%%%%%#((////((#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%##((///(#%%%%%%%%%%%%%%%%%%(/**////////////
 ..,,,,,,.................,,,**********,,*(#%%%%%%%%%%%%%%%%%%#%%%%%%%%#((///((#%%%%%%%%%%%%%%%%%%%%%#%%%%%%%%%%%%%%%%%%%%%#/**////////////
.,,,,,,,,.................,,***********,,/(####%%%%%%%%%%%%%%%%%%%%%%%%#(/*,,,*(#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#(/*////////////
.,***,,,,,,..............,,,**********,..,***//((##%%%%%%%%%%%%%%%%%%%%%%%##((##%%%%%%%%%%%%%%%%%%%%%%%%%##(((((((((###%%%%%#/**///////////
.*****,,,,,,,,,,,,,,,,,,,*************,..,*******/(#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%##///*//////((#%%%%%#(**///////////
.****************/******/***////*****,.,*///////**/#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#(////////////(#%%%%%#/**//////////
.***********************/////*******,..,*//////////(#%%%%%%%%%%%%%%%%%%%%##########%%%%%%%%%%%%%%%%%%%%#(///////////*/(#%%%%%#(***/////////
.************************///********,..,*//////////#%%%%%%%%%%%%%%%%%%#(//*****///(((##%%%%%%%%%%%%%%%%#(///////////**/##%%%%##/***////////
.***********************************,.,,***///////(#%%%%%%%%%%%%%%%%#(/*,,,*//((((////(#%%%%%%%%%%%%%%%#((////////////(#%%%%%%#(*********//
,***********,,,*,,*,,**************,,,*//******//(#%%%%%%%%%%%%%%%%%#(*,,*/(((#####(((((#%%%%%%%%%%%%%%%##///////////(#%%%%%%%%#(***///////
,*************,,**,,,************,,,,,/(##((((####%%%%%%%%%%%%%%%%%%%(/**/(((#((((#((//(#%%%%%%%%%%%%%%%%%#(((((((((##%%%%%%%%%%#/**///////
,******************************,,,,,,,*(#%#%%%%%%%%%%%%%%%%%%%%%%%%%%#(**/((#(#(((#((//(#%%%%%%%%%%%%%%%%%%%%%%%#%#%%%%%%%%%%%%%#(**///////
,*************,**************,****,,,,,/(#%%%%%%%%%%%%%%%%%%%%%%%%%%%%#(/*/((((#((((///(#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%(/*///////
,*************************************,*/#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%##(////////////(#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#/**/////*
,******////****///////////////////////***/#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%####(((((((###%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#(********
.,*,****///////////////////////////////***/#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%#(/*******
.,,,,*****//////////////////////////*******(#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%##(*******
.,,,,,,***********/////////////////********/(#%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%(*******
PIKA;

		if($this->allGood) {

			echo $pika;

		}

		$this->assertTrue(true);

	}

	private function getToken() {

		if(!$this->secondToken) {

			$client = new Token($_SESSION['accessToken']);

			$client->ip('173.49.87.94')
					->expirationMonth(12)
					->expirationYear(30)
					->cardNumber('4321000000001119')
					->name('John Smith')
					->zip('89144')
					->address('123 Easy St')
					->post();

			$result = $client->output();

			// Set card value to preserve through tests
			$this->secondToken = $client->getToken();

		}
		return $this->secondToken;

	}

}