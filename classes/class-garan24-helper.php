<?php
/**
 * Helper class for Garan24 KPM
 *
 * @link http://www.woothemes.com/products/garan24/
 * @since 1.0.0
 *
 * @package WC_Gateway_Garan24
 */	

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_Garan24_Helper {

	public function __construct( $parent ) {
		$this->parent = $parent;
	}

	/**
	 * Helper function, gets Garan24 payment method testmode.
	 *
	 * @since 1.0.0
	 **/
	function get_test_mode() {
		return $this->parent->testmode;
	}

	/**
	 * Checks if method is enabled.
	 *
	 * @since 1.0.0
	 **/
	function get_enabled() {
		return $this->parent->enabled;
	}


	/**
	 * Helper function, gets Garan24 locale based on current locale.
	 *
	 * @since 1.0.0
	 *
	 * @param string $locale
	 *
	 * @return string $garan24_locale
	 **/
	function get_garan24_locale( $locale ) {
		switch ( $locale ) {
			case 'da_DK':
				$garan24_locale = 'da_dk';
				break;
			case 'de_DE' :
				$garan24_locale = 'de_de';
				break;
			case 'no_NO' :
			case 'nb_NO' :
			case 'nn_NO' :
				$garan24_locale = 'nb_no';
				break;
			case 'nl_NL' :
				$garan24_locale = 'nl_nl';
				break;
			case 'fi_FI' :
			case 'fi' :
				$garan24_locale = 'fi_fi';
				break;
			case 'sv_SE' :
				$garan24_locale = 'sv_se';
				break;
			case 'de_AT' :
				$garan24_locale = 'de_at';
				break;
			case 'en_GB' :
				$garan24_locale = 'en_gb';
				break;
			case 'en_US' :
				$garan24_locale = 'en_se';
				break;
			default:
				$garan24_locale = '';
		}

		return $garan24_locale;
	}

	/**
	 * Helper function, gets Garan24 secret based on country.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $country
	 *
	 * @return string $current_secret
	 **/
	function get_secret( $country = '' ) {
		global $woocommerce;

		if ( empty( $country ) ) {
			$country = ( isset( $woocommerce->customer->country ) ) ? $woocommerce->customer->country : $this->parent->shop_country;
		}

		switch ( $country ) {
			case 'DK' :
				$current_secret = $this->parent->secret_dk;
				break;
			case 'DE' :
				$current_secret = $this->parent->secret_de;
				break;
			case 'NL' :
				$current_secret = $this->parent->secret_nl;
				break;
			case 'NO' :
				$current_secret = $this->parent->secret_no;
				break;
			case 'FI' :
				$current_secret = $this->parent->secret_fi;
				break;
			case 'SE' :
				$current_secret = $this->parent->secret_se;
				break;
			case 'AT' :
				$current_secret = $this->parent->secret_at;
				break;
			default:
				$current_secret = '';
		}

		return $current_secret;
	}

	/**
	 * Helper function, gets currency for selected country.
	 *
	 * @since 1.0.0
	 *
	 * @param string $country
	 *
	 * @return string $currency
	 **/
	function get_currency_for_country( $country ) {
		switch ( $country ) {
			case 'DK' :
				$currency = 'DKK';
				break;
			case 'DE' :
				$currency = 'EUR';
				break;
			case 'NL' :
				$currency = 'EUR';
				break;
			case 'NO' :
				$currency = 'NOK';
				break;
			case 'FI' :
				$currency = 'EUR';
				break;
			case 'SE' :
				$currency = 'SEK';
				break;
			case 'AT' :
				$currency = 'EUR';
				break;
			default:
				$currency = '';
		}

		return $currency;
	}

	/**
	 * Helper function, gets Garan24 language for selected country.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $country
	 *
	 * @return string $language
	 **/
	function get_garan24_language( $country ) {
		switch ( $country ) {
			case 'DK' :
				$language = 'DA';
				break;
			case 'DE' :
				$language = 'DE';
				break;
			case 'NL' :
				$language = 'NL';
				break;
			case 'NO' :
				$language = 'NB';
				break;
			case 'FI' :
				$language = 'FI';
				break;
			case 'SE' :
				$language = 'SV';
				break;
			case 'AT' :
				$language = 'DE';
				break;
			default:
				$language = '';
		}

		return $language;
	}

	/**
	 * Helper function, gets Garan24 country.
	 *
	 * @since 1.0.0
	 *
	 * @return string $garan24_country
	 **/
	function get_garan24_country() {
		global $woocommerce;

		if ( $woocommerce->customer->get_country() ) {
			$garan24_country = $woocommerce->customer->get_country();
		} else {
			$garan24_country = $this->parent->shop_language;
			switch ( $this->parent->shop_country ) {
				case 'NB' :
					$garan24_country = 'NO';
					break;
				case 'SV' :
					$garan24_country = 'SE';
					break;
			}
		}

		// Check if $garan24_country exists among the authorized countries
		if ( ! in_array( $garan24_country, $this->parent->authorized_countries ) ) {
			return $this->parent->shop_country;
		} else {
			return $garan24_country;
		}
	}

	/**
	 * Helper function, gets invoice icon.
	 *
	 * @since 1.0.0
	 **/
	function get_account_icon() {
		global $woocommerce;

		$country = ( isset( $woocommerce->customer->country ) ) ? $woocommerce->customer->country : '';

		if ( empty( $country ) ) {
			$country = $this->parent->shop_country;
		}

		switch ( $country ) {
			case 'DK':
				$garan24_part_payment_icon = 'https://cdn.garan24.com/1.0/shared/image/generic/logo/da_dk/basic/blue-black.png?width=100&eid=' . $this->get_eid();
				break;
			case 'DE':
				$garan24_part_payment_icon = 'https://cdn.garan24.com/1.0/shared/image/generic/logo/de_de/basic/blue-black.png?width=100&eid=' . $this->get_eid();
				break;
			case 'NL':
				$garan24_part_payment_icon = 'https://cdn.garan24.com/1.0/shared/image/generic/logo/nl_nl/basic/blue-black.png?width=100&eid=' . $this->get_eid();
				break;
			case 'NO':
				$garan24_part_payment_icon = false;
				break;
			case 'FI':
				$garan24_part_payment_icon = 'https://cdn.garan24.com/1.0/shared/image/generic/logo/fi_fi/basic/blue-black.png?width=100&eid=' . $this->get_eid();
				break;
			case 'SE':
				$garan24_part_payment_icon = 'https://cdn.garan24.com/1.0/shared/image/generic/logo/sv_se/basic/blue-black.png?width=100&eid=' . $this->get_eid();
				break;
			case 'AT':
				$garan24_part_payment_icon = 'https://cdn.garan24.com/1.0/shared/image/generic/logo/de_at/basic/blue-black.png?width=100&eid=' . $this->get_eid();
				break;
			default:
				$garan24_part_payment_icon = '';
		}

		return $garan24_part_payment_icon;
	}

	/**
	 * Helper function, gets Garan24 eid based on country.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $country
	 *
	 * @return integer $current_eid
	 **/
	function get_eid( $country = '' ) {
		global $woocommerce;

		if ( empty( $country ) ) {
			$country = ( isset( $woocommerce->customer->country ) ) ? $woocommerce->customer->country : $this->parent->shop_country;
		}

		switch ( $country ) {
			case 'DK' :
				$current_eid = $this->parent->eid_dk;
				break;
			case 'DE' :
				$current_eid = $this->parent->eid_de;
				break;
			case 'NL' :
				$current_eid = $this->parent->eid_nl;
				break;
			case 'NO' :
				$current_eid = $this->parent->eid_no;
				break;
			case 'FI' :
				$current_eid = $this->parent->eid_fi;
				break;
			case 'SE' :
				$current_eid = $this->parent->eid_se;
				break;
			case 'AT' :
				$current_eid = $this->parent->eid_at;
				break;
			default:
				$current_eid = '';
		}

		return $current_eid;
	}

}
