<?php

namespace nntmux;

use app\models\Settings;
use nntmux\db\DB;


class ReleasesMultiGroup
{

	/**
	 * @var array of MGR groups
	 */
	public static $mgrGroups = [
		 'alt.binaries.amazing',
		 'alt.binaries.ath',
		 'alt.binaries.bloaf',
		 'alt.binaries.british.drama',
		 'alt.binaries.chello',
		 'alt.binaries.etc',
		 'alt.binaries.font',
		 'alt.binaries.misc',
		 'alt.binaries.tatu'
	];

	/**
	 * @var array of MGR posters
	 */
	public static $mgrPosterNames = [
		'mmmq@meh.com',
		'buymore@suprnova.com',
		'pfc@p0rnFuscated.com',
		'mq@meh.com'
	];


	/**
	 * ReleasesMultiGroup constructor.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'ColorCLI'            => null,
			'Groups'              => null,
			'Settings'            => null,
		];
		$options += $defaults;

		$this->_pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
		$this->_groups = ($options['Groups'] instanceof Groups ? $options['Groups'] : new Groups(['Settings' => $this->_pdo]));
		$this->_colorCLI = ($options['ColorCLI'] instanceof ColorCLI ? $options['ColorCLI'] : new ColorCLI());
	}

	/**
	 * @param $fromName
	 *
	 * @return bool
	 */
	public static function isMultiGroup($fromName)
	{
		return in_array($fromName, self::$mgrPosterNames);
	}

	public function insertCollections($header, $matches, $fileCount, $now)
	{
		$collectionID = $this->_pdo->queryInsert(
			sprintf("
							INSERT INTO mgr_collections (subject, fromname, date, xref, group_id,
								totalfiles, collectionhash, dateadded)
							VALUES (%s, %s, FROM_UNIXTIME(%s), %s, -1, %d, '%s', NOW())
							ON DUPLICATE KEY UPDATE dateadded = NOW(), noise = '%s'",
				$this->_pdo->escapeString(substr(utf8_encode($matches[1]), 0, 255)),
				$this->_pdo->escapeString(utf8_encode($header['From'])),
				(is_numeric($header['Date']) ? ($header['Date'] > $now ? $now : $header['Date']) : $now),
				$this->_pdo->escapeString(explode(',', self::$mgrGroups)),
				$fileCount[3],
				sha1($header['CollectionKey']),
				bin2hex(openssl_random_pseudo_bytes(16))
			)
		);

		return $collectionID;
	}

	public function insertBinaries($collectionID, $header, $matches, $fileCount)
	{
		$binaryID = $this->_pdo->queryInsert(
			sprintf("
						INSERT INTO mgr_binaries (binaryhash, name, collection_id, totalparts, currentparts, filenumber, partsize)
						VALUES (UNHEX('%s'), %s, %d, %d, 1, %d, %d)
						ON DUPLICATE KEY UPDATE currentparts = currentparts + 1, partsize = partsize + %d",
				md5($matches[1] . $header['From'] . -1),
				$this->_pdo->escapeString(utf8_encode($matches[1])),
				$collectionID,
				$matches[3],
				$fileCount[1],
				$header['Bytes'],
				$header['Bytes']
			)
		);

		return $binaryID;
	}
}
