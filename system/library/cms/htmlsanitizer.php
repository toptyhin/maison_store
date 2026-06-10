<?php
namespace Cms;

final class HtmlSanitizer {
	public static function richText($html) {
		$html = (string)$html;
		if ($html === '') {
			return '';
		}

		if (is_file(DIR_SYSTEM . 'helper/HTMLPurifier.php')) {
			require_once DIR_SYSTEM . 'helper/HTMLPurifier.php';
			if (class_exists('HTMLPurifier')) {
				$config = \HTMLPurifier_Config::createDefault();
				$config->set('HTML.Allowed', 'p,b,strong,i,em,u,ul,ol,li,a[href|title|target|rel],br,span,h2,h3,h4,blockquote');
				$config->set('URI.AllowedSchemes', array('http' => true, 'https' => true, 'mailto' => true));
				$config->set('Attr.AllowedFrameTargets', array('_blank'));
				$purifier = new \HTMLPurifier($config);
				return $purifier->purify($html);
			}
		}

		return strip_tags($html, '<p><b><strong><i><em><u><ul><ol><li><a><br><span><h2><h3><h4><blockquote>');
	}

	public static function plain($text) {
		return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
	}
}
