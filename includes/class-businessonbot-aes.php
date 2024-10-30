<?php
/**
 * AES counter (CTR) mode implementation in PHP
 * (c) Chris Veness 2005-2014 www.movable-type.co.uk/scripts
 * Right of free use is granted for all commercial or non-commercial use under CC-BY licence.
 * No warranty of any form is offered.
 *
 * It will generate the encryption and decryption for data.
 *
 * @package BusinessOnBot/Encrypt-Decrypt-Data
 */

defined( 'ABSPATH' ) || exit;

/**
 * Encryption/Decryption for storing data.
 */
class BusinessOnBot_Aes {

	/**
	 * Sub-box
	 *
	 * @var array $sub_box Variable sBox is pre-computed multiplicative inverse in GF(2^8) used in subBytes and keyExpansion
	 */
	private static array $sub_box = array(
		0x63,
		0x7c,
		0x77,
		0x7b,
		0xf2,
		0x6b,
		0x6f,
		0xc5,
		0x30,
		0x01,
		0x67,
		0x2b,
		0xfe,
		0xd7,
		0xab,
		0x76,
		0xca,
		0x82,
		0xc9,
		0x7d,
		0xfa,
		0x59,
		0x47,
		0xf0,
		0xad,
		0xd4,
		0xa2,
		0xaf,
		0x9c,
		0xa4,
		0x72,
		0xc0,
		0xb7,
		0xfd,
		0x93,
		0x26,
		0x36,
		0x3f,
		0xf7,
		0xcc,
		0x34,
		0xa5,
		0xe5,
		0xf1,
		0x71,
		0xd8,
		0x31,
		0x15,
		0x04,
		0xc7,
		0x23,
		0xc3,
		0x18,
		0x96,
		0x05,
		0x9a,
		0x07,
		0x12,
		0x80,
		0xe2,
		0xeb,
		0x27,
		0xb2,
		0x75,
		0x09,
		0x83,
		0x2c,
		0x1a,
		0x1b,
		0x6e,
		0x5a,
		0xa0,
		0x52,
		0x3b,
		0xd6,
		0xb3,
		0x29,
		0xe3,
		0x2f,
		0x84,
		0x53,
		0xd1,
		0x00,
		0xed,
		0x20,
		0xfc,
		0xb1,
		0x5b,
		0x6a,
		0xcb,
		0xbe,
		0x39,
		0x4a,
		0x4c,
		0x58,
		0xcf,
		0xd0,
		0xef,
		0xaa,
		0xfb,
		0x43,
		0x4d,
		0x33,
		0x85,
		0x45,
		0xf9,
		0x02,
		0x7f,
		0x50,
		0x3c,
		0x9f,
		0xa8,
		0x51,
		0xa3,
		0x40,
		0x8f,
		0x92,
		0x9d,
		0x38,
		0xf5,
		0xbc,
		0xb6,
		0xda,
		0x21,
		0x10,
		0xff,
		0xf3,
		0xd2,
		0xcd,
		0x0c,
		0x13,
		0xec,
		0x5f,
		0x97,
		0x44,
		0x17,
		0xc4,
		0xa7,
		0x7e,
		0x3d,
		0x64,
		0x5d,
		0x19,
		0x73,
		0x60,
		0x81,
		0x4f,
		0xdc,
		0x22,
		0x2a,
		0x90,
		0x88,
		0x46,
		0xee,
		0xb8,
		0x14,
		0xde,
		0x5e,
		0x0b,
		0xdb,
		0xe0,
		0x32,
		0x3a,
		0x0a,
		0x49,
		0x06,
		0x24,
		0x5c,
		0xc2,
		0xd3,
		0xac,
		0x62,
		0x91,
		0x95,
		0xe4,
		0x79,
		0xe7,
		0xc8,
		0x37,
		0x6d,
		0x8d,
		0xd5,
		0x4e,
		0xa9,
		0x6c,
		0x56,
		0xf4,
		0xea,
		0x65,
		0x7a,
		0xae,
		0x08,
		0xba,
		0x78,
		0x25,
		0x2e,
		0x1c,
		0xa6,
		0xb4,
		0xc6,
		0xe8,
		0xdd,
		0x74,
		0x1f,
		0x4b,
		0xbd,
		0x8b,
		0x8a,
		0x70,
		0x3e,
		0xb5,
		0x66,
		0x48,
		0x03,
		0xf6,
		0x0e,
		0x61,
		0x35,
		0x57,
		0xb9,
		0x86,
		0xc1,
		0x1d,
		0x9e,
		0xe1,
		0xf8,
		0x98,
		0x11,
		0x69,
		0xd9,
		0x8e,
		0x94,
		0x9b,
		0x1e,
		0x87,
		0xe9,
		0xce,
		0x55,
		0x28,
		0xdf,
		0x8c,
		0xa1,
		0x89,
		0x0d,
		0xbf,
		0xe6,
		0x42,
		0x68,
		0x41,
		0x99,
		0x2d,
		0x0f,
		0xb0,
		0x54,
		0xbb,
		0x16,
	);

	/**
	 * Round constant
	 *
	 * @var array $round_constant Variable rCon is Round Constant used for the Key Expansion [1st col is 2^(r-1) in GF(2^8)]
	 */
	private static array $round_constant = array(
		array( 0x00, 0x00, 0x00, 0x00 ),
		array( 0x01, 0x00, 0x00, 0x00 ),
		array( 0x02, 0x00, 0x00, 0x00 ),
		array( 0x04, 0x00, 0x00, 0x00 ),
		array( 0x08, 0x00, 0x00, 0x00 ),
		array( 0x10, 0x00, 0x00, 0x00 ),
		array( 0x20, 0x00, 0x00, 0x00 ),
		array( 0x40, 0x00, 0x00, 0x00 ),
		array( 0x80, 0x00, 0x00, 0x00 ),
		array( 0x1b, 0x00, 0x00, 0x00 ),
		array( 0x36, 0x00, 0x00, 0x00 ),
	);

	/**
	 * AES Cipher function: encrypt 'input' with Rijndael algorithm
	 *
	 * @param array $input message as byte-array (16 bytes).
	 * @param array $w     key schedule as 2D byte-array (Nr+1 x Nb bytes) - generated from the cipher key by keyExpansion().
	 *
	 * @return array ciphertext as byte-array (16 bytes)
	 */
	public static function cipher( array $input, array $w ): array {
		$number_of_blocks = 4; // block size (in words): no of columns in state (fixed at 4 for AES).
		$number_of_rounds = count( $w ) / $number_of_blocks - 1; // no of rounds: 10/12/14 for 128/192/256-bit keys.
		$state            = array(); // initialise 4xNb byte-array 'state' with input.
		for ( $i = 0; $i < 4 * $number_of_blocks; $i++ ) {
			$state[ $i % 4 ][ floor( $i / 4 ) ] = $input[ $i ];
		}

		$state = self::add_round_key( $state, $w, 0, $number_of_blocks );

		for ( $round = 1; $round < $number_of_rounds; $round++ ) { // apply number_of_rounds rounds.
			$state = self::sub_bytes( $state, $number_of_blocks );
			$state = self::shift_rows( $state, $number_of_blocks );
			$state = self::mix_columns( $state );
			$state = self::add_round_key( $state, $w, $round, $number_of_blocks );
		}

		$state = self::sub_bytes( $state, $number_of_blocks );
		$state = self::shift_rows( $state, $number_of_blocks );
		$state = self::add_round_key( $state, $w, $number_of_rounds, $number_of_blocks );

		$output = array( 4 * $number_of_blocks ); // convert state to 1-d array before returning.

		for ( $i = 0; $i < 4 * $number_of_blocks; $i++ ) {
			$output[ $i ] = $state[ $i % 4 ][ floor( $i / 4 ) ];
		}

		return $output;
	}

	/**
	 * Xor Round Key into state S.
	 *
	 * @param array $state            State.
	 * @param array $w                W.
	 * @param int   $rounds           Rounds.
	 * @param int   $number_of_blocks Number of blocks.
	 *
	 * @return array
	 */
	private static function add_round_key( array $state, array $w, int $rounds, int $number_of_blocks ): array {
		for ( $r = 0; $r < 4; $r++ ) {
			for ( $c = 0; $c < $number_of_blocks; $c++ ) {
				$state[ $r ][ $c ] ^= $w[ $rounds * 4 + $c ][ $r ];
			}
		}
		return $state;
	}

	/**
	 * Apply SBox to state S.
	 *
	 * @param array $s                S.
	 * @param int   $number_of_blocks Number of blocks.
	 *
	 * @return array
	 */
	private static function sub_bytes( array $s, int $number_of_blocks ): array {
		for ( $r = 0; $r < 4; $r++ ) {
			for ( $c = 0; $c < $number_of_blocks; $c++ ) {
				$s[ $r ][ $c ] = self::$sub_box[ $s[ $r ][ $c ] ];
			}
		}
		return $s;
	}

	/**
	 * Shift row r of state S left by r bytes.
	 *
	 * @param array $s                S.
	 * @param int   $number_of_blocks Number of blocks.
	 *
	 * @return array
	 */
	private static function shift_rows( array $s, int $number_of_blocks ): array {
		$t = array( 4 );
		for ( $r = 1; $r < 4; $r++ ) {
			for ( $c = 0; $c < 4; $c++ ) {
				$t[ $c ] = $s[ $r ][ ( $c + $r ) % $number_of_blocks ]; // shift into temp copy.
			}
			for ( $c = 0; $c < 4; $c++ ) {
				$s[ $r ][ $c ] = $t[ $c ]; // and copy back.
			}
		} // note that this will work for Nb=4,5,6, but not 7,8 (always 4 for AES).

		return $s; // see fp.gladman.plus.com/cryptography_technology/rijndael/aes.spec.311.pdf.
	}

	/**
	 * Combine bytes of each col of state S.
	 *
	 * @param array $s S.
	 *
	 * @return array
	 */
	private static function mix_columns( array $s ): array {
		for ( $c = 0; $c < 4; $c++ ) {
			$a = array( 4 );
			$b = array( 4 );
			for ( $i = 0; $i < 4; $i++ ) {
				$a[ $i ] = $s[ $i ][ $c ];
				$b[ $i ] = $s[ $i ][ $c ] & 0x80 ? $s[ $i ][ $c ] << 1 ^ 0x011b : $s[ $i ][ $c ] << 1;
			}
			$s[0][ $c ] = $b[0] ^ $a[1] ^ $b[1] ^ $a[2] ^ $a[3];
			$s[1][ $c ] = $a[0] ^ $b[1] ^ $a[2] ^ $b[2] ^ $a[3];
			$s[2][ $c ] = $a[0] ^ $a[1] ^ $b[2] ^ $a[3] ^ $b[3];
			$s[3][ $c ] = $a[0] ^ $b[0] ^ $a[1] ^ $a[2] ^ $b[3];
		}

		return $s;
	}

	/**
	 * Generate Key Schedule from Cipher Key [�5.2].
	 *
	 * Perform key expansion on cipher key to generate a key schedule.
	 *
	 * @param array $key cipher key byte-array (16 bytes).
	 *
	 * @return array schedule as 2D byte-array (Nr+1 x Nb bytes).
	 */
	public static function key_expansion( array $key ): array {
		$number_of_blocks = 4; // block size (in words): no of columns in state (fixed at 4 for AES).
		$key_length       = count( $key ) / 4; // key length (in words): 4/6/8 for 128/192/256-bit keys.
		$number_of_rounds = $key_length + 6; // no of rounds: 10/12/14 for 128/192/256-bit keys.

		$w    = array();
		$temp = array();

		for ( $i = 0; $i < $key_length; $i++ ) {
			$r       = array( $key[ 4 * $i ], $key[ 4 * $i + 1 ], $key[ 4 * $i + 2 ], $key[ 4 * $i + 3 ] );
			$w[ $i ] = $r;
		}

		for ( $i = $key_length; $i < ( $number_of_blocks * ( $number_of_rounds + 1 ) ); $i++ ) {
			$w[ $i ] = array();
			for ( $t = 0; $t < 4; $t++ ) {
				$temp[ $t ] = $w[ $i - 1 ][ $t ];
			}
			if ( 0 === ( $i % $key_length ) ) {
				$temp = self::sub_word( self::rotate_word( $temp ) );
				for ( $t = 0; $t < 4; $t++ ) {
					$temp[ $t ] ^= self::$round_constant[ $i / $key_length ][ $t ];
				}
			} elseif ( $key_length > 6 && 4 === ( $i % $key_length ) ) {
				$temp = self::sub_word( $temp );
			}
			for ( $t = 0; $t < 4; $t++ ) {
				$w[ $i ][ $t ] = $w[ $i - $key_length ][ $t ] ^ $temp[ $t ];
			}
		}

		return $w;
	}

	/**
	 * Apply SBox to 4-byte word w.
	 *
	 * @param array $w W.
	 *
	 * @return array
	 */
	private static function sub_word( array $w ): array {
		for ( $i = 0; $i < 4; $i++ ) {
			$w[ $i ] = self::$sub_box[ $w[ $i ] ];
		}

		return $w;
	}

	/**
	 * Rotate 4-byte word w left by one byte.
	 *
	 * @param array $w W.
	 *
	 * @return array
	 */
	private static function rotate_word( array $w ): array {
		$tmp = $w[0];
		for ( $i = 0; $i < 3; $i++ ) {
			$w[ $i ] = $w[ $i + 1 ];
		}
		$w[3] = $tmp;

		return $w;
	}
}
