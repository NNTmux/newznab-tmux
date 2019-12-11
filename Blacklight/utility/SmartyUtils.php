<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program (see LICENSE.txt in the base directory.  If
 * not, see:
 *
 * @link <http://www.gnu.org/licenses/>.
 * @author niel
 * @copyright 2014 nZEDb
 */
use App\Models\Category;

/**
 * Returns the value of the specified Category constant.
 *
 *
 * @param $category
 * @return mixed
 */
function getCategoryValue($category)
{
    return Category::getCategoryValue($category);
}

// Function inspired by c0r3@newznabforums adds country flags on the browse page.
/**
 * @param string $text	Text to match against.
 * @param string $page	Type of page. browse or search.
 *
 *@return bool|string
 */
function release_flag($text, $page)
{
    $code = $language = '';

    switch (true) {
        case stripos($text, 'Arabic') !== false:
            $code = 'PK';
            $language = 'Arabic';
            break;
        case stripos($text, 'Cantonese') !== false:
            $code = 'TW';
            $language = 'Cantonese';
            break;
        case preg_match('/Chinese|Mandarin|\bc[hn]\b/i', $text):
            $code = 'CN';
            $language = 'Chinese';
            break;
        case preg_match('/\bCzech\b/i', $text):
            $code = 'CZ';
            $language = 'Czech';
            break;
        case stripos($text, 'Danish') !== false:
            $code = 'DK';
            $language = 'Danish';
            break;
        case stripos($text, 'Finnish') !== false:
            $code = 'FI';
            $language = 'Finnish';
            break;
        case preg_match('/Flemish|\b(Dutch|nl)\b|NlSub/i', $text):
            $code = 'NL';
            $language = 'Dutch';
            break;
        case preg_match('/French|Vostfr|Multi/i', $text):
            $code = 'FR';
            $language = 'French';
            break;
        case preg_match('/German(bed)?|\bger\b/i', $text):
            $code = 'DE';
            $language = 'German';
            break;
        case preg_match('/\bGreek\b/i', $text):
            $code = 'GR';
            $language = 'Greek';
            break;
        case preg_match('/Hebrew|Yiddish/i', $text):
            $code = 'IL';
            $language = 'Hebrew';
            break;
        case preg_match('/\bHindi\b/i', $text):
            $code = 'IN';
            $language = 'Hindi';
            break;
        case preg_match('/Hungarian|\bhun\b/i', $text):
            $code = 'HU';
            $language = 'Hungarian';
            break;
        case preg_match('/Italian|\bita\b/i', $text):
            $code = 'IT';
            $language = 'Italian';
            break;
        case preg_match('/Japanese|\bjp\b/i', $text):
            $code = 'JP';
            $language = 'Japanese';
            break;
        case preg_match('/Korean|\bkr\b/i', $text):
            $code = 'KR';
            $language = 'Korean';
            break;
        case stripos($text, 'Norwegian') !== false:
            $code = 'NO';
            $language = 'Norwegian';
            break;
        case stripos($text, 'Polish') !== false:
            $code = 'PL';
            $language = 'Polish';
            break;
        case stripos($text, 'Portuguese') !== false:
            $code = 'PT';
            $language = 'Portugese';
            break;
        case stripos($text, 'Romanian') !== false:
            $code = 'RO';
            $language = 'Romanian';
            break;
        case stripos($text, 'Spanish') !== false:
            $code = 'ES';
            $language = 'Spanish';
            break;
        case preg_match('/Swe(dish|sub)/i', $text):
            $code = 'SE';
            $language = 'Swedish';
            break;
        case preg_match('/Tagalog|Filipino/i', $text):
            $code = 'PH';
            $language = 'Tagalog|Filipino';
            break;
        case preg_match('/\bThai\b/i', $text):
            $code = 'TH';
            $language = 'Thai';
            break;
        case stripos($text, 'Turkish') !== false:
            $code = 'TR';
            $language = 'Turkish';
            break;
        case stripos($text, 'Russian') !== false:
            $code = 'RU';
            $language = 'Russian';
            break;
        case stripos($text, 'Vietnamese') !== false:
            $code = 'VN';
            $language = 'Vietnamese';
            break;
    }

    if ($code !== '' && $page === 'browse') {
        return
            '<img title="'.$language.'" alt="'.$language.'" src="'.asset('/assets/images/flags/'.$code.'.png').'"/>';
    }

    if ($page === 'search') {
        if ($code === '') {
            return false;
        }

        return $code;
    }

    return '';
}
