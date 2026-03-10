<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'AUS_PARCEL_REGULAR'           => array(
		'name'               => 'Regular / Parcel Post',
		'alternate_services' => array(
			'AUS_PARCEL_REGULAR_SATCHEL_500G',
			'AUS_PARCEL_REGULAR_SATCHEL_1KG',
			'AUS_PARCEL_REGULAR_SATCHEL_3KG',
			'AUS_PARCEL_REGULAR_SATCHEL_5KG',
			'AUS_LETTER_REGULAR_SMALL',
			'AUS_LETTER_REGULAR_MEDIUM',
			'AUS_LETTER_REGULAR_LARGE',
			'AUS_LETTER_REGULAR_LARGE_125',
			'AUS_LETTER_REGULAR_LARGE_250',
			'AUS_LETTER_REGULAR_LARGE_500',
		),
	),
	'AUS_PARCEL_EXPRESS'           => array(
		'name'               => 'Express Post',
		'alternate_services' => array(
			'AUS_PARCEL_EXPRESS_SATCHEL_SMALL',
			'AUS_PARCEL_EXPRESS_SATCHEL_MEDIUM',
			'AUS_PARCEL_EXPRESS_SATCHEL_LARGE',
			'AUS_PARCEL_EXPRESS_SATCHEL_EXTRA_LARGE',
			'AUS_LETTER_EXPRESS_SMALL',
			'AUS_LETTER_EXPRESS_MEDIUM',
			'AUS_LETTER_EXPRESS_LARGE',
		),
	),
	'AUS_PARCEL_COURIER'           => array(
		'name'               => 'Courier Post',
		'alternate_services' => array(
			'AUS_PARCEL_COURIER_SATCHEL_SMALL',
			'AUS_PARCEL_COURIER_SATCHEL_MEDIUM',
			'AUS_PARCEL_COURIER_SATCHEL_LARGE',
		),
	),
	'INT_PARCEL_STD_OWN_PACKAGING' => array(
		'name'               => 'International Standard',
		'alternate_services' => array(
			'INT_PARCEL_STD_SMALL_SATCHEL',
			'INT_PARCEL_STD_MEDIUM_SATCHEL',
			'INT_PARCEL_STD_LARGE_SATCHEL',
			'INT_PARCEL_STD_BOX',
			'INT_LETTER_REG_SMALL_ENVELOPE',
			'INT_LETTER_REG_LARGE_ENVELOPE',
		),
	),
	'INT_PARCEL_EXP_OWN_PACKAGING' => array(
		'name'               => 'International Express',
		'alternate_services' => array(
			'INT_PARCEL_EXP_SMALL_SATCHEL',
			'INT_PARCEL_EXP_MEDIUM_SATCHEL',
			'INT_PARCEL_EXP_LARGE_SATCHEL',
			'INT_PARCEL_EXP_BOX',
			'INT_LETTER_EXP_OWN_PACKAGING',
			'INT_LETTER_EXP_LARGE_ENVELOPE',
		),
	),
	'INT_PARCEL_COR_OWN_PACKAGING' => array(
		'name'               => 'International Courier',
		'alternate_services' => array(
			'INT_PARCEL_COR_SMALL_SATCHEL',
			'INT_PARCEL_COR_MEDIUM_SATCHEL',
			'INT_LETTER_COR_OWN_PACKAGING',
			'INT_LETTER_COR_SMALL_SATCHEL',
		),
	),
	'INT_PARCEL_AIR_OWN_PACKAGING' => array(
		'name'               => 'International Economy Air',
		'alternate_services' => array(
			'INT_LETTER_AIR_OWN_PACKAGING_LIGHT',
			'INT_LETTER_AIR_OWN_PACKAGING_MEDIUM_LIGHT',
			'INT_LETTER_AIR_OWN_PACKAGING_MEDIUM',
			'INT_LETTER_AIR_OWN_PACKAGING_HEAVY',
			'INT_LETTER_AIR_SMALL_ENVELOPE',
			'INT_LETTER_AIR_LARGE_ENVELOPE',
		),
	),
	'INT_PARCEL_SEA_OWN_PACKAGING' => array(
		'name' => 'International Economy Sea',
	),
);
