<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use ApaiIO\ApaiIO;
use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\Operations\Search;
use App\Models\Settings;

$pubkey = Settings::settingValue('APIs..amazonpubkey');
$privkey = Settings::settingValue('APIs..amazonprivkey');
$asstag = Settings::settingValue('APIs..amazonassociatetag');

$conf = new GenericConfiguration();
$client = new \GuzzleHttp\Client();
$request = new \ApaiIO\Request\GuzzleRequest($client);

$conf
    ->setCountry('com')
    ->setAccessKey($pubkey)
    ->setSecretKey($privkey)
    ->setAssociateTag($asstag)
    ->setRequest($request)
    ->setResponseTransformer(new \ApaiIO\ResponseTransformer\XmlToSimpleXmlObject());

$search = new Search();
$search->setCategory('VideoGames');
$search->setKeywords('Deus Ex Mankind Divided');
$search->setResponseGroup(['Large']);
$search->setPage(1);

$apaiIo = new ApaiIO($conf);

$response = $apaiIo->runOperation($search);

dd($response);
