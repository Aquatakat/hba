<?php

// this is literally in an object for no reason other than the autoloader
// is this what PHP is now?

// I miss mysql_real_escape_string

/* form definition:

$form = "[type]";
// where [type] is a type listed by

$form = ["string"];
// an array filled solely with strings

$form = ["string NULL"];
// an array filled with either strings or null

$form = ["foo" => "integer"];
// an object where "foo" is a key and its value is an integer

$form = ["string", "integer"];
// an array with two things, the first one is a string and the second one is an integer

$form = "anything";
// who cares, just pass it on through

if the form is fucked up it is very likely this function will break in fun and
unexpected ways. object values and arrays will be processed recursively

*/

namespace HBA;

class InputValidator {

	public static function validate($validatee, $form, $decode_json = false) {

		if ($decode_json) {
			$validatee = json_decode($validatee);
			if (json_last_error() !== \JSON_ERROR_NONE) {
				throw new InputValidationException(json_last_error_msg().' (JSON)');
			}
		}

		if (is_array($form)) {

			if (key($form) !== 0) {
				// form is associative

				if (!is_object($validatee)) throw new InputValidationException(sprintf('Wrong type; expected object, got %s.', gettype($validatee)));
				foreach ($form as $key => $subform) {
					if (!isset($validatee->{$key})) throw new InputValidationException(sprintf('Object is missing required key %s.', $key));
					self::validate($validatee->{$key}, $subform);
				}
				
			} else {
				// form is not associative

				if (!is_array($validatee)) throw new InputValidationException(sprintf('Wrong type; expected array, got %s.', gettype($validatee)));
				
				if (count($form) === 1) {
					foreach ($validatee as $subvalidatee) {
						self::validate($subvalidatee, reset($form));
					}
				} else {
					if (count($form) !== count($validatee)) throw new InputValidationException(sprintf('Wrong number for fixed value array; expected %d, got %d.', count($form), count($validatee)));
					foreach ($form as $key => $subform) {
						self::validate($validatee[$key], $subform);
					}
				}

			}

		} elseif (is_string($form)) {
			
			if ($form !== "anything") {

				$form = explode(" ", $form);
				if (!in_array($type = gettype($validatee), $form, true)) {
					throw new InputValidationException(sprintf('Wrong type; expected %s, got %s.', implode(' or ', $form), $type));
				}
				
			}

		} else {
			echo 'You fucked up the form. I told you not to do that.', PHP_EOL;
		}

		return $validatee;

	}

}

/*
I was seriously considering using composer to include a library that was over
a dozen objects for this. it has url validation. I hate the Internet.
*/