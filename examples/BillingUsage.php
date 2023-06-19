<?php

/*
 * PortaOne Billing JSON API wrapper usage example
 */
require __DIR__ . '/../vendor/autoload.php';

use Porta\Billing\Billing;
use Porta\Billing\Config;
use Porta\Billing\BulkOperation;

// Will use Guzzle PSR package for requests and streams
$factory = new GuzzleHttp\Psr7\HttpFactory();

// Create config object
$config = new Config(
        // Billing host (bill-sip server)
        'my-porta-one-server.com',
        // Guzzle factory for requests and streams
        $factory,
        $factory,
        // Will use PSR-18 adapter with Guzzle client, just for tests
        new \Porta\Billing\Adapters\Psr18Adapter(new GuzzleHttp\Client(['verify' => false])),
        // Will use simple php class instance cache object as we need no sesson persistance
        new \Porta\Billing\Cache\InstanceCache(),
        // And finally credentials to access billing API
        [Config::LOGIN => 'myLogin', Config::PASSWORD => 'myPass']
);

// Create the API wrapper
$billing = new Billing($config);

//Load first 10 customers from the server
echo "Starting load customer records\n";
$t = microtime(true);
$answer = $billing->call('/Customer/get_customer_list', ['limit' => 10]);
$customers = $answer['customer_list'];

echo "Loaded " . count($customers) . " customer records in " . (microtime(true) - $t) . " seconds\n";

// remove account from config object to show how it may work from stored session
$config->setAccount();

// Re-create billing object without account. But the session snjred in the cache class instance.
$billing = new Billing($config);

// Preapare bulk load of their accounts
/** @var BulkOperation[] $requests */
$requests = [];
foreach ($customers as $customer) {
    $customerId = $customer['i_customer'];
    $requests[$customerId] = new BulkOperation('Account/get_account_list', ['i_customer' => $customerId]);
}

// Bulk load of accounts. We use PSR-18 client, so it really will load one by one
// Use Guzzle advanced features may make times faster
$t = microtime(true);
$billing->callConcurrent($requests);
echo "Complete " . count($requests) . " calls in " . (microtime(true) - $t) . " seconds\n";

//Print out results
foreach ($customers as $customer) {
    /** @var BulkOperation $request */
    $request = $requests[$customer['i_customer']];

    echo "Customer '{$customer['name']}' has ";
    if (!$request->success()) {
        echo "error loading account data: " . $request->getException()->getMessage() . "\n";
        continue;
    }
    $accounts = $request->getResponse()['account_list'];
    echo count($accounts) . " account(s).\n";
    foreach ($accounts as $account) {
        echo "    Account ID: " . $account['id'] . "\n";
    }
}

// That's all folks!


