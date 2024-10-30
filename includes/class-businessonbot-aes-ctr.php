<?php
/**
 * BusinessOnBot
 *
 * AES counter (CTR) mode implementation in PHP
 * (c) Chris Veness 2005-2014 www.movable-type.co.uk/scripts
 * Right of free use is granted for all commercial or non-commercial use under CC-BY licence.
 * No warranty of any form is offered.
 *
 * @author  BusinessOnBot
 * @package BusinessOnBot/Encrypt-Decrypt-Data-Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * Controller for Encryption/Decryption for storing data.
 */
class BusinessOnBot_Aes_Ctr extends BusinessOnBot_Aes {

	/**
	 * Encrypt a text using AES encryption in Counter mode of operation
	 *  - see http://csrc.nist.gov/publications/nistpubs/800-38a/sp800-38a.pdf
	 *
	 * Unicode multi-byte character safe
	 *
	 * @param string $plaintext      Source text to be encrypted.
	 * @param string $password       The password to use to generate a key.
	 * @param int    $number_of_bits Number of bits to be used in the key (128, 192, or 256).
	 * @return string Encrypted text.
	 */
	public static function encrypt( string $plaintext, string $password, int $number_of_bits = 256 ): string {
		$block_size = 16; // block size fixed at 16 bytes / 128 bits (Nb=4) for AES.

		if ( ! ( 128 === $number_of_bits || 192 === $number_of_bits || 256 === $number_of_bits ) ) {
			return ''; // standard allows 128/192/256 bit keys.
		}

		/**
		 * Use AES itself to encrypt password to get cipher key (using plain password as source for key expansion)
		 * Gives us well encrypted key
		 */
		$number_of_bytes = $number_of_bits / 8; // no bytes in key.
		$password_bytes  = array();

		for ( $i = 0; $i < $number_of_bytes; $i++ ) {
			$password_bytes[ $i ] = ord( substr( $password, $i, 1 ) ) & 0xff;
		}

		$key = BusinessOnBot_Aes::cipher( $password_bytes, BusinessOnBot_Aes::key_expansion( $password_bytes ) );
		$key = array_merge( $key, array_slice( $key, 0, $number_of_bytes - 16 ) ); // expand key to 16/24/32 bytes long.

		/**
		 * Initialise 1st 8 bytes of counter block with nonce (NIST SP800-38A �B.2): [0-1] = millisec,
		 * [2-3] = random, [4-7] = seconds, giving guaranteed sub-ms uniqueness up to Feb 2106.
		 */
		$counter_block = array();
		$nonce         = floor( microtime( true ) * 1000 ); // Timestamp (ms) since 1-Jan-1970.
		$nonce_ms      = $nonce % 1000;
		$nonce_sec     = floor( $nonce / 1000 );
		$nonce_rand    = floor( wp_rand( 0, 65535 ) );

		for ( $i = 0; $i < 2; $i++ ) {
			$counter_block[ $i ] = self::urs( $nonce_ms, $i * 8 ) & 0xff;
		}

		for ( $i = 0; $i < 2; $i++ ) {
			$counter_block[ $i + 2 ] = self::urs( $nonce_rand, $i * 8 ) & 0xff;
		}

		for ( $i = 0; $i < 4; $i++ ) {
			$counter_block[ $i + 4 ] = self::urs( $nonce_sec, $i * 8 ) & 0xff;
		}

		// Convert it to a string to go on the front of the ciphertext.
		$counter_text = '';
		for ( $i = 0; $i < 8; $i++ ) {
			$counter_text .= chr( $counter_block[ $i ] );
		}

		// Generate key schedule - an expansion of the key into distinct Key Rounds for each round.
		$key_schedule = BusinessOnBot_Aes::key_expansion( $key );

		$block_count = ceil( strlen( $plaintext ) / $block_size );
		$cipher_text = array(); // Ciphertext as array of strings.

		for ( $b = 0; $b < $block_count; $b++ ) {
			/**
			 * Set counter (block #) in last 8 bytes of counter block (leaving nonce in 1st 8 bytes).
			 * Done in two stages for 32-bit ops: using two words allows us to go past 2^32 blocks (68GB).
			 */
			for ( $c = 0; $c < 4; $c++ ) {
				$counter_block[ 15 - $c ] = self::urs( $b, $c * 8 ) & 0xff;
			}

			for ( $c = 0; $c < 4; $c++ ) {
				$counter_block[ 15 - $c - 4 ] = self::urs( $b / 0x100000000, $c * 8 );
			}

			$cipher_counter = BusinessOnBot_Aes::cipher( $counter_block, $key_schedule ); // Encrypt counter block.

			// Block size is reduced on final block.
			$block_length = $b < $block_count - 1 ? $block_size : ( strlen( $plaintext ) - 1 ) % $block_size + 1;
			$cipher_byte  = array();

			for ( $i = 0; $i < $block_length; $i++ ) { // XOR plaintext with ciphered counter byte-by-byte.
				$cipher_byte[ $i ] = $cipher_counter[ $i ] ^ ord( substr( $plaintext, $b * $block_size + $i, 1 ) );
				$cipher_byte[ $i ] = chr( $cipher_byte[ $i ] );
			}
			$cipher_text[ $b ] = implode( '', $cipher_byte ); // Escape troublesome characters in ciphertext.
		}

		// Implode is more efficient than repeated string concatenation.
		$ciphertext = $counter_text . implode( '', $cipher_text );

		return base64_encode( $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode,Generic.PHP.ForbiddenFunctions.Found
	}

	/**
	 * Decrypt a text encrypted by AES in counter mode of operation
	 *
	 * @param string $ciphertext Source text to be decrypted.
	 * @param string $password The password to use to generate a key.
	 * @param int    $number_of_bits Number of bits to be used in the key (128, 192, or 256).
	 *
	 * @return string
	 */
	public static function decrypt( string $ciphertext, string $password, int $number_of_bits = 256 ): string {
		$block_size = 16; // Block size fixed at 16 bytes / 128 bits (Nb=4) for AES.

		if ( ! ( 128 === $number_of_bits || 192 === $number_of_bits || 256 === $number_of_bits ) ) {
			return ''; // Standard allows 128/192/256 bit keys.
		}
		$ciphertext = base64_decode( $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode,Generic.PHP.ForbiddenFunctions.Found

		// Use AES to encrypt password (mirroring encrypt routine).
		$number_of_bytes = $number_of_bits / 8; // No bytes in key.
		$password_bytes  = array();

		for ( $i = 0; $i < $number_of_bytes; $i++ ) {
			$password_bytes[ $i ] = ord( substr( $password, $i, 1 ) ) & 0xff;
		}

		$key = BusinessOnBot_Aes::cipher( $password_bytes, BusinessOnBot_Aes::key_expansion( $password_bytes ) );
		$key = array_merge( $key, array_slice( $key, 0, $number_of_bytes - 16 ) ); // Expand key to 16/24/32 bytes long.

		// Recover nonce from 1st element of ciphertext.
		$counter_block = array();
		$counter_text  = substr( $ciphertext, 0, 8 );
		for ( $i = 0; $i < 8; $i++ ) {
			$counter_block[ $i ] = ord( substr( $counter_text, $i, 1 ) );
		}

		// Generate key schedule.
		$key_schedule = BusinessOnBot_Aes::key_expansion( $key );

		// Separate ciphertext into blocks (skipping past initial 8 bytes).
		$number_of_blocks = ceil( ( strlen( $ciphertext ) - 8 ) / $block_size );
		$ct               = array();

		for ( $b = 0; $b < $number_of_blocks; $b++ ) {
			$ct[ $b ] = substr( $ciphertext, 8 + $b * $block_size, 16 );
		}

		$ciphertext = $ct; // Ciphertext is now array of block-length strings.
		$plaintext  = array(); // Plaintext will get generated block-by-block into array of block-length strings.

		for ( $b = 0; $b < $number_of_blocks; $b++ ) {
			// Set counter (block #) in last 8 bytes of counter block (leaving nonce in 1st 8 bytes).
			for ( $c = 0; $c < 4; $c++ ) {
				$counter_block[ 15 - $c ] = self::urs( $b, $c * 8 ) & 0xff;
			}

			for ( $c = 0; $c < 4; $c++ ) {
				$counter_block[ 15 - $c - 4 ] = self::urs( ( $b + 1 ) / 0x100000000 - 1, $c * 8 ) & 0xff;
			}

			$cipher_counter = BusinessOnBot_Aes::cipher( $counter_block, $key_schedule ); // Encrypt counter block.

			$plaintext_byte    = array();
			$ciphertext_length = strlen( $ciphertext[ $b ] );

			for ( $i = 0; $i < $ciphertext_length; $i++ ) {
				// XOR plaintext with ciphered counter byte-by-byte.
				$plaintext_byte[ $i ] = $cipher_counter[ $i ] ^ ord( substr( $ciphertext[ $b ], $i, 1 ) );
				$plaintext_byte[ $i ] = chr( $plaintext_byte[ $i ] );

			}
			$plaintext[ $b ] = implode( '', $plaintext_byte );
		}

		// Join array of blocks into single plaintext string.
		return implode( '', $plaintext );
	}

	/**
	 * Unsigned right shift function, since PHP has neither >>> operator nor unsigned ints
	 *
	 * @param int $a  number to be shifted (32-bit integer).
	 * @param int $b  number of bits to shift a to the right (0..31).
	 *
	 * @return int right-shifted and zero-filled by b bits
	 */
	private static function urs( $a, $b ) {
		$a  = intval( $a ) & 0xffffffff;
		$b &= 0x1f; // (bounds check).

		if ( $a & 0x80000000 && $b > 0 ) { // if left-most bit set.
			$a     = ( $a >> 1 ) & 0x7fffffff; // right-shift one bit & clear left-most bit.
			$check = $b - 1;
			$a     = $a >> ( $check ); // remaining right-shifts.
		} else { // otherwise.
			$a = ( $a >> $b ); // use normal right-shift.
		}

		return $a;
	}
}
