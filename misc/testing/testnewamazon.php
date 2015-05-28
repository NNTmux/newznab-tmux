<?php
require_once dirname(__FILE__) . '/../../www/config.php';

use newznab\libraries\ApaiIO\Configuration\GenericConfiguration;
use newznab\libraries\ApaiIO\Operations\Search;
use newznab\libraries\ApaiIO\ApaiIO;

$s = new Settings();
$site = $s->get();

$pubkey = $site->amazonpubkey;
$privkey = $site->amazonprivkey;
$asstag = $site->amazonassociatetag;

$conf = new GenericConfiguration();
$conf
	->setCountry('com')
	->setAccessKey($pubkey)
	->setSecretKey($privkey)
	->setAssociateTag($asstag)
	->setResponseTransformer('\newznab\libraries\ApaiIO\ResponseTransformer\XmlToSimpleXmlObject');

$search = new Search();
$search->setCategory('VideoGames');
$search->setKeywords('Guilty Gear 2 Oveture');
$search->setResponseGroup(['Large']);
$search->setPage(1);

$apaiIo = new ApaiIO($conf);

$response = $apaiIo->runOperation($search);
var_dump($response);