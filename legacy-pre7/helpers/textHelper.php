<?php

/**
 * This file is part of getQuestionInformation plugin
 * Purify question and answer text for plugin usage : grid header, grid value, list label, title and data-content attribute.
 * Same purification for all LimeSurvey versions, one purifier instance (creating it is slow),
 * and result kept for current request (same question text is used for each subquestion).
 * @license AGPL v3
 */

namespace getQuestionInformation\helpers;

use CHtmlPurifier;

class textHelper
{
    /** @var CHtmlPurifier|null for HTML */
    private static $htmlPurifier = null;

    /** @var CHtmlPurifier|null for text without any HTML */
    private static $textPurifier = null;

    /** @var string[] purified HTML by original text */
    private static $purified = [];

    /** @var string[] text by original text */
    private static $flatten = [];

    /**
     * Return safe HTML : HTMLPurifier default (no script, no iframe, no form, no event, no data uri),
     * link opened in a new tab with noopener and noreferrer (admin grid must not be left).
     * @param string $sHtml
     * @return string
     */
    public static function purified($sHtml)
    {
        $sHtml = (string) $sHtml;
        if (!isset(self::$purified[$sHtml])) {
            if (is_null(self::$htmlPurifier)) {
                self::$htmlPurifier = new CHtmlPurifier();
                self::$htmlPurifier->options = array(
                    'HTML.TargetBlank' => true,
                );
            }
            self::$purified[$sHtml] = (string) self::$htmlPurifier->purify($sHtml);
        }
        return self::$purified[$sHtml];
    }

    /**
     * Return the text without any HTML, new line replaced by space
     * HTML entities are kept : result can be used directly in HTML, but need encoding for javascript
     * @param string $sHtml
     * @return string
     */
    public static function flatten($sHtml)
    {
        $sHtml = (string) $sHtml;
        if (!isset(self::$flatten[$sHtml])) {
            if (is_null(self::$textPurifier)) {
                self::$textPurifier = new CHtmlPurifier();
                self::$textPurifier->options = array(
                    'HTML.Allowed' => '',
                    'Output.Newline' => ' ',
                );
            }
            self::$flatten[$sHtml] = (string) self::$textPurifier->purify($sHtml);
        }
        return self::$flatten[$sHtml];
    }

    /**
     * Same than core viewHelper::flatEllipsizeText with plugin purifier
     * @param string $sString
     * @param boolean $bFlat
     * @param integer $iAbbreviated max length, 0 or false : don't abbreviated
     * @param string $sEllipsis
     * @param float $fPosition position of ellipsis (0 to 1)
     * @return string
     */
    public static function flatEllipsizeText($sString, $bFlat = true, $iAbbreviated = 0, $sEllipsis = '...', $fPosition = 1)
    {
        $sString = (string) $sString;
        if ($bFlat || $iAbbreviated) {
            $sString = self::flatten($sString);
        }
        if ($iAbbreviated > 0) {
            $sString = ellipsize($sString, $iAbbreviated, $fPosition, $sEllipsis);
        }
        return $sString;
    }
}
