<?php
require_once realpath(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'indexer.php');

use libs\ApaiIO\Configuration\GenericConfiguration;
use libs\ApaiIO\Operations\Search;
use libs\ApaiIO\ApaiIO;
use newznab\db\Settings;

$pdo = new Settings();

$pubkey = $pdo->getSetting('amazonpubkey');
$privkey = $pdo->getSetting('amazonprivkey');
$asstag = $pdo->getSetting('amazonassociatetag');

$conf = new GenericConfiguration();
$conf
	->setCountry('com')
	->setAccessKey($pubkey)
	->setSecretKey($privkey)
	->setAssociateTag($asstag)
	->setResponseTransformer('\libs\ApaiIO\ResponseTransformer\XmlToSimpleXmlObject');

$search = new Search();
$search->setCategory('VideoGames');
$search->setKeywords('Guilty Gear 2 Oveture');
$search->setResponseGroup(['Large']);
$search->setPage(1);

$apaiIo = new ApaiIO($conf);

$response = $apaiIo->runOperation($search);
var_dump($response);
