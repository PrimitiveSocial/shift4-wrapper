# Shift4Wrapper
Wrapper for Shift4 API for Laravel

## Installation
`composer require primitivesocial/shift4wrapper`

Then, publish the config file:
`php artisan vendor:publish`

You will need to add the following `.env` variables.
`SHIFT4_API_URL`: URL for the Shift4 API
`SHIFT4_CLIENT_GUID`: GUID from Shift4
`SHIFT4_AUTH_TOKEN`: Auth token from Shift4
`I4GO_API_URL`: API URL for I4GO
`SHIFT4_COMPANY_NAME`: Name of company on file with Shift4
`SHIFT4_INTERFACE_NAME`: Name of interface on file with Shift4

## Usage

### Get Access Token
Shift4 access tokens are long term, so you should store these.
```
$client = new Shift4Wrapper();

$accessToken = $client->accessToken;

```

### Create I4GO Token
```
$client = new Token($accessToken);

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

// Set card value to preserve through tests
$token = $client->getToken();
```

### Post Sale
```
$transaction = new Transaction($accessToken);

$transaction->tax(11.14)
		->total(111.45)
		->clerk('1')
		->invoiceNumber('12345')
		->tokenValue($token)
		->purchaseCard(array(
			'customerReference' => 412348,
			'destinationPostalCode' => 19134,
			'productDescriptors' => array('rent')
		))
		->sale();
```

### Post Refund
```
$transaction = new Transaction($accessToken);

$transaction->total(200.00)
		->tokenValue($token)
		->invoiceNumber('12345')
		->clerk('5188')
		->refund();
```

### Delete Invoice
Because the invoice number must be sent in the header for this call, the method is differently structured.
```
$transaction = new Transaction($accessToken);

$transaction->deleteInvoice($randomInvoice);
```

More methods can be viewed in the tests.

## Testing

Shift4 requires certain testing to be done in order to qualify for use of their API in production. Passing tests can be found in the `tests` folder. Tests are connected to Laravel's `Log` class, which allows for ease of exporting test results for submission to Shift4.