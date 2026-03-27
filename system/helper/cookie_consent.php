<?php

/**
 * Cookie consent (catalog).
 * CookieConsent: JSON {"v":1,"analytics":bool,"marketing":bool}
 * Legacy CookieNotificationAccept implies analytics and marketing allowed.
 * Older JSON with only "marketing" treats analytics same as marketing for GA.
 */

function cookie_consent_expiry_timestamp() {
	return strtotime('+1 year');
}

function cookie_consent_choice_saved($request) {
	if (!empty($request->cookie['CookieNotificationAccept'])) {
		return true;
	}
	if (empty($request->cookie['CookieConsent'])) {
		return false;
	}
	$data = json_decode($request->cookie['CookieConsent'], true);

	return is_array($data) && isset($data['v']);
}

function cookie_consent_parse($request) {
	if (empty($request->cookie['CookieConsent'])) {
		return null;
	}
	$data = json_decode($request->cookie['CookieConsent'], true);
	if (!is_array($data) || !isset($data['v'])) {
		return null;
	}
	$marketing = !empty($data['marketing']);
	if (array_key_exists('analytics', $data)) {
		$analytics = !empty($data['analytics']);
	} else {
		$analytics = $marketing;
	}

	return array('analytics' => $analytics, 'marketing' => $marketing);
}

function cookie_consent_analytics_allowed($request, $customer) {
	if ($customer->isLogged()) {
		return true;
	}
	if (!empty($request->cookie['CookieNotificationAccept'])) {
		return true;
	}
	$flags = cookie_consent_parse($request);

	return $flags ? !empty($flags['analytics']) : false;
}

function cookie_consent_marketing_allowed($request, $customer) {
	if ($customer->isLogged()) {
		return true;
	}
	if (!empty($request->cookie['CookieNotificationAccept'])) {
		return true;
	}
	$flags = cookie_consent_parse($request);

	return $flags ? !empty($flags['marketing']) : false;
}

function cookie_consent_set_cookies($analytics, $marketing) {
	$expiry = cookie_consent_expiry_timestamp();
	$payload = json_encode(array(
		'v'         => 1,
		'analytics' => (bool)$analytics,
		'marketing' => (bool)$marketing,
	));

	setcookie('CookieConsent', $payload, $expiry, '/');

	if ($analytics || $marketing) {
		setcookie('CookieNotificationAccept', '1', $expiry, '/');
	} else {
		setcookie('CookieNotificationAccept', '', time() - 3600, '/');
	}
}
