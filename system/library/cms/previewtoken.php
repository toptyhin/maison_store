<?php
namespace Cms;

final class PreviewToken {
	public static function create($registry, $page_id, $user_id) {
		$secret = $registry->get('config')->get('config_encryption');
		if (!$secret) {
			$secret = 'cms_preview';
		}

		$payload = (int)$page_id . '|' . (int)$user_id . '|' . time();
		return hash_hmac('sha256', $payload, $secret) . '.' . base64_encode($payload);
	}

	public static function validate($registry, $token, $page_id) {
		if (!$token || strpos($token, '.') === false) {
			return false;
		}

		list($hash, $encoded) = explode('.', $token, 2);
		$payload = base64_decode($encoded, true);
		if ($payload === false) {
			return false;
		}

		$secret = $registry->get('config')->get('config_encryption');
		if (!$secret) {
			$secret = 'cms_preview';
		}

		if (!hash_equals(hash_hmac('sha256', $payload, $secret), $hash)) {
			return false;
		}

		$parts = explode('|', $payload);
		if (count($parts) !== 3) {
			return false;
		}

		if ((int)$parts[0] !== (int)$page_id) {
			return false;
		}

		if ((time() - (int)$parts[2]) > 86400) {
			return false;
		}

		return (int)$parts[1];
	}
}
