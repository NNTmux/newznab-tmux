<?php

use Illuminate\Database\Seeder;

class CountriesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        \DB::table('countries')->delete();

        \DB::table('countries')->insert([
            0 =>
            [
                'id' => 'AD',
                'iso3' => 'AND',
                'country' => 'Principality of Andorra',
            ],
            1 =>
            [
                'id' => 'AE',
                'iso3' => 'ARE',
                'country' => 'United Arab Emirates',
            ],
            2 =>
            [
                'id' => 'AF',
                'iso3' => 'AFG',
                'country' => 'Islamic Republic of Afghanistan',
            ],
            3 =>
            [
                'id' => 'AG',
                'iso3' => 'ATG',
                'country' => 'Antigua and Barbuda',
            ],
            4 =>
            [
                'id' => 'AI',
                'iso3' => 'AIA',
                'country' => 'Anguilla',
            ],
            5 =>
            [
                'id' => 'AL',
                'iso3' => 'ALB',
                'country' => 'Republic of Albania',
            ],
            6 =>
            [
                'id' => 'AM',
                'iso3' => 'ARM',
                'country' => 'Republic of Armenia',
            ],
            7 =>
            [
                'id' => 'AO',
                'iso3' => 'AGO',
                'country' => 'Republic of Angola',
            ],
            8 =>
            [
                'id' => 'AQ',
                'iso3' => 'ATA',
                'country' => 'Antarctica',
            ],
            9 =>
            [
                'id' => 'AR',
                'iso3' => 'ARG',
                'country' => 'Argentine Republic',
            ],
            10 =>
            [
                'id' => 'AS',
                'iso3' => 'ASM',
                'country' => 'American Samoa',
            ],
            11 =>
            [
                'id' => 'AT',
                'iso3' => 'AUT',
                'country' => 'Republic of Austria',
            ],
            12 =>
            [
                'id' => 'AU',
                'iso3' => 'AUS',
                'country' => 'Commonwealth of Australia',
            ],
            13 =>
            [
                'id' => 'AW',
                'iso3' => 'ABW',
                'country' => 'Aruba',
            ],
            14 =>
            [
                'id' => 'AX',
                'iso3' => 'ALA',
                'country' => 'Åland Islands',
            ],
            15 =>
            [
                'id' => 'AZ',
                'iso3' => 'AZE',
                'country' => 'Republic of Azerbaijan',
            ],
            16 =>
            [
                'id' => 'BA',
                'iso3' => 'BIH',
                'country' => 'Bosnia and Herzegovina',
            ],
            17 =>
            [
                'id' => 'BB',
                'iso3' => 'BRB',
                'country' => 'Barbados',
            ],
            18 =>
            [
                'id' => 'BD',
                'iso3' => 'BGD',
                'country' => 'People\'s Republic of Bangladesh',
            ],
            19 =>
            [
                'id' => 'BE',
                'iso3' => 'BEL',
                'country' => 'Kingdom of Belgium',
            ],
            20 =>
            [
                'id' => 'BF',
                'iso3' => 'BFA',
                'country' => 'Burkina Faso',
            ],
            21 =>
            [
                'id' => 'BG',
                'iso3' => 'BGR',
                'country' => 'Republic of Bulgaria',
            ],
            22 =>
            [
                'id' => 'BH',
                'iso3' => 'BHR',
                'country' => 'Kingdom of Bahrain',
            ],
            23 =>
            [
                'id' => 'BI',
                'iso3' => 'BDI',
                'country' => 'Republic of Burundi',
            ],
            24 =>
            [
                'id' => 'BJ',
                'iso3' => 'BEN',
                'country' => 'Republic of Benin',
            ],
            25 =>
            [
                'id' => 'BL',
                'iso3' => 'BLM',
                'country' => 'Saint Barthélemy',
            ],
            26 =>
            [
                'id' => 'BM',
                'iso3' => 'BMU',
                'country' => 'Bermuda Islands',
            ],
            27 =>
            [
                'id' => 'BN',
                'iso3' => 'BRN',
                'country' => 'Brunei Darussalam',
            ],
            28 =>
            [
                'id' => 'BO',
                'iso3' => 'BOL',
                'country' => 'Plurinational State of Bolivia',
            ],
            29 =>
            [
                'id' => 'BQ',
                'iso3' => 'BES',
                'country' => 'Bonaire, Sint Eustatius and Saba',
            ],
            30 =>
            [
                'id' => 'BR',
                'iso3' => 'BRA',
                'country' => 'Federative Republic of Brazil',
            ],
            31 =>
            [
                'id' => 'BS',
                'iso3' => 'BHS',
                'country' => 'Commonwealth of The Bahamas',
            ],
            32 =>
            [
                'id' => 'BT',
                'iso3' => 'BTN',
                'country' => 'Kingdom of Bhutan',
            ],
            33 =>
            [
                'id' => 'BV',
                'iso3' => 'BVT',
                'country' => 'Bouvet Island',
            ],
            34 =>
            [
                'id' => 'BW',
                'iso3' => 'BWA',
                'country' => 'Republic of Botswana',
            ],
            35 =>
            [
                'id' => 'BY',
                'iso3' => 'BLR',
                'country' => 'Republic of Belarus',
            ],
            36 =>
            [
                'id' => 'BZ',
                'iso3' => 'BLZ',
                'country' => 'Belize',
            ],
            37 =>
            [
                'id' => 'CA',
                'iso3' => 'CAN',
                'country' => 'Canada',
            ],
            38 =>
            [
                'id' => 'CC',
                'iso3' => 'CCK',
            'country' => 'Cocos (Keeling) Islands',
            ],
            39 =>
            [
                'id' => 'CD',
                'iso3' => 'COD',
                'country' => 'Democratic Republic of the Congo',
            ],
            40 =>
            [
                'id' => 'CF',
                'iso3' => 'CAF',
                'country' => 'Central African Republic',
            ],
            41 =>
            [
                'id' => 'CG',
                'iso3' => 'COG',
                'country' => 'Republic of the Congo',
            ],
            42 =>
            [
                'id' => 'CH',
                'iso3' => 'CHE',
                'country' => 'Swiss Confederation',
            ],
            43 =>
            [
                'id' => 'CI',
                'iso3' => 'CIV',
            'country' => 'Republic of Côte D\'Ivoire (Ivory Coast)',
            ],
            44 =>
            [
                'id' => 'CK',
                'iso3' => 'COK',
                'country' => 'Cook Islands',
            ],
            45 =>
            [
                'id' => 'CL',
                'iso3' => 'CHL',
                'country' => 'Republic of Chile',
            ],
            46 =>
            [
                'id' => 'CM',
                'iso3' => 'CMR',
                'country' => 'Republic of Cameroon',
            ],
            47 =>
            [
                'id' => 'CN',
                'iso3' => 'CHN',
                'country' => 'People\'s Republic of China',
            ],
            48 =>
            [
                'id' => 'CO',
                'iso3' => 'COL',
                'country' => 'Republic of Colombia',
            ],
            49 =>
            [
                'id' => 'CR',
                'iso3' => 'CRI',
                'country' => 'Republic of Costa Rica',
            ],
            50 =>
            [
                'id' => 'CU',
                'iso3' => 'CUB',
                'country' => 'Republic of Cuba',
            ],
            51 =>
            [
                'id' => 'CV',
                'iso3' => 'CPV',
                'country' => 'Republic of Cape Verde',
            ],
            52 =>
            [
                'id' => 'CW',
                'iso3' => 'CUW',
                'country' => 'Curaçao',
            ],
            53 =>
            [
                'id' => 'CX',
                'iso3' => 'CXR',
                'country' => 'Christmas Island',
            ],
            54 =>
            [
                'id' => 'CY',
                'iso3' => 'CYP',
                'country' => 'Republic of Cyprus',
            ],
            55 =>
            [
                'id' => 'CZ',
                'iso3' => 'CZE',
                'country' => 'Czech Republic',
            ],
            56 =>
            [
                'id' => 'DE',
                'iso3' => 'DEU',
                'country' => 'Federal Republic of Germany',
            ],
            57 =>
            [
                'id' => 'DJ',
                'iso3' => 'DJI',
                'country' => 'Republic of Djibouti',
            ],
            58 =>
            [
                'id' => 'DK',
                'iso3' => 'DNK',
                'country' => 'Kingdom of Denmark',
            ],
            59 =>
            [
                'id' => 'DM',
                'iso3' => 'DMA',
                'country' => 'Commonwealth of Dominica',
            ],
            60 =>
            [
                'id' => 'DO',
                'iso3' => 'DOM',
                'country' => 'Dominican Republic',
            ],
            61 =>
            [
                'id' => 'DZ',
                'iso3' => 'DZA',
                'country' => 'People\'s Democratic Republic of Algeria',
            ],
            62 =>
            [
                'id' => 'EC',
                'iso3' => 'ECU',
                'country' => 'Republic of Ecuador',
            ],
            63 =>
            [
                'id' => 'EE',
                'iso3' => 'EST',
                'country' => 'Republic of Estonia',
            ],
            64 =>
            [
                'id' => 'EG',
                'iso3' => 'EGY',
                'country' => 'Arab Republic of Egypt',
            ],
            65 =>
            [
                'id' => 'EH',
                'iso3' => 'ESH',
                'country' => 'Western Sahara',
            ],
            66 =>
            [
                'id' => 'ER',
                'iso3' => 'ERI',
                'country' => 'State of Eritrea',
            ],
            67 =>
            [
                'id' => 'ES',
                'iso3' => 'ESP',
                'country' => 'Kingdom of Spain',
            ],
            68 =>
            [
                'id' => 'ET',
                'iso3' => 'ETH',
                'country' => 'Federal Democratic Republic of Ethiopia',
            ],
            69 =>
            [
                'id' => 'FI',
                'iso3' => 'FIN',
                'country' => 'Republic of Finland',
            ],
            70 =>
            [
                'id' => 'FJ',
                'iso3' => 'FJI',
                'country' => 'Republic of Fiji',
            ],
            71 =>
            [
                'id' => 'FK',
                'iso3' => 'FLK',
            'country' => 'The Falkland Islands (Malvinas)',
            ],
            72 =>
            [
                'id' => 'FM',
                'iso3' => 'FSM',
                'country' => 'Federated States of Micronesia',
            ],
            73 =>
            [
                'id' => 'FO',
                'iso3' => 'FRO',
                'country' => 'The Faroe Islands',
            ],
            74 =>
            [
                'id' => 'FR',
                'iso3' => 'FRA',
                'country' => 'French Republic',
            ],
            75 =>
            [
                'id' => 'GA',
                'iso3' => 'GAB',
                'country' => 'Gabonese Republic',
            ],
            76 =>
            [
                'id' => 'GB',
                'iso3' => 'GBR',
                'country' => 'United Kingdom of Great Britain and Nothern Ireland',
            ],
            77 =>
            [
                'id' => 'GD',
                'iso3' => 'GRD',
                'country' => 'Grenada',
            ],
            78 =>
            [
                'id' => 'GE',
                'iso3' => 'GEO',
                'country' => 'Georgia',
            ],
            79 =>
            [
                'id' => 'GF',
                'iso3' => 'GUF',
                'country' => 'French Guiana',
            ],
            80 =>
            [
                'id' => 'GG',
                'iso3' => 'GGY',
                'country' => 'Guernsey',
            ],
            81 =>
            [
                'id' => 'GH',
                'iso3' => 'GHA',
                'country' => 'Republic of Ghana',
            ],
            82 =>
            [
                'id' => 'GI',
                'iso3' => 'GIB',
                'country' => 'Gibraltar',
            ],
            83 =>
            [
                'id' => 'GL',
                'iso3' => 'GRL',
                'country' => 'Greenland',
            ],
            84 =>
            [
                'id' => 'GM',
                'iso3' => 'GMB',
                'country' => 'Republic of The Gambia',
            ],
            85 =>
            [
                'id' => 'GN',
                'iso3' => 'GIN',
                'country' => 'Republic of Guinea',
            ],
            86 =>
            [
                'id' => 'GP',
                'iso3' => 'GLP',
                'country' => 'Guadeloupe',
            ],
            87 =>
            [
                'id' => 'GQ',
                'iso3' => 'GNQ',
                'country' => 'Republic of Equatorial Guinea',
            ],
            88 =>
            [
                'id' => 'GR',
                'iso3' => 'GRC',
                'country' => 'Hellenic Republic',
            ],
            89 =>
            [
                'id' => 'GS',
                'iso3' => 'SGS',
                'country' => 'South Georgia and the South Sandwich Islands',
            ],
            90 =>
            [
                'id' => 'GT',
                'iso3' => 'GTM',
                'country' => 'Republic of Guatemala',
            ],
            91 =>
            [
                'id' => 'GU',
                'iso3' => 'GUM',
                'country' => 'Guam',
            ],
            92 =>
            [
                'id' => 'GW',
                'iso3' => 'GNB',
                'country' => 'Republic of Guinea-Bissau',
            ],
            93 =>
            [
                'id' => 'GY',
                'iso3' => 'GUY',
                'country' => 'Co-operative Republic of Guyana',
            ],
            94 =>
            [
                'id' => 'HK',
                'iso3' => 'HKG',
                'country' => 'Hong Kong',
            ],
            95 =>
            [
                'id' => 'HM',
                'iso3' => 'HMD',
                'country' => 'Heard Island and McDonald Islands',
            ],
            96 =>
            [
                'id' => 'HN',
                'iso3' => 'HND',
                'country' => 'Republic of Honduras',
            ],
            97 =>
            [
                'id' => 'HR',
                'iso3' => 'HRV',
                'country' => 'Republic of Croatia',
            ],
            98 =>
            [
                'id' => 'HT',
                'iso3' => 'HTI',
                'country' => 'Republic of Haiti',
            ],
            99 =>
            [
                'id' => 'HU',
                'iso3' => 'HUN',
                'country' => 'Hungary',
            ],
            100 =>
            [
                'id' => 'ID',
                'iso3' => 'IDN',
                'country' => 'Republic of Indonesia',
            ],
            101 =>
            [
                'id' => 'IE',
                'iso3' => 'IRL',
                'country' => 'Ireland',
            ],
            102 =>
            [
                'id' => 'IL',
                'iso3' => 'ISR',
                'country' => 'State of Israel',
            ],
            103 =>
            [
                'id' => 'IM',
                'iso3' => 'IMN',
                'country' => 'Isle of Man',
            ],
            104 =>
            [
                'id' => 'IN',
                'iso3' => 'IND',
                'country' => 'Republic of India',
            ],
            105 =>
            [
                'id' => 'IO',
                'iso3' => 'IOT',
                'country' => 'British Indian Ocean Territory',
            ],
            106 =>
            [
                'id' => 'IQ',
                'iso3' => 'IRQ',
                'country' => 'Republic of Iraq',
            ],
            107 =>
            [
                'id' => 'IR',
                'iso3' => 'IRN',
                'country' => 'Islamic Republic of Iran',
            ],
            108 =>
            [
                'id' => 'IS',
                'iso3' => 'ISL',
                'country' => 'Republic of Iceland',
            ],
            109 =>
            [
                'id' => 'IT',
                'iso3' => 'ITA',
                'country' => 'Italian Republic',
            ],
            110 =>
            [
                'id' => 'JE',
                'iso3' => 'JEY',
                'country' => 'The Bailiwick of Jersey',
            ],
            111 =>
            [
                'id' => 'JM',
                'iso3' => 'JAM',
                'country' => 'Jamaica',
            ],
            112 =>
            [
                'id' => 'JO',
                'iso3' => 'JOR',
                'country' => 'Hashemite Kingdom of Jordan',
            ],
            113 =>
            [
                'id' => 'JP',
                'iso3' => 'JPN',
                'country' => 'Japan',
            ],
            114 =>
            [
                'id' => 'KE',
                'iso3' => 'KEN',
                'country' => 'Republic of Kenya',
            ],
            115 =>
            [
                'id' => 'KG',
                'iso3' => 'KGZ',
                'country' => 'Kyrgyz Republic',
            ],
            116 =>
            [
                'id' => 'KH',
                'iso3' => 'KHM',
                'country' => 'Kingdom of Cambodia',
            ],
            117 =>
            [
                'id' => 'KI',
                'iso3' => 'KIR',
                'country' => 'Republic of Kiribati',
            ],
            118 =>
            [
                'id' => 'KM',
                'iso3' => 'COM',
                'country' => 'Union of the Comoros',
            ],
            119 =>
            [
                'id' => 'KN',
                'iso3' => 'KNA',
                'country' => 'Federation of Saint Christopher and Nevis',
            ],
            120 =>
            [
                'id' => 'KP',
                'iso3' => 'PRK',
                'country' => 'Democratic People\'s Republic of Korea',
            ],
            121 =>
            [
                'id' => 'KR',
                'iso3' => 'KOR',
                'country' => 'Republic of Korea',
            ],
            122 =>
            [
                'id' => 'KW',
                'iso3' => 'KWT',
                'country' => 'State of Kuwait',
            ],
            123 =>
            [
                'id' => 'KY',
                'iso3' => 'CYM',
                'country' => 'The Cayman Islands',
            ],
            124 =>
            [
                'id' => 'KZ',
                'iso3' => 'KAZ',
                'country' => 'Republic of Kazakhstan',
            ],
            125 =>
            [
                'id' => 'LA',
                'iso3' => 'LAO',
                'country' => 'Lao People\'s Democratic Republic',
            ],
            126 =>
            [
                'id' => 'LB',
                'iso3' => 'LBN',
                'country' => 'Republic of Lebanon',
            ],
            127 =>
            [
                'id' => 'LC',
                'iso3' => 'LCA',
                'country' => 'Saint Lucia',
            ],
            128 =>
            [
                'id' => 'LI',
                'iso3' => 'LIE',
                'country' => 'Principality of Liechtenstein',
            ],
            129 =>
            [
                'id' => 'LK',
                'iso3' => 'LKA',
                'country' => 'Democratic Socialist Republic of Sri Lanka',
            ],
            130 =>
            [
                'id' => 'LR',
                'iso3' => 'LBR',
                'country' => 'Republic of Liberia',
            ],
            131 =>
            [
                'id' => 'LS',
                'iso3' => 'LSO',
                'country' => 'Kingdom of Lesotho',
            ],
            132 =>
            [
                'id' => 'LT',
                'iso3' => 'LTU',
                'country' => 'Republic of Lithuania',
            ],
            133 =>
            [
                'id' => 'LU',
                'iso3' => 'LUX',
                'country' => 'Grand Duchy of Luxembourg',
            ],
            134 =>
            [
                'id' => 'LV',
                'iso3' => 'LVA',
                'country' => 'Republic of Latvia',
            ],
            135 =>
            [
                'id' => 'LY',
                'iso3' => 'LBY',
                'country' => 'Libya',
            ],
            136 =>
            [
                'id' => 'MA',
                'iso3' => 'MAR',
                'country' => 'Kingdom of Morocco',
            ],
            137 =>
            [
                'id' => 'MC',
                'iso3' => 'MCO',
                'country' => 'Principality of Monaco',
            ],
            138 =>
            [
                'id' => 'MD',
                'iso3' => 'MDA',
                'country' => 'Republic of Moldova',
            ],
            139 =>
            [
                'id' => 'ME',
                'iso3' => 'MNE',
                'country' => 'Montenegro',
            ],
            140 =>
            [
                'id' => 'MF',
                'iso3' => 'MAF',
                'country' => 'Saint Martin',
            ],
            141 =>
            [
                'id' => 'MG',
                'iso3' => 'MDG',
                'country' => 'Republic of Madagascar',
            ],
            142 =>
            [
                'id' => 'MH',
                'iso3' => 'MHL',
                'country' => 'Republic of the Marshall Islands',
            ],
            143 =>
            [
                'id' => 'MK',
                'iso3' => 'MKD',
                'country' => 'The Former Yugoslav Republic of Macedonia',
            ],
            144 =>
            [
                'id' => 'ML',
                'iso3' => 'MLI',
                'country' => 'Republic of Mali',
            ],
            145 =>
            [
                'id' => 'MM',
                'iso3' => 'MMR',
                'country' => 'Republic of the Union of Myanmar',
            ],
            146 =>
            [
                'id' => 'MN',
                'iso3' => 'MNG',
                'country' => 'Mongolia',
            ],
            147 =>
            [
                'id' => 'MO',
                'iso3' => 'MAC',
                'country' => 'The Macao Special Administrative Region',
            ],
            148 =>
            [
                'id' => 'MP',
                'iso3' => 'MNP',
                'country' => 'Northern Mariana Islands',
            ],
            149 =>
            [
                'id' => 'MQ',
                'iso3' => 'MTQ',
                'country' => 'Martinique',
            ],
            150 =>
            [
                'id' => 'MR',
                'iso3' => 'MRT',
                'country' => 'Islamic Republic of Mauritania',
            ],
            151 =>
            [
                'id' => 'MS',
                'iso3' => 'MSR',
                'country' => 'Montserrat',
            ],
            152 =>
            [
                'id' => 'MT',
                'iso3' => 'MLT',
                'country' => 'Republic of Malta',
            ],
            153 =>
            [
                'id' => 'MU',
                'iso3' => 'MUS',
                'country' => 'Republic of Mauritius',
            ],
            154 =>
            [
                'id' => 'MV',
                'iso3' => 'MDV',
                'country' => 'Republic of Maldives',
            ],
            155 =>
            [
                'id' => 'MW',
                'iso3' => 'MWI',
                'country' => 'Republic of Malawi',
            ],
            156 =>
            [
                'id' => 'MX',
                'iso3' => 'MEX',
                'country' => 'United Mexican States',
            ],
            157 =>
            [
                'id' => 'MY',
                'iso3' => 'MYS',
                'country' => 'Malaysia',
            ],
            158 =>
            [
                'id' => 'MZ',
                'iso3' => 'MOZ',
                'country' => 'Republic of Mozambique',
            ],
            159 =>
            [
                'id' => 'NA',
                'iso3' => 'NAM',
                'country' => 'Republic of Namibia',
            ],
            160 =>
            [
                'id' => 'NC',
                'iso3' => 'NCL',
                'country' => 'New Caledonia',
            ],
            161 =>
            [
                'id' => 'NE',
                'iso3' => 'NER',
                'country' => 'Republic of Niger',
            ],
            162 =>
            [
                'id' => 'NF',
                'iso3' => 'NFK',
                'country' => 'Norfolk Island',
            ],
            163 =>
            [
                'id' => 'NG',
                'iso3' => 'NGA',
                'country' => 'Federal Republic of Nigeria',
            ],
            164 =>
            [
                'id' => 'NI',
                'iso3' => 'NIC',
                'country' => 'Republic of Nicaragua',
            ],
            165 =>
            [
                'id' => 'NL',
                'iso3' => 'NLD',
                'country' => 'Kingdom of the Netherlands',
            ],
            166 =>
            [
                'id' => 'NO',
                'iso3' => 'NOR',
                'country' => 'Kingdom of Norway',
            ],
            167 =>
            [
                'id' => 'NP',
                'iso3' => 'NPL',
                'country' => 'Federal Democratic Republic of Nepal',
            ],
            168 =>
            [
                'id' => 'NR',
                'iso3' => 'NRU',
                'country' => 'Republic of Nauru',
            ],
            169 =>
            [
                'id' => 'NU',
                'iso3' => 'NIU',
                'country' => 'Niue',
            ],
            170 =>
            [
                'id' => 'NZ',
                'iso3' => 'NZL',
                'country' => 'New Zealand',
            ],
            171 =>
            [
                'id' => 'OM',
                'iso3' => 'OMN',
                'country' => 'Sultanate of Oman',
            ],
            172 =>
            [
                'id' => 'PA',
                'iso3' => 'PAN',
                'country' => 'Republic of Panama',
            ],
            173 =>
            [
                'id' => 'PE',
                'iso3' => 'PER',
                'country' => 'Republic of Peru',
            ],
            174 =>
            [
                'id' => 'PF',
                'iso3' => 'PYF',
                'country' => 'French Polynesia',
            ],
            175 =>
            [
                'id' => 'PG',
                'iso3' => 'PNG',
                'country' => 'Independent State of Papua New Guinea',
            ],
            176 =>
            [
                'id' => 'PH',
                'iso3' => 'PHL',
                'country' => 'Republic of the Philippines',
            ],
            177 =>
            [
                'id' => 'PK',
                'iso3' => 'PAK',
                'country' => 'Islamic Republic of Pakistan',
            ],
            178 =>
            [
                'id' => 'PL',
                'iso3' => 'POL',
                'country' => 'Republic of Poland',
            ],
            179 =>
            [
                'id' => 'PM',
                'iso3' => 'SPM',
                'country' => 'Saint Pierre and Miquelon',
            ],
            180 =>
            [
                'id' => 'PN',
                'iso3' => 'PCN',
                'country' => 'Pitcairn',
            ],
            181 =>
            [
                'id' => 'PR',
                'iso3' => 'PRI',
                'country' => 'Commonwealth of Puerto Rico',
            ],
            182 =>
            [
                'id' => 'PS',
                'iso3' => 'PSE',
            'country' => 'State of Palestine (or Occupied Palestinian Territory)',
            ],
            183 =>
            [
                'id' => 'PT',
                'iso3' => 'PRT',
                'country' => 'Portuguese Republic',
            ],
            184 =>
            [
                'id' => 'PW',
                'iso3' => 'PLW',
                'country' => 'Republic of Palau',
            ],
            185 =>
            [
                'id' => 'PY',
                'iso3' => 'PRY',
                'country' => 'Republic of Paraguay',
            ],
            186 =>
            [
                'id' => 'QA',
                'iso3' => 'QAT',
                'country' => 'State of Qatar',
            ],
            187 =>
            [
                'id' => 'RE',
                'iso3' => 'REU',
                'country' => 'Réunion',
            ],
            188 =>
            [
                'id' => 'RO',
                'iso3' => 'ROU',
                'country' => 'Romania',
            ],
            189 =>
            [
                'id' => 'RS',
                'iso3' => 'SRB',
                'country' => 'Republic of Serbia',
            ],
            190 =>
            [
                'id' => 'RU',
                'iso3' => 'RUS',
                'country' => 'Russian Federation',
            ],
            191 =>
            [
                'id' => 'RW',
                'iso3' => 'RWA',
                'country' => 'Republic of Rwanda',
            ],
            192 =>
            [
                'id' => 'SA',
                'iso3' => 'SAU',
                'country' => 'Kingdom of Saudi Arabia',
            ],
            193 =>
            [
                'id' => 'SB',
                'iso3' => 'SLB',
                'country' => 'Solomon Islands',
            ],
            194 =>
            [
                'id' => 'SC',
                'iso3' => 'SYC',
                'country' => 'Republic of Seychelles',
            ],
            195 =>
            [
                'id' => 'SD',
                'iso3' => 'SDN',
                'country' => 'Republic of the Sudan',
            ],
            196 =>
            [
                'id' => 'SE',
                'iso3' => 'SWE',
                'country' => 'Kingdom of Sweden',
            ],
            197 =>
            [
                'id' => 'SG',
                'iso3' => 'SGP',
                'country' => 'Republic of Singapore',
            ],
            198 =>
            [
                'id' => 'SH',
                'iso3' => 'SHN',
                'country' => 'Saint Helena, Ascension and Tristan da Cunha',
            ],
            199 =>
            [
                'id' => 'SI',
                'iso3' => 'SVN',
                'country' => 'Republic of Slovenia',
            ],
            200 =>
            [
                'id' => 'SJ',
                'iso3' => 'SJM',
                'country' => 'Svalbard and Jan Mayen',
            ],
            201 =>
            [
                'id' => 'SK',
                'iso3' => 'SVK',
                'country' => 'Slovak Republic',
            ],
            202 =>
            [
                'id' => 'SL',
                'iso3' => 'SLE',
                'country' => 'Republic of Sierra Leone',
            ],
            203 =>
            [
                'id' => 'SM',
                'iso3' => 'SMR',
                'country' => 'Republic of San Marino',
            ],
            204 =>
            [
                'id' => 'SN',
                'iso3' => 'SEN',
                'country' => 'Republic of Senegal',
            ],
            205 =>
            [
                'id' => 'SO',
                'iso3' => 'SOM',
                'country' => 'Somali Republic',
            ],
            206 =>
            [
                'id' => 'SR',
                'iso3' => 'SUR',
                'country' => 'Republic of Suriname',
            ],
            207 =>
            [
                'id' => 'SS',
                'iso3' => 'SSD',
                'country' => 'Republic of South Sudan',
            ],
            208 =>
            [
                'id' => 'ST',
                'iso3' => 'STP',
                'country' => 'Democratic Republic of São Tomé and Príncipe',
            ],
            209 =>
            [
                'id' => 'SV',
                'iso3' => 'SLV',
                'country' => 'Republic of El Salvador',
            ],
            210 =>
            [
                'id' => 'SX',
                'iso3' => 'SXM',
                'country' => 'Sint Maarten',
            ],
            211 =>
            [
                'id' => 'SY',
                'iso3' => 'SYR',
                'country' => 'Syrian Arab Republic',
            ],
            212 =>
            [
                'id' => 'SZ',
                'iso3' => 'SWZ',
                'country' => 'Kingdom of Swaziland',
            ],
            213 =>
            [
                'id' => 'TC',
                'iso3' => 'TCA',
                'country' => 'Turks and Caicos Islands',
            ],
            214 =>
            [
                'id' => 'TD',
                'iso3' => 'TCD',
                'country' => 'Republic of Chad',
            ],
            215 =>
            [
                'id' => 'TF',
                'iso3' => 'ATF',
                'country' => 'French Southern Territories',
            ],
            216 =>
            [
                'id' => 'TG',
                'iso3' => 'TGO',
                'country' => 'Togolese Republic',
            ],
            217 =>
            [
                'id' => 'TH',
                'iso3' => 'THA',
                'country' => 'Kingdom of Thailand',
            ],
            218 =>
            [
                'id' => 'TJ',
                'iso3' => 'TJK',
                'country' => 'Republic of Tajikistan',
            ],
            219 =>
            [
                'id' => 'TK',
                'iso3' => 'TKL',
                'country' => 'Tokelau',
            ],
            220 =>
            [
                'id' => 'TL',
                'iso3' => 'TLS',
                'country' => 'Democratic Republic of Timor-Leste',
            ],
            221 =>
            [
                'id' => 'TM',
                'iso3' => 'TKM',
                'country' => 'Turkmenistan',
            ],
            222 =>
            [
                'id' => 'TN',
                'iso3' => 'TUN',
                'country' => 'Republic of Tunisia',
            ],
            223 =>
            [
                'id' => 'TO',
                'iso3' => 'TON',
                'country' => 'Kingdom of Tonga',
            ],
            224 =>
            [
                'id' => 'TR',
                'iso3' => 'TUR',
                'country' => 'Republic of Turkey',
            ],
            225 =>
            [
                'id' => 'TT',
                'iso3' => 'TTO',
                'country' => 'Republic of Trinidad and Tobago',
            ],
            226 =>
            [
                'id' => 'TV',
                'iso3' => 'TUV',
                'country' => 'Tuvalu',
            ],
            227 =>
            [
                'id' => 'TW',
                'iso3' => 'TWN',
            'country' => 'Republic of China (Taiwan)',
            ],
            228 =>
            [
                'id' => 'TZ',
                'iso3' => 'TZA',
                'country' => 'United Republic of Tanzania',
            ],
            229 =>
            [
                'id' => 'UA',
                'iso3' => 'UKR',
                'country' => 'Ukraine',
            ],
            230 =>
            [
                'id' => 'UG',
                'iso3' => 'UGA',
                'country' => 'Republic of Uganda',
            ],
            231 =>
            [
                'id' => 'UM',
                'iso3' => 'UMI',
                'country' => 'United States Minor Outlying Islands',
            ],
            232 =>
            [
                'id' => 'US',
                'iso3' => 'USA',
                'country' => 'United States of America',
            ],
            233 =>
            [
                'id' => 'UY',
                'iso3' => 'URY',
                'country' => 'Eastern Republic of Uruguay',
            ],
            234 =>
            [
                'id' => 'UZ',
                'iso3' => 'UZB',
                'country' => 'Republic of Uzbekistan',
            ],
            235 =>
            [
                'id' => 'VA',
                'iso3' => 'VAT',
                'country' => 'State of the Vatican City',
            ],
            236 =>
            [
                'id' => 'VC',
                'iso3' => 'VCT',
                'country' => 'Saint Vincent and the Grenadines',
            ],
            237 =>
            [
                'id' => 'VE',
                'iso3' => 'VEN',
                'country' => 'Bolivarian Republic of Venezuela',
            ],
            238 =>
            [
                'id' => 'VG',
                'iso3' => 'VGB',
                'country' => 'British Virgin Islands',
            ],
            239 =>
            [
                'id' => 'VI',
                'iso3' => 'VIR',
                'country' => 'Virgin Islands of the United States',
            ],
            240 =>
            [
                'id' => 'VN',
                'iso3' => 'VNM',
                'country' => 'Socialist Republic of Vietnam',
            ],
            241 =>
            [
                'id' => 'VU',
                'iso3' => 'VUT',
                'country' => 'Republic of Vanuatu',
            ],
            242 =>
            [
                'id' => 'WF',
                'iso3' => 'WLF',
                'country' => 'Wallis and Futuna',
            ],
            243 =>
            [
                'id' => 'WS',
                'iso3' => 'WSM',
                'country' => 'Independent State of Samoa',
            ],
            244 =>
            [
                'id' => 'XK',
                'iso3' => 'XKX',
                'country' => 'Republic of Kosovo',
            ],
            245 =>
            [
                'id' => 'YE',
                'iso3' => 'YEM',
                'country' => 'Republic of Yemen',
            ],
            246 =>
            [
                'id' => 'YT',
                'iso3' => 'MYT',
                'country' => 'Mayotte',
            ],
            247 =>
            [
                'id' => 'ZA',
                'iso3' => 'ZAF',
                'country' => 'Republic of South Africa',
            ],
            248 =>
            [
                'id' => 'ZM',
                'iso3' => 'ZMB',
                'country' => 'Republic of Zambia',
            ],
            249 =>
            [
                'id' => 'ZW',
                'iso3' => 'ZWE',
                'country' => 'Republic of Zimbabwe',
            ],
        ]);
    }
}
