<?php
require_once realpath(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use ApaiIO\ApaiIO;
use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\Operations\Search;
use ApaiIO\ResponseTransformer\XmlToSimpleXmlObject;
use app\models\Settings;


$pubkey = Settings::value('APIs..amazonpubkey');
$privkey = Settings::value('APIs..amazonprivkey');
$asstag = Settings::value('APIs..amazonassociatetag');

$conf = new GenericConfiguration();
$conf
	->setCountry('com')
	->setAccessKey($pubkey)
	->setSecretKey($privkey)
	->setAssociateTag($asstag)
	->setResponseTransformer(XmlToSimpleXmlObject::class);

$search = new Search();
$search->setCategory('VideoGames');
$search->setKeywords('Deus Ex Mankind Divided');
$search->setResponseGroup(['Large']);
$search->setPage(1);

$apaiIo = new ApaiIO($conf);

$response = $apaiIo->runOperation($search);

var_dump($response);
